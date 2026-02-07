/**
 * Coffeebrk Stories Widget JavaScript
 * 
 * Handles story card clicks, video playback, and modal viewer
 */

(function () {
    'use strict';

    class CoffeebrkStoriesViewer {
        constructor() {
            this.currentIndex = 0;
            this.stories = [];
            this.viewer = null;
            this.container = null;
            this.autoplay = true;
            this.loop = false;
            this.startMuted = true;

            // Singleton Player Instances
            this.ytPlayer = null;
            this.vimeoPlayer = null;
            this.htmlVideoInfo = null; // Store reference to current HTML element

            this.init();
        }

        init() {
            this.loadAPIs();
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.bindEvents());
            } else {
                this.bindEvents();
            }
        }

        loadAPIs() {
            if (!window.YT) {
                const tag = document.createElement('script');
                tag.src = "https://www.youtube.com/iframe_api";
                const firstScriptTag = document.getElementsByTagName('script')[0];
                firstScriptTag.parentNode.insertBefore(tag, firstScriptTag);
            }
            if (!window.Vimeo) {
                const tag = document.createElement('script');
                tag.src = "https://player.vimeo.com/api/player.js";
                document.head.appendChild(tag);
            }
        }

        bindEvents() {
            document.querySelectorAll('.cbk-stories').forEach(container => {
                this.setupContainer(container);
            });

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeViewer();
                if (e.key === 'ArrowLeft') this.prevStory();
                if (e.key === 'ArrowRight') this.nextStory();
            });
        }

        setupContainer(container) {
            const cards = container.querySelectorAll('.cbk-stories__card');
            const wrapper = container.closest('.cbk-stories-wrapper');

            if (wrapper) {
                const prevBtn = wrapper.querySelector('.cbk-stories-nav--prev');
                const nextBtn = wrapper.querySelector('.cbk-stories-nav--next');
                if (prevBtn) prevBtn.addEventListener('click', () => container.scrollBy({ left: -300, behavior: 'smooth' }));
                if (nextBtn) nextBtn.addEventListener('click', () => container.scrollBy({ left: 300, behavior: 'smooth' }));
            }

            this.autoplay = container.dataset.autoplay === 'true';
            this.loop = container.dataset.loop === 'true';
            this.startMuted = container.dataset.muted === 'yes';

            cards.forEach((card, index) => {
                card.addEventListener('click', () => this.openViewer(container, index));
                if (card.classList.contains('cbk-stories__card--auto-gradient')) {
                    this.applyAutoGradient(card);
                }
            });
        }

        applyAutoGradient(card) {
            const thumbUrl = card.dataset.thumbUrl;
            const intensity = parseInt(card.dataset.intensity) || 50;
            const gradientEl = card.querySelector('.cbk-stories__gradient--auto');

            if (!thumbUrl || !gradientEl) return;

            const img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = () => {
                const color = this.extractDominantColor(img);
                if (color) {
                    card.style.setProperty('--gradient-color', color);
                    const startPercent = 100 - intensity;
                    const midPercent = 100 - (intensity * 0.5);
                    const highPercent = 100 - (intensity * 0.2);
                    const intensityFactor = intensity / 100;

                    gradientEl.style.background = `linear-gradient(180deg, 
                        rgba(245, 245, 255, 0) ${startPercent}%, 
                        ${this.hexToRgba(color, 0.5 * intensityFactor)} ${midPercent}%, 
                        ${this.hexToRgba(color, 0.9 * intensityFactor)} ${highPercent}%, 
                        ${color} 100%)`;
                }
            };
            img.onerror = () => {
                gradientEl.style.background = 'linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,0.6) 100%)';
            };
            img.src = thumbUrl;
        }

        extractDominantColor(img) {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                const sampleHeight = Math.floor(img.height / 3);
                canvas.width = 50;
                canvas.height = sampleHeight;
                ctx.drawImage(img, 0, img.height - sampleHeight, img.width, sampleHeight, 0, 0, 50, sampleHeight);
                const data = ctx.getImageData(0, 0, 50, sampleHeight).data;
                let r = 0, g = 0, b = 0, count = 0;
                for (let i = 0; i < data.length; i += 16) {
                    r += data[i];
                    g += data[i + 1];
                    b += data[i + 2];
                    count++;
                }
                return this.rgbToHex(Math.round(r / count), Math.round(g / count), Math.round(b / count));
            } catch (e) {
                return null;
            }
        }

        rgbToHex(r, g, b) {
            return '#' + [r, g, b].map(x => {
                const hex = x.toString(16);
                return hex.length === 1 ? '0' + hex : hex;
            }).join('');
        }

        hexToRgba(hex, alpha) {
            const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
            return result ? `rgba(${parseInt(result[1], 16)}, ${parseInt(result[2], 16)}, ${parseInt(result[3], 16)}, ${alpha})` : `rgba(128, 128, 128, ${alpha})`;
        }

        openViewer(container, index) {
            this.container = container;
            this.currentIndex = index;
            this.stories = Array.from(container.querySelectorAll('.cbk-stories__card'));

            const widgetId = container.closest('[data-id]')?.dataset.id || 'default';
            this.viewer = document.getElementById(`cbk-stories-viewer-${widgetId}`);

            if (!this.viewer) {
                this.viewer = this.createViewer();
                document.body.appendChild(this.viewer);
            }

            this.bindViewerEvents();
            this.showStory(index);
            this.viewer.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        createViewer() {
            const viewer = document.createElement('div');
            viewer.className = 'cbk-stories-viewer';
            viewer.innerHTML = `
                <div class="cbk-stories-viewer__overlay"></div>
                <button class="cbk-stories-viewer__close" aria-label="Close">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M18 6L6 18M6 6L18 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--prev">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div class="cbk-stories-viewer__content">
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--prev-2"></div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--prev"></div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--current">
                        <div class="cbk-stories-viewer__video-container"></div>
                    </div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--next"></div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--next-2"></div>
                </div>
                <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--next">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
            `;
            return viewer;
        }

        bindViewerEvents() {
            const close = () => this.closeViewer();
            const prev = () => this.prevStory();
            const next = () => this.nextStory();

            this.viewer.querySelector('.cbk-stories-viewer__close').onclick = close;
            this.viewer.querySelector('.cbk-stories-viewer__overlay').onclick = close;
            this.viewer.querySelector('.cbk-stories-viewer__nav--prev').onclick = prev;
            this.viewer.querySelector('.cbk-stories-viewer__nav--next').onclick = next;

            ['prev', 'next', 'prev-2', 'next-2'].forEach(type => {
                const item = this.viewer.querySelector(`.cbk-stories-viewer__item--${type}`);
                if (item) item.onclick = () => {
                    if (type === 'prev') this.prevStory();
                    if (type === 'next') this.nextStory();
                    if (type === 'prev-2' && this.currentIndex >= 2) this.showStory(this.currentIndex - 2);
                    if (type === 'next-2' && this.currentIndex < this.stories.length - 2) this.showStory(this.currentIndex + 2);
                };
            });
        }

        showStory(index) {
            if (index < 0 || index >= this.stories.length) return;

            this.currentIndex = index;
            const story = this.stories[index];
            const videoUrl = story.dataset.videoUrl;
            const videoContainer = this.viewer.querySelector('.cbk-stories-viewer__video-container');

            // Set size
            const currentItem = this.viewer.querySelector('.cbk-stories-viewer__item--current');
            if (currentItem) {
                Object.assign(currentItem.style, {
                    width: '380px', height: '680px', minWidth: '380px', minHeight: '680px',
                    flexShrink: '0', position: 'relative', background: '#000', borderRadius: '12px', overflow: 'hidden'
                });
            }
            if (videoContainer) {
                Object.assign(videoContainer.style, {
                    width: '100%', height: '100%', position: 'relative'
                });
            }

            // Detect Type
            const youtubeMatch = videoUrl && videoUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            const vimeoMatch = videoUrl && videoUrl.match(/(?:vimeo\.com\/)(\d+)/);

            // Hide/Pause other players
            this.pauseAllPlayers();

            if (youtubeMatch) {
                this.initYouTube(videoContainer, youtubeMatch[1]);
            } else if (vimeoMatch) {
                this.initVimeo(videoContainer, vimeoMatch[1]);
            } else if (videoUrl) {
                this.initHTMLVideo(videoContainer, videoUrl);
            } else {
                videoContainer.innerHTML = '';
                videoContainer.appendChild(this.createPlaceholder());
            }

            this.updateSideItems(index);
            this.updateNavigation();
        }

        pauseAllPlayers() {
            if (this.ytPlayer && typeof this.ytPlayer.stopVideo === 'function') {
                this.ytPlayer.stopVideo();
                if (this.ytPlayer.getIframe()) this.ytPlayer.getIframe().style.display = 'none';
            }
            if (this.vimeoPlayer) {
                this.vimeoPlayer.pause().catch(() => { });
                if (this.vimeoPlayer.element) this.vimeoPlayer.element.style.display = 'none';
            }
            if (this.htmlVideoInfo) {
                this.htmlVideoInfo.pause(); // Pause video
                this.htmlVideoInfo.style.display = 'none';
            }
        }

        initYouTube(container, videoId) {
            // Create container if not exists (singleton container)
            let ytContainer = document.getElementById('cbk-yt-player-instance');
            if (!ytContainer) {
                ytContainer = document.createElement('div');
                ytContainer.id = 'cbk-yt-player-instance';
                container.appendChild(ytContainer);
            } else if (ytContainer.parentNode !== container) {
                container.appendChild(ytContainer);
            }
            ytContainer.style.display = 'block';

            if (this.ytPlayer) {
                this.ytPlayer.loadVideoById(videoId);
                this.handleYouTubeMuteAndPlay();
            } else {
                if (window.YT && window.YT.Player) {
                    this.ytPlayer = new YT.Player('cbk-yt-player-instance', {
                        videoId: videoId,
                        width: '100%', height: '100%',
                        playerVars: {
                            'autoplay': 0, // We control playback manually
                            'controls': 1,
                            'rel': 0,
                            'playsinline': 1,
                            'enablejsapi': 1,
                            'origin': window.location.origin
                        },
                        events: {
                            'onReady': (event) => this.handleYouTubeMuteAndPlay(),
                            'onStateChange': (event) => {
                                if (event.data === YT.PlayerState.ENDED) this.nextStory();
                            }
                        }
                    });
                } else {
                    setTimeout(() => this.initYouTube(container, videoId), 100);
                }
            }
        }

        handleYouTubeMuteAndPlay() {
            if (!this.ytPlayer) return;
            // Force display again to be sure
            if (this.ytPlayer.getIframe()) {
                this.ytPlayer.getIframe().style.display = 'block';
                this.ytPlayer.getIframe().style.width = '100%';
                this.ytPlayer.getIframe().style.height = '100%';
            }

            if (this.startMuted) {
                this.ytPlayer.mute();
            } else {
                this.ytPlayer.unMute();
            }

            if (this.autoplay) {
                this.ytPlayer.playVideo();
            }
        }

        initVimeo(container, videoId) {
            let vimeoContainer = document.getElementById('cbk-vimeo-player-instance');
            if (!vimeoContainer) {
                vimeoContainer = document.createElement('div');
                vimeoContainer.id = 'cbk-vimeo-player-instance';
                container.appendChild(vimeoContainer);
            } else if (vimeoContainer.parentNode !== container) {
                container.appendChild(vimeoContainer);
            }
            vimeoContainer.style.display = 'block';

            if (this.vimeoPlayer) {
                this.vimeoPlayer.loadVideo(videoId).then(() => {
                    this.handleVimeoMuteAndPlay();
                });
                this.vimeoPlayer.element.style.display = 'block';
            } else {
                if (window.Vimeo && window.Vimeo.Player) {
                    this.vimeoPlayer = new Vimeo.Player(vimeoContainer, {
                        id: videoId,
                        width: 380,
                        autoplay: false,
                        muted: this.startMuted,
                        loop: false
                    });

                    this.vimeoPlayer.on('ended', () => this.nextStory());
                    // Initial load
                    this.handleVimeoMuteAndPlay();
                } else {
                    setTimeout(() => this.initVimeo(container, videoId), 100);
                }
            }
        }

        handleVimeoMuteAndPlay() {
            if (!this.vimeoPlayer) return;

            this.vimeoPlayer.setMuted(this.startMuted).catch(() => { });

            if (this.autoplay) {
                this.vimeoPlayer.play().catch(() => { });
            }
        }

        initHTMLVideo(container, url) {
            let video = document.getElementById('cbk-html-video-instance');
            if (!video) {
                video = document.createElement('video');
                video.id = 'cbk-html-video-instance';
                video.controls = true;
                video.playsInline = true;
                video.addEventListener('ended', () => this.nextStory());
                container.appendChild(video);
            } else if (video.parentNode !== container) {
                container.appendChild(video);
            }

            this.htmlVideoInfo = video;
            video.style.display = 'block';
            video.style.width = '100%';
            video.style.height = '100%';

            if (video.src !== url) {
                video.src = url;
            }

            video.muted = this.startMuted;
            if (this.autoplay) {
                video.play().catch(() => { });
            }
        }

        createPlaceholder() {
            const placeholder = document.createElement('div');
            placeholder.style.cssText = 'display: flex; align-items: center; justify-content: center; height: 100%; color: #fff; font-size: 18px;';
            placeholder.textContent = 'No video available';
            return placeholder;
        }

        updateSideItems(index) {
            const len = this.stories.length;
            const update = (cls, idx, opacity) => {
                const el = this.viewer.querySelector(cls);
                this.updateSideItem(el, idx, opacity);
            };

            let prev = index - 1;
            if (this.loop && prev < 0) prev = len - 1;
            update('.cbk-stories-viewer__item--prev', prev, 0.7);

            let next = index + 1;
            if (this.loop && next >= len) next = 0;
            update('.cbk-stories-viewer__item--next', next, 0.7);

            let prev2 = index - 2;
            if (this.loop) prev2 = (index - 2 + len) % len;
            update('.cbk-stories-viewer__item--prev-2', prev2, 0.5);

            let next2 = index + 2;
            if (this.loop) next2 = (index + 2) % len;
            update('.cbk-stories-viewer__item--next-2', next2, 0.5);
        }

        updateSideItem(element, storyIndex, opacity) {
            if (!element) return;
            const url = this.getThumbnailUrl(storyIndex);

            if (url) {
                element.style.backgroundImage = `url('${url}')`;
                element.style.opacity = opacity;
                element.style.pointerEvents = 'auto';
                element.style.display = 'block';
                if (element.className.includes('prev-2') || element.className.includes('next-2')) {
                    element.style.width = '120px'; element.style.height = '215px';
                } else {
                    element.style.width = '150px'; element.style.height = '270px';
                }
            } else {
                element.style.display = 'none';
            }
        }

        getThumbnailUrl(index) {
            if (index < 0 || index >= this.stories.length) return null;
            const card = this.stories[index];
            return card.dataset.thumbUrl || (card.querySelector('.cbk-stories__thumbnail')?.dataset.src) || '';
        }

        updateNavigation() {
            const prevBtn = this.viewer.querySelector('.cbk-stories-viewer__nav--prev');
            const nextBtn = this.viewer.querySelector('.cbk-stories-viewer__nav--next');
            if (this.loop) {
                prevBtn.disabled = nextBtn.disabled = false;
            } else {
                prevBtn.disabled = this.currentIndex === 0;
                nextBtn.disabled = this.currentIndex === this.stories.length - 1;
            }
        }

        prevStory() {
            if (this.currentIndex > 0) {
                this.showStory(this.currentIndex - 1);
            } else if (this.loop) {
                this.showStory(this.stories.length - 1);
            }
        }

        nextStory() {
            if (this.currentIndex < this.stories.length - 1) {
                this.showStory(this.currentIndex + 1);
            } else if (this.loop) {
                this.showStory(0);
            }
        }

        closeViewer() {
            if (!this.viewer) return;
            this.pauseAllPlayers();
            this.viewer.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    new CoffeebrkStoriesViewer();

    if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/coffeebrk_stories.default', function () {
            new CoffeebrkStoriesViewer();
        });
    }
})();
