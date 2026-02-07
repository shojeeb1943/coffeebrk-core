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

            this.init();
        }

        init() {
            // Wait for DOM to be ready
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.bindEvents());
            } else {
                this.bindEvents();
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
            this.autoplay = container.dataset.autoplay === 'true';
            this.loop = container.dataset.loop === 'true';

            cards.forEach((card, index) => {
                card.addEventListener('click', () => {
                    this.openViewer(container, index);
                });
            });
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
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--prev"></div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--current">
                        <div class="cbk-stories-viewer__video-container"></div>
                    </div>
                    <div class="cbk-stories-viewer__item cbk-stories-viewer__item--next"></div>
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
        }

        showStory(index) {
            if (index < 0 || index >= this.stories.length) {
                return;
            }

            this.currentIndex = index;
            const story = this.stories[index];
            const videoUrl = story.dataset.videoUrl;
            const videoContainer = this.viewer.querySelector('.cbk-stories-viewer__video-container');

            // Clear previous content
            videoContainer.innerHTML = '';

            // Detect video type and create appropriate element
            const videoElement = this.createVideoElement(videoUrl);
            if (videoElement) {
                videoContainer.appendChild(videoElement);
            }

            // Update Side Items
            this.updateSideItems(index);

            // Update navigation state
            this.updateNavigation();
        }

        getThumbnailUrl(index) {
            if (index < 0 || index >= this.stories.length) return null;
            const thumb = this.stories[index].querySelector('.cbk-stories__thumbnail');
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
            const prevItem = this.viewer.querySelector('.cbk-stories-viewer__item--prev');
            const nextItem = this.viewer.querySelector('.cbk-stories-viewer__item--next');

            if (prevItem) {
                const prevUrl = this.getThumbnailUrl(index - 1);
                if (prevUrl) {
                    prevItem.style.backgroundImage = `url('${prevUrl}')`;
                    prevItem.style.opacity = '0.4';
                    prevItem.style.pointerEvents = 'auto';
                } else {
                    prevItem.style.backgroundImage = 'none';
                    prevItem.style.opacity = '0';
                    prevItem.style.pointerEvents = 'none';
                }
            }

            if (nextItem) {
                const nextUrl = this.getThumbnailUrl(index + 1);
                if (nextUrl) {
                    nextItem.style.backgroundImage = `url('${nextUrl}')`;
                    nextItem.style.opacity = '0.4';
                    nextItem.style.pointerEvents = 'auto';
                } else {
                    nextItem.style.backgroundImage = 'none';
                    nextItem.style.opacity = '0';
                    nextItem.style.pointerEvents = 'none';
                }
            }
        }

        createVideoElement(url) {
            if (!url) {
                return this.createPlaceholder();
            }

            // YouTube detection
            const youtubeMatch = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
            if (youtubeMatch) {
                return this.createYouTubeEmbed(youtubeMatch[1]);
            }

            // Vimeo detection
            const vimeoMatch = url.match(/(?:vimeo\.com\/)(\d+)/);
            if (vimeoMatch) {
                return this.createVimeoEmbed(vimeoMatch[1]);
            }

            // Assume it's a direct video URL
            return this.createHTMLVideo(url);
        }

        createYouTubeEmbed(videoId) {
            const iframe = document.createElement('iframe');
            let src = `https://www.youtube.com/embed/${videoId}?rel=0&modestbranding=1`;

            if (this.autoplay) {
                src += '&autoplay=1';
            }
            if (this.loop) {
                src += `&loop=1&playlist=${videoId}`;
            }

            iframe.src = src;
            iframe.allow = 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture';
            iframe.allowFullscreen = true;

            return iframe;
        }

        createVimeoEmbed(videoId) {
            const iframe = document.createElement('iframe');
            let src = `https://player.vimeo.com/video/${videoId}?`;

            if (this.autoplay) {
                src += 'autoplay=1&';
            }
            if (this.loop) {
                src += 'loop=1&';
            }

            iframe.src = src;
            iframe.allow = 'autoplay; fullscreen; picture-in-picture';
            iframe.allowFullscreen = true;

            return iframe;
        }

        createHTMLVideo(url) {
            const video = document.createElement('video');
            video.src = url;
            video.controls = true;
            video.playsInline = true;

            if (this.autoplay) {
                video.autoplay = true;
                video.muted = true; // Muted is required for autoplay in most browsers
            }
            if (this.loop) {
                video.loop = true;
            }

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

            prevBtn.disabled = this.currentIndex === 0;
            nextBtn.disabled = this.currentIndex === this.stories.length - 1;
        }

        prevStory() {
            if (this.currentIndex > 0) {
                this.showStory(this.currentIndex - 1);
            }
        }

        nextStory() {
            if (this.currentIndex < this.stories.length - 1) {
                this.showStory(this.currentIndex + 1);
            }
        }

        closeViewer() {
            if (!this.viewer) return;

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
