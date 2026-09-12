/**
 * Coffeebrk Stories Widget JavaScript
 * 
 * Handles story card clicks, video playback, and modal viewer
 */

(function () {
    'use strict';

    // videoUrl ultimately comes from post meta set via the REST API (n8n,
    // YouTube importer, etc.) and is interpolated into innerHTML below for
    // the TikTok/Instagram embeds - escape it so a crafted URL can't break
    // out of the attribute and inject markup.
    function escapeHtmlAttr(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;');
    }

    class CoffeebrkStoriesViewer {
        constructor() {
            this.currentIndex = 0;
            this.stories = [];
            this.viewer = null;
            this.autoplay = true;
            this.loop = false;
            this.startMuted = true;
            this.gestureLocked = false;

            // Singleton Player Instances
            this.ytPlayer = null;
            this.vimeoPlayer = null;
            this.htmlVideoInfo = null; // Store reference to current HTML element
            this.storyTimer = null; // Fixed-duration auto-advance timer (TikTok/Instagram)

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
            if (!window.instgrm) {
                const tag = document.createElement('script');
                tag.src = "https://www.instagram.com/embed.js";
                document.head.appendChild(tag);
            }
            // TikTok's embed.js is loaded fresh on every initTikTok() call
            // instead (it has no re-scan API), so nothing to preload here.
        }

        bindEvents() {
            document.querySelectorAll('.cbk-stories').forEach(container => {
                this.setupContainer(container);
            });

            this.setupLoopCards();

            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') this.closeViewer();
                if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') this.prevStory();
                if (e.key === 'ArrowRight' || e.key === 'ArrowDown') this.nextStory();
            });
        }

        setupContainer(container) {
            if (container.dataset.cbkStoriesBound) return;
            container.dataset.cbkStoriesBound = 'true';

            const cards = container.querySelectorAll('.cbk-stories__card');
            const wrapper = container.closest('.cbk-stories-wrapper');

            if (wrapper) {
                const prevBtn = wrapper.querySelector('.cbk-stories-nav--prev');
                const nextBtn = wrapper.querySelector('.cbk-stories-nav--next');

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        // If at the beginning, scroll to end (infinite loop)
                        if (container.scrollLeft <= 10) {
                            container.scrollTo({ left: container.scrollWidth, behavior: 'smooth' });
                        } else {
                            container.scrollBy({ left: -300, behavior: 'smooth' });
                        }
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        // If at the end, scroll to beginning (infinite loop)
                        const maxScroll = container.scrollWidth - container.clientWidth;
                        if (container.scrollLeft >= maxScroll - 10) {
                            container.scrollTo({ left: 0, behavior: 'smooth' });
                        } else {
                            container.scrollBy({ left: 300, behavior: 'smooth' });
                        }
                    });
                }
            }

            const widgetId = container.closest('[data-id]')?.dataset.id || 'default';
            const viewerId = `cbk-stories-viewer-${widgetId}`;
            const settings = {
                autoplay: container.dataset.autoplay === 'true',
                loop: container.dataset.loop === 'true',
                startMuted: container.dataset.muted === 'yes',
            };

            cards.forEach((card, index) => {
                card.addEventListener('click', () => {
                    const stories = Array.from(cards).map(c => ({ videoUrl: c.dataset.videoUrl }));
                    this.openViewer(stories, index, viewerId, settings);
                });
                if (card.classList.contains('cbk-stories__card--auto-gradient')) {
                    this.applyAutoGradient(card);
                }
            });
        }

        // Handles story cards built as native Elementor Loop Grid items (Video/Heading/Image
        // widgets bound to the cbk_story CPT via dynamic tags) rather than the Coffeebrk
        // Stories widget markup. Overlays a click-catcher on each video iframe so a click
        // opens our popup feed instead of playing inline inside the small card.
        setupLoopCards() {
            if (document.body.classList.contains('elementor-editor-active')) return;

            const allItems = document.querySelectorAll('.e-loop-item.cbk_story');
            if (!allItems.length) return;

            // Only real, playable videos go into the navigable feed - skip test/placeholder
            // cards with no video configured so scrolling never dead-ends on an empty story.
            const stories = Array.from(allItems).map(item => {
                const videoWidget = item.querySelector('.elementor-widget-video[data-settings]');
                let videoUrl = '';
                if (videoWidget) {
                    try {
                        const settings = JSON.parse(videoWidget.dataset.settings);
                        videoUrl = settings.youtube_url || settings.vimeo_url || (settings.hosted_url && settings.hosted_url.url) || '';
                    } catch (e) { /* ignore malformed settings */ }
                }
                return { element: item, videoUrl };
            }).filter(story => story.videoUrl);

            stories.forEach((story, index) => {
                const item = story.element;
                if (item.dataset.cbkLoopBound) return;
                item.dataset.cbkLoopBound = 'true';

                const wrapper = item.querySelector('.elementor-widget-video .elementor-wrapper');
                if (!wrapper) return;

                if (!wrapper.style.position) wrapper.style.position = 'relative';

                const catcher = document.createElement('div');
                catcher.className = 'cbk-story-click-catcher';
                catcher.addEventListener('click', () => {
                    this.openViewer(stories, index, 'cbk-stories-viewer-story-feed', {
                        autoplay: true, loop: true, startMuted: true,
                    });
                });
                wrapper.appendChild(catcher);
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

        // stories: array of { videoUrl }. settings: { autoplay, loop, startMuted }.
        openViewer(stories, index, viewerId, settings = {}) {
            this.stories = stories;
            this.currentIndex = index;
            this.autoplay = settings.autoplay !== false;
            this.loop = !!settings.loop;
            this.startMuted = settings.startMuted !== false;

            this.viewer = document.getElementById(viewerId);

            if (!this.viewer) {
                this.viewer = this.createViewer();
                this.viewer.id = viewerId;
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
                <button class="cbk-stories-viewer__unmute" aria-label="Unmute" style="display: none; position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 100; background: rgba(0,0,0,0.7); border: 2px solid #fff; border-radius: 50%; width: 80px; height: 80px; cursor: pointer; color: #fff;">
                    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="margin: auto; display: block;">
                        <path d="M11 5L6 9H2v6h4l5 4V5z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <line x1="23" y1="9" x2="17" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        <line x1="17" y1="9" x2="23" y2="15" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--prev">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                </button>
                <div class="cbk-stories-viewer__content">
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--current">
                        <div class="cbk-stories-viewer__video-container"></div>
                    </div>
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

            // Unmute button handler
            const unmuteBtn = this.viewer.querySelector('.cbk-stories-viewer__unmute');
            if (unmuteBtn) {
                unmuteBtn.onclick = () => {
                    console.log('[Stories] Unmute button clicked');
                    this.forceUnmute();
                    unmuteBtn.style.display = 'none';
                };
            }

            this.bindGestureEvents();
        }

        // ponytail: fixed-threshold wheel/swipe scroll, tune threshold if it feels too sensitive
        bindGestureEvents() {
            const content = this.viewer.querySelector('.cbk-stories-viewer__content');
            if (!content || content.dataset.cbkGestureBound) return;
            content.dataset.cbkGestureBound = 'true';

            const GESTURE_LOCK_MS = 500;
            const SWIPE_THRESHOLD = 50;

            const navigate = (direction) => {
                if (this.gestureLocked) return;
                this.gestureLocked = true;
                setTimeout(() => { this.gestureLocked = false; }, GESTURE_LOCK_MS);
                if (direction > 0) this.nextStory();
                else this.prevStory();
            };

            content.addEventListener('wheel', (e) => {
                e.preventDefault();
                navigate(e.deltaY > 0 ? 1 : -1);
            }, { passive: false });

            let touchStartY = 0;
            content.addEventListener('touchstart', (e) => {
                touchStartY = e.touches[0].clientY;
            }, { passive: true });

            content.addEventListener('touchend', (e) => {
                const deltaY = touchStartY - e.changedTouches[0].clientY;
                if (Math.abs(deltaY) > SWIPE_THRESHOLD) {
                    navigate(deltaY > 0 ? 1 : -1);
                }
            }, { passive: true });
        }

        forceUnmute() {
            if (this.ytPlayer && typeof this.ytPlayer.unMute === 'function') {
                this.ytPlayer.unMute();
                this.ytPlayer.setVolume(100);
                console.log('[Stories] YouTube player unmuted');
            }
            if (this.vimeoPlayer) {
                this.vimeoPlayer.setMuted(false).catch(() => { });
                this.vimeoPlayer.setVolume(1).catch(() => { });
                console.log('[Stories] Vimeo player unmuted');
            }
            if (this.htmlVideoInfo) {
                this.htmlVideoInfo.muted = false;
                console.log('[Stories] HTML video unmuted');
            }
        }

        showUnmuteButton() {
            const btn = this.viewer?.querySelector('.cbk-stories-viewer__unmute');
            if (btn && !this.startMuted) {
                btn.style.display = 'flex';
                console.log('[Stories] Showing unmute button');
            }
        }

        hideUnmuteButton() {
            const btn = this.viewer?.querySelector('.cbk-stories-viewer__unmute');
            if (btn) {
                btn.style.display = 'none';
            }
        }

        showStory(index, direction) {
            if (index < 0 || index >= this.stories.length) return;

            this.currentIndex = index;
            const story = this.stories[index];
            const videoUrl = story.videoUrl;
            const videoContainer = this.viewer.querySelector('.cbk-stories-viewer__video-container');

            const currentItem = this.viewer.querySelector('.cbk-stories-viewer__item--current');
            if (currentItem && direction) {
                const slideClass = direction > 0 ? 'cbk-slide-up' : 'cbk-slide-down';
                currentItem.classList.remove('cbk-slide-up', 'cbk-slide-down');
                // Force reflow so the animation restarts on consecutive navigations
                void currentItem.offsetWidth;
                currentItem.classList.add(slideClass);
            }
            if (videoContainer) {
                Object.assign(videoContainer.style, {
                    width: '100%', height: '100%', position: 'relative'
                });
            }

            // Detect Type (supports youtube.com/watch, youtube.com/shorts, youtube.com/embed, youtu.be)
            const youtubeMatch = videoUrl && videoUrl.match(/(?:youtube\.com\/(?:shorts\/|watch\?v=|embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/);
            const vimeoMatch = videoUrl && videoUrl.match(/(?:vimeo\.com\/)(\d+)/);
            const tiktokMatch = videoUrl && videoUrl.match(/tiktok\.com\/(?:@[\w.-]+\/video\/(\d+)|t\/\w+)/i);
            const instagramMatch = videoUrl && videoUrl.match(/instagram\.com\/(?:p|reel|tv)\/([A-Za-z0-9_-]+)/i);

            // Hide/Pause other players
            this.pauseAllPlayers();

            if (youtubeMatch) {
                this.initYouTube(videoContainer, youtubeMatch[1]);
            } else if (vimeoMatch) {
                this.initVimeo(videoContainer, vimeoMatch[1]);
            } else if (tiktokMatch) {
                this.initTikTok(videoContainer, videoUrl, tiktokMatch[1] || '');
            } else if (instagramMatch) {
                this.initInstagram(videoContainer, videoUrl);
            } else if (videoUrl) {
                this.initHTMLVideo(videoContainer, videoUrl);
            } else {
                videoContainer.innerHTML = '';
                videoContainer.appendChild(this.createPlaceholder());
            }

            this.updateNavigation();
        }

        pauseAllPlayers() {
            if (this.storyTimer) {
                clearTimeout(this.storyTimer);
                this.storyTimer = null;
            }

            const hidePlayer = (el) => {
                if (el) {
                    el.style.visibility = 'hidden';
                    el.style.opacity = '0';
                    el.style.zIndex = '-1';
                }
            };

            if (this.ytPlayer && typeof this.ytPlayer.stopVideo === 'function') {
                this.ytPlayer.pauseVideo(); // Pause instead of stop to keep buffer if possible, or stop if needed
                if (this.ytPlayer.getIframe()) hidePlayer(this.ytPlayer.getIframe());
            }
            if (this.vimeoPlayer) {
                this.vimeoPlayer.pause().catch(() => { });
                if (this.vimeoPlayer.element) hidePlayer(this.vimeoPlayer.element);
            }
            if (this.htmlVideoInfo) {
                this.htmlVideoInfo.pause();
                hidePlayer(this.htmlVideoInfo);
            }

            hidePlayer(document.getElementById('cbk-tiktok-player-instance'));
            hidePlayer(document.getElementById('cbk-instagram-player-instance'));
        }

        initYouTube(container, videoId) {
            // Create container if not exists (singleton container)
            let ytContainer = document.getElementById('cbk-yt-player-instance');
            if (!ytContainer) {
                ytContainer = document.createElement('div');
                ytContainer.id = 'cbk-yt-player-instance';
                // Force absolute positioning for stacking
                ytContainer.style.position = 'absolute';
                ytContainer.style.top = '0';
                ytContainer.style.left = '0';
                ytContainer.style.width = '100%';
                ytContainer.style.height = '100%';
                container.appendChild(ytContainer);
            } else if (ytContainer.parentNode !== container) {
                container.appendChild(ytContainer);
            }

            // Show Player
            ytContainer.style.visibility = 'visible';
            ytContainer.style.opacity = '1';
            ytContainer.style.zIndex = '1';

            if (this.ytPlayer) {
                this.ytPlayer.loadVideoById(videoId);
                this.handleYouTubeMuteAndPlay();
            } else {
                if (window.YT && window.YT.Player) {
                    this.ytPlayer = new YT.Player('cbk-yt-player-instance', {
                        videoId: videoId,
                        width: '100%', height: '100%',
                        playerVars: {
                            'autoplay': this.autoplay ? 1 : 0,
                            'mute': this.startMuted ? 1 : 0,
                            'controls': 1,
                            'rel': 0,
                            'playsinline': 1,
                            'enablejsapi': 1,
                            'origin': window.location.origin
                        },
                        events: {
                            'onReady': (event) => {
                                console.log('[Stories] YouTube onReady - startMuted:', this.startMuted, 'autoplay:', this.autoplay);
                                this.handleYouTubeMuteAndPlay();
                            },
                            'onStateChange': (event) => {
                                // Loop Logic
                                if (event.data === YT.PlayerState.ENDED) this.nextStory();

                                // Check mute state when playing
                                if (event.data === YT.PlayerState.PLAYING) {
                                    const isMuted = event.target.isMuted();
                                    console.log('[Stories] YouTube PLAYING - isMuted:', isMuted, 'startMuted setting:', this.startMuted);

                                    if (!this.startMuted && isMuted) {
                                        // Video is muted but shouldn't be - try to unmute
                                        console.log('[Stories] Attempting to unmute...');
                                        event.target.unMute();
                                        event.target.setVolume(100);

                                        // Check if unmute worked
                                        setTimeout(() => {
                                            if (event.target.isMuted()) {
                                                console.log('[Stories] Unmute failed - showing unmute button');
                                                this.showUnmuteButton();
                                            } else {
                                                console.log('[Stories] Unmute successful');
                                                this.hideUnmuteButton();
                                            }
                                        }, 100);
                                    } else if (this.startMuted && !isMuted) {
                                        // Video should be muted but isn't
                                        event.target.mute();
                                    } else if (!this.startMuted && !isMuted) {
                                        // Correctly unmuted
                                        this.hideUnmuteButton();
                                    }
                                }
                            },
                            'onError': (event) => {
                                // ponytail: no retry/backoff - a dead video (deleted,
                                // private, embedding disabled) is dead, just move on.
                                this.nextStory();
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
            const iframe = this.ytPlayer.getIframe();
            if (iframe) {
                iframe.style.visibility = 'visible';
                iframe.style.opacity = '1';
                iframe.style.zIndex = '1';
                iframe.style.position = 'absolute';
                iframe.style.top = '0';
                iframe.style.left = '0';
            }

            // Explicitly Mute/Unmute BEFORE play
            if (this.startMuted) {
                this.ytPlayer.mute();
            } else {
                this.ytPlayer.unMute();
                this.ytPlayer.setVolume(100);
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
                // Force absolute positioning
                vimeoContainer.style.position = 'absolute';
                vimeoContainer.style.top = '0';
                vimeoContainer.style.left = '0';
                vimeoContainer.style.width = '100%';
                vimeoContainer.style.height = '100%';
                container.appendChild(vimeoContainer);
            } else if (vimeoContainer.parentNode !== container) {
                container.appendChild(vimeoContainer);
            }

            // Show Player
            vimeoContainer.style.visibility = 'visible';
            vimeoContainer.style.opacity = '1';
            vimeoContainer.style.zIndex = '1';

            if (this.vimeoPlayer) {
                this.vimeoPlayer.loadVideo(videoId).then(() => {
                    this.handleVimeoMuteAndPlay();
                });
                // Ensure element is visible
                if (this.vimeoPlayer.element) {
                    this.vimeoPlayer.element.style.visibility = 'visible';
                    this.vimeoPlayer.element.style.opacity = '1';
                    this.vimeoPlayer.element.style.zIndex = '1';
                }
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

                    // Enforcement Logic
                    this.vimeoPlayer.on('play', () => {
                        if (!this.startMuted) {
                            this.vimeoPlayer.setMuted(false).catch(() => { });
                            this.vimeoPlayer.setVolume(1).catch(() => { });
                        }
                    });

                    // Initial load
                    this.handleVimeoMuteAndPlay();
                } else {
                    setTimeout(() => this.initVimeo(container, videoId), 100);
                }
            }
        }

        handleVimeoMuteAndPlay() {
            if (!this.vimeoPlayer) return;

            // Force display properties
            if (this.vimeoPlayer.element) {
                this.vimeoPlayer.element.style.visibility = 'visible';
                this.vimeoPlayer.element.style.opacity = '1';
                this.vimeoPlayer.element.style.zIndex = '1';
                this.vimeoPlayer.element.style.position = 'absolute';
                this.vimeoPlayer.element.style.top = '0';
                this.vimeoPlayer.element.style.left = '0';
                this.vimeoPlayer.element.style.width = '100%';
                this.vimeoPlayer.element.style.height = '100%';
            }

            this.vimeoPlayer.setMuted(this.startMuted).catch(() => { });

            if (!this.startMuted) {
                this.vimeoPlayer.setVolume(1).catch(() => { });
            }

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
                // Force absolute positioning
                video.style.position = 'absolute';
                video.style.top = '0';
                video.style.left = '0';
                video.style.width = '100%';
                video.style.height = '100%';
                container.appendChild(video);
            } else if (video.parentNode !== container) {
                container.appendChild(video);
            }

            this.htmlVideoInfo = video;

            // Show Player
            video.style.visibility = 'visible';
            video.style.opacity = '1';
            video.style.zIndex = '1';

            if (video.src !== url) {
                video.src = url;
            }

            video.muted = this.startMuted;
            if (this.autoplay) {
                const playPromise = video.play();
                if (playPromise !== undefined) {
                    playPromise.catch(() => { });
                }
            }
        }

        initTikTok(container, url, videoId) {
            let ttContainer = document.getElementById('cbk-tiktok-player-instance');
            if (!ttContainer) {
                ttContainer = document.createElement('div');
                ttContainer.id = 'cbk-tiktok-player-instance';
                Object.assign(ttContainer.style, {
                    position: 'absolute', top: '0', left: '0', width: '100%', height: '100%'
                });
                container.appendChild(ttContainer);
            } else if (ttContainer.parentNode !== container) {
                container.appendChild(ttContainer);
            }

            ttContainer.style.visibility = 'visible';
            ttContainer.style.opacity = '1';
            ttContainer.style.zIndex = '1';

            const safeUrl = escapeHtmlAttr(url);
            const safeVideoId = videoId ? escapeHtmlAttr(videoId) : '';
            ttContainer.innerHTML = `<blockquote class="tiktok-embed" cite="${safeUrl}" ${safeVideoId ? `data-video-id="${safeVideoId}"` : ''} style="max-width:100%;min-width:280px;"><section></section></blockquote>`;

            // ponytail: TikTok's embed.js has no documented re-scan API (unlike
            // Instagram's instgrm.Embeds.process()) - removing and re-adding a
            // fresh script tag is the only reliable way to make it pick up a
            // dynamically inserted blockquote. Upgrade if TikTok ever ships one.
            const old = document.getElementById('cbk-tiktok-embed-script');
            if (old) old.remove();
            const script = document.createElement('script');
            script.id = 'cbk-tiktok-embed-script';
            script.async = true;
            script.src = 'https://www.tiktok.com/embed.js';
            document.body.appendChild(script);

            this.startFixedTimer();
        }

        initInstagram(container, url) {
            let igContainer = document.getElementById('cbk-instagram-player-instance');
            if (!igContainer) {
                igContainer = document.createElement('div');
                igContainer.id = 'cbk-instagram-player-instance';
                Object.assign(igContainer.style, {
                    position: 'absolute', top: '0', left: '0', width: '100%', height: '100%',
                    overflow: 'auto', background: '#000'
                });
                container.appendChild(igContainer);
            } else if (igContainer.parentNode !== container) {
                container.appendChild(igContainer);
            }

            igContainer.style.visibility = 'visible';
            igContainer.style.opacity = '1';
            igContainer.style.zIndex = '1';

            const safeUrl = escapeHtmlAttr(url);
            igContainer.innerHTML = `<blockquote class="instagram-media" data-instgrm-permalink="${safeUrl}" data-instgrm-version="14" style="width:100%;"><a href="${safeUrl}"></a></blockquote>`;

            if (window.instgrm && window.instgrm.Embeds) {
                window.instgrm.Embeds.process();
            } // else: loadAPIs() already queued embed.js; it auto-processes on load.

            this.startFixedTimer();
        }

        startFixedTimer() {
            // ponytail: naive fixed-duration timer, not a real end-of-video signal -
            // neither TikTok's nor Instagram's embed widget fires a JS "ended" event.
            // Upgrade if either platform ever ships a postMessage-based player API.
            this.storyTimer = setTimeout(() => this.nextStory(), 15000);
        }

        createPlaceholder() {
            const placeholder = document.createElement('div');
            placeholder.style.cssText = 'display: flex; align-items: center; justify-content: center; height: 100%; color: #fff; font-size: 18px;';
            placeholder.textContent = 'No video available';
            return placeholder;
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
                this.showStory(this.currentIndex - 1, -1);
            } else if (this.loop) {
                this.showStory(this.stories.length - 1, -1);
            }
        }

        nextStory() {
            if (this.currentIndex < this.stories.length - 1) {
                this.showStory(this.currentIndex + 1, 1);
            } else if (this.loop) {
                this.showStory(0, 1);
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

    if (window.elementorFrontend && window.elementorFrontend.hooks) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/coffeebrk_stories.default', function () {
            new CoffeebrkStoriesViewer();
        });
    }
})();
