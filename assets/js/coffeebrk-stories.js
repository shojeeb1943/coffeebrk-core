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

            this.init();
        }

        init() {
            this.loadAPIs();
            // Wait for DOM to be ready
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
            // Bind click events to all story cards
            document.querySelectorAll('.cbk-stories').forEach(container => {
                this.setupContainer(container);
            });

            // Close on escape key
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape') {
                    this.closeViewer();
                }
                if (e.key === 'ArrowLeft') {
                    this.prevStory();
                }
                if (e.key === 'ArrowRight') {
                    this.nextStory();
                }
            });
        }

        setupContainer(container) {
            const cards = container.querySelectorAll('.cbk-stories__card');

            // Setup Carousel Navigation
            const wrapper = container.closest('.cbk-stories-wrapper');
            if (wrapper) {
                const prevBtn = wrapper.querySelector('.cbk-stories-nav--prev');
                const nextBtn = wrapper.querySelector('.cbk-stories-nav--next');

                if (prevBtn) {
                    prevBtn.addEventListener('click', () => {
                        container.scrollBy({ left: -300, behavior: 'smooth' });
                    });
                }

                if (nextBtn) {
                    nextBtn.addEventListener('click', () => {
                        container.scrollBy({ left: 300, behavior: 'smooth' });
                    });
                }
            }

            this.autoplay = container.dataset.autoplay === 'true';
            this.loop = container.dataset.loop === 'true';
            this.startMuted = container.dataset.muted === 'yes';

            cards.forEach((card, index) => {
                card.addEventListener('click', () => {
                    this.openViewer(container, index);
                });

                // Auto-detect gradient color if not set
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
                    // Calculate gradient stops based on intensity
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
                // Fallback to a neutral gradient
                gradientEl.style.background = 'linear-gradient(180deg, rgba(0,0,0,0) 50%, rgba(0,0,0,0.6) 100%)';
            };
            img.src = thumbUrl;
        }

        extractDominantColor(img) {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');

                // Sample from the bottom third of the image (where gradient appears)
                const sampleHeight = Math.floor(img.height / 3);
                canvas.width = 50; // Small sample for performance
                canvas.height = sampleHeight;

                ctx.drawImage(img, 0, img.height - sampleHeight, img.width, sampleHeight, 0, 0, 50, sampleHeight);

                const imageData = ctx.getImageData(0, 0, 50, sampleHeight);
                const data = imageData.data;

                let r = 0, g = 0, b = 0, count = 0;

                // Sample every 4th pixel for performance
                for (let i = 0; i < data.length; i += 16) {
                    r += data[i];
                    g += data[i + 1];
                    b += data[i + 2];
                    count++;
                }

                r = Math.round(r / count);
                g = Math.round(g / count);
                b = Math.round(b / count);

                return this.rgbToHex(r, g, b);
            } catch (e) {
                console.warn('Could not extract color from image:', e);
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
            if (result) {
                const r = parseInt(result[1], 16);
                const g = parseInt(result[2], 16);
                const b = parseInt(result[3], 16);
                return `rgba(${r}, ${g}, ${b}, ${alpha})`;
            }
            return `rgba(128, 128, 128, ${alpha})`;
        }

        openViewer(container, index) {
            this.container = container;
            this.currentIndex = index;
            this.stories = Array.from(container.querySelectorAll('.cbk-stories__card'));

            // Find or create viewer
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
                <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--prev" aria-label="Previous">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M15 18L9 12L15 6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
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
                <button class="cbk-stories-viewer__nav cbk-stories-viewer__nav--next" aria-label="Next">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 6L15 12L9 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
            `;
            return viewer;
        }

        bindViewerEvents() {
            // Close button
            this.viewer.querySelector('.cbk-stories-viewer__close').addEventListener('click', () => {
                this.closeViewer();
            });

            // Overlay click to close
            this.viewer.querySelector('.cbk-stories-viewer__overlay').addEventListener('click', () => {
                this.closeViewer();
            });

            // Navigation
            this.viewer.querySelector('.cbk-stories-viewer__nav--prev').addEventListener('click', () => {
                this.prevStory();
            });

            this.viewer.querySelector('.cbk-stories-viewer__nav--next').addEventListener('click', () => {
                this.nextStory();
            });

            // Side items click
            const prevItem = this.viewer.querySelector('.cbk-stories-viewer__item--prev');
            if (prevItem) {
                prevItem.addEventListener('click', () => {
                    this.prevStory();
                });
            }

            const nextItem = this.viewer.querySelector('.cbk-stories-viewer__item--next');
            if (nextItem) {
                nextItem.addEventListener('click', () => {
                    this.nextStory();
                });
            }

            // Far side items (jump by 2)
            const prevItem2 = this.viewer.querySelector('.cbk-stories-viewer__item--prev-2');
            if (prevItem2) {
                prevItem2.addEventListener('click', () => {
                    if (this.currentIndex >= 2) {
                        this.showStory(this.currentIndex - 2);
                    }
                });
            }

            const nextItem2 = this.viewer.querySelector('.cbk-stories-viewer__item--next-2');
            if (nextItem2) {
                nextItem2.addEventListener('click', () => {
                    if (this.currentIndex < this.stories.length - 2) {
                        this.showStory(this.currentIndex + 2);
                    }
                });
            }
        }

        showStory(index) {
            if (index < 0 || index >= this.stories.length) {
                return;
            }

            this.currentIndex = index;
            const story = this.stories[index];
            const videoUrl = story.dataset.videoUrl;

            // Force center item dimensions
            const currentItem = this.viewer.querySelector('.cbk-stories-viewer__item--current');
            if (currentItem) {
                currentItem.style.width = '380px';
                currentItem.style.height = '680px';
                currentItem.style.minWidth = '380px';
                currentItem.style.minHeight = '680px';
                currentItem.style.flexShrink = '0';
                currentItem.style.position = 'relative';
                currentItem.style.background = '#000';
                currentItem.style.borderRadius = '12px';
                currentItem.style.overflow = 'hidden';
            }

            const videoContainer = this.viewer.querySelector('.cbk-stories-viewer__video-container');

            // Cleanup previous player
            if (this.player) {
                if (typeof this.player.destroy === 'function') {
                    this.player.destroy();
                } else if (typeof this.player.unload === 'function') {
                    this.player.unload(); // Vimeo
                }
                this.player = null;
            }

            // Clear content
            videoContainer.innerHTML = '';

            // Ensure container fills parent
            videoContainer.style.width = '100%';
            videoContainer.style.height = '100%';
            videoContainer.style.position = 'relative';

            // Detect video type
            const youtubeMatch = videoUrl && videoUrl.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            const vimeoMatch = videoUrl && videoUrl.match(/(?:vimeo\.com\/)(\d+)/);

            if (youtubeMatch) {
                const playerId = 'cbk-yt-player-' + Date.now();
                videoContainer.innerHTML = `<div id="${playerId}"></div>`;
                this.createYouTubePlayer(playerId, youtubeMatch[1]);
            } else if (vimeoMatch) {
                const playerId = 'cbk-vimeo-player-' + Date.now();
                videoContainer.innerHTML = `<div id="${playerId}"></div>`;
                this.createVimeoPlayer(playerId, vimeoMatch[1]);
            } else if (videoUrl) {
                const video = this.createHTMLVideo(videoUrl);
                videoContainer.appendChild(video);
            } else {
                videoContainer.appendChild(this.createPlaceholder());
            }

            // Update Side Items
            this.updateSideItems(index);

            // Update navigation state
            this.updateNavigation();
        }

        getThumbnailUrl(index) {
            if (index < 0 || index >= this.stories.length) return null;
            const card = this.stories[index];
            if (card.dataset.thumbUrl) return card.dataset.thumbUrl;
            const thumb = card.querySelector('.cbk-stories__thumbnail');
            let url = '';
            if (thumb) {
                url = thumb.dataset.src || '';
                if (!url) {
                    // Fallback to style backgroundImage parsing if data-src missing
                    const style = thumb.style.backgroundImage;
                    if (style && style.indexOf('url(') !== -1) {
                        url = style.replace(/^url\(['"]?/, '').replace(/['"]?\)$/, '');
                    }
                }
            }
            return url;
        }

        updateSideItems(index) {
            const len = this.stories.length;

            const prevItem = this.viewer.querySelector('.cbk-stories-viewer__item--prev');
            let prevIndex = index - 1;
            if (this.loop && prevIndex < 0) prevIndex = len - 1;
            this.updateSideItem(prevItem, prevIndex, 0.7);

            const nextItem = this.viewer.querySelector('.cbk-stories-viewer__item--next');
            let nextIndex = index + 1;
            if (this.loop && nextIndex >= len) nextIndex = 0;
            this.updateSideItem(nextItem, nextIndex, 0.7);

            const prevItem2 = this.viewer.querySelector('.cbk-stories-viewer__item--prev-2');
            let prevIndex2 = index - 2;
            if (this.loop) prevIndex2 = (index - 2 + len) % len;
            this.updateSideItem(prevItem2, prevIndex2, 0.5);

            const nextItem2 = this.viewer.querySelector('.cbk-stories-viewer__item--next-2');
            let nextIndex2 = index + 2;
            if (this.loop) nextIndex2 = (index + 2) % len;
            this.updateSideItem(nextItem2, nextIndex2, 0.5);
        }

        updateSideItem(element, storyIndex, opacity) {
            if (!element) return;

            const url = this.getThumbnailUrl(storyIndex);
            console.log('updateSideItem:', storyIndex, url); // Debug

            if (url) {
                element.style.backgroundImage = `url('${url}')`;
                element.style.opacity = opacity.toString();
                element.style.pointerEvents = 'auto';
                element.style.display = 'block';
                // Ensure dimensions are set
                if (element.classList.contains('cbk-stories-viewer__item--prev') ||
                    element.classList.contains('cbk-stories-viewer__item--next')) {
                    element.style.width = '150px';
                    element.style.height = '270px';
                } else {
                    element.style.width = '120px';
                    element.style.height = '215px';
                }
            } else {
                element.style.backgroundImage = 'none';
                element.style.opacity = '0';
                element.style.pointerEvents = 'none';
                element.style.display = 'none';
            }
        }

        createYouTubePlayer(containerId, videoId) {
            const onPlayerReady = (event) => {
                if (this.startMuted) {
                    event.target.mute();
                } else {
                    event.target.unMute();
                }
                if (this.autoplay) {
                    event.target.playVideo();
                }
            };

            const onPlayerStateChange = (event) => {
                if (event.data === YT.PlayerState.ENDED) {
                    this.nextStory();
                }
            };

            const initPlayer = () => {
                this.player = new YT.Player(containerId, {
                    videoId: videoId,
                    width: '100%',
                    height: '100%',
                    playerVars: {
                        'autoplay': this.autoplay ? 1 : 0,
                        'controls': 1,
                        'rel': 0,
                        'playsinline': 1,
                        'enablejsapi': 1,
                        'origin': window.location.origin,
                        'mute': this.startMuted ? 1 : 0
                    },
                    events: {
                        'onReady': onPlayerReady,
                        'onStateChange': onPlayerStateChange
                    }
                });
            };

            if (window.YT && window.YT.Player) {
                initPlayer();
            } else {
                const checkYT = setInterval(() => {
                    if (window.YT && window.YT.Player) {
                        clearInterval(checkYT);
                        initPlayer();
                    }
                }, 100);
            }
        }

        createVimeoPlayer(containerId, videoId) {
            const initPlayer = () => {
                const options = {
                    id: videoId,
                    width: 380,
                    loop: false, // We handle loop via nextStory
                    autoplay: this.autoplay,
                    muted: this.startMuted
                };

                const player = new Vimeo.Player(containerId, options);
                this.player = player;

                player.on('play', () => {
                    if (!this.startMuted) {
                        player.setVolume(1).catch(() => { });
                    }
                });

                player.on('ended', () => {
                    this.nextStory();
                });
            };

            if (window.Vimeo && window.Vimeo.Player) {
                initPlayer();
            } else {
                const checkVimeo = setInterval(() => {
                    if (window.Vimeo && window.Vimeo.Player) {
                        clearInterval(checkVimeo);
                        initPlayer();
                    }
                }, 100);
            }
        }

        createHTMLVideo(url) {
            console.log('Creating HTML Video:', url, 'Autoplay:', this.autoplay, 'Muted:', this.startMuted);
            const video = document.createElement('video');
            video.src = url;
            video.controls = true;
            video.playsInline = true;

            if (this.autoplay) {
                video.autoplay = true;
            }

            // Explicitly set muted property
            video.muted = this.startMuted;

            // HTML5 video loop means repeating same video.
            // If we want Cycle Loop, we need event listener.
            video.addEventListener('ended', () => {
                this.nextStory();
            });

            return video;
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
                // Always enabled in loop mode
                prevBtn.disabled = false;
                nextBtn.disabled = false;
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

            // Cleanup player
            if (this.player) {
                if (typeof this.player.destroy === 'function') {
                    this.player.destroy();
                } else if (typeof this.player.unload === 'function') {
                    this.player.unload();
                }
                this.player = null;
            }

            // Clear video to stop playback
            const videoContainer = this.viewer.querySelector('.cbk-stories-viewer__video-container');
            if (videoContainer) {
                videoContainer.innerHTML = '';
            }

            this.viewer.style.display = 'none';
            document.body.style.overflow = '';
        }
    }

    // Initialize
    new CoffeebrkStoriesViewer();

    // Re-initialize on Elementor frontend init (for live preview)
    if (window.elementorFrontend) {
        window.elementorFrontend.hooks.addAction('frontend/element_ready/coffeebrk_stories.default', function ($scope) {
            new CoffeebrkStoriesViewer();
        });
    }
})();
