/**
 * Coffeebrk Bento Grid - masonry layout + Load More / Infinite Scroll
 *
 * Real shortest-column masonry (absolute-positioned items, à la Masonry.js/
 * Pinterest) instead of CSS column-count, which only balances by estimated
 * total height and doesn't guarantee items land in the shortest column -
 * that's what caused tall video cards to cluster in the first columns and
 * the layout to shift between reloads. Column count/gap come from CSS
 * custom properties Elementor's responsive controls already set on
 * .cbk-bento-grid, so breakpoints stay editable from the widget panel
 * without duplicating that logic here.
 *
 * Also fetches additional pages from the coffeebrk/v1/bento-grid REST
 * endpoint and appends the returned card markup, then relays out the whole
 * grid - the CSS transition on .cbk-bento-item's transform is what makes
 * that look smooth (existing cards glide to their new spot if they move at
 * all), so there's no need for a separate "only position the new items"
 * code path and its associated stale-state risk.
 */
(function () {
    'use strict';

    function getGridConfig(grid) {
        var style = getComputedStyle(grid);
        var cols = parseInt(style.getPropertyValue('--cbk-bento-columns'), 10);
        var gap = parseFloat(style.getPropertyValue('--cbk-bento-gap'));
        return {
            cols: cols > 0 ? cols : 3,
            gap: isNaN(gap) ? 16 : gap
        };
    }

    function indexOfMin(arr) {
        var min = 0;
        for (var i = 1; i < arr.length; i++) {
            if (arr[i] < arr[min]) min = i;
        }
        return min;
    }

    // Positions every child of `grid` (in DOM order) into the
    // currently-shortest column. Always recomputed from scratch for every
    // item, every call - simpler and safer than trying to extend from a
    // previously-stored column-heights snapshot, and the CSS transition on
    // transform still makes re-running this after an append look smooth.
    function fullLayout(grid) {
        if (!grid) return;

        var items = Array.prototype.slice.call(grid.children);
        var config = getGridConfig(grid);
        var colWidth = (grid.clientWidth - config.gap * (config.cols - 1)) / config.cols;
        var colHeights = new Array(config.cols).fill(0);

        items.forEach(function (item) {
            var shortest = indexOfMin(colHeights);
            var x = shortest * (colWidth + config.gap);
            var y = colHeights[shortest];

            item.style.width = colWidth + 'px';
            item.style.transform = 'translate(' + x + 'px, ' + y + 'px)';

            colHeights[shortest] = y + item.offsetHeight + config.gap;
        });

        grid.style.height = Math.max.apply(null, colHeights.concat(0)) - config.gap + 'px';
        grid.classList.add('cbk-bento-grid--ready');
    }

    function allGrids() {
        return Array.prototype.slice.call(document.querySelectorAll('.cbk-bento-grid'));
    }

    function relayoutAll() {
        allGrids().forEach(fullLayout);
    }

    function debounce(fn, wait) {
        var timer = null;
        return function () {
            clearTimeout(timer);
            timer = setTimeout(fn, wait);
        };
    }

    // Manual escape hatch for content this engine can't predict - e.g. a
    // custom Loop Item template with a widget that resizes itself
    // asynchronously after its own script runs.
    window.cbkBentoGridRelayout = relayoutAll;

    function fetchPage(restUrl, query, page) {
        var params = new URLSearchParams(query);
        params.set('paged', page);

        return fetch(restUrl + '?' + params.toString(), { credentials: 'same-origin' })
            .then(function (res) { return res.json(); });
    }

    function appendHtml(grid, html) {
        var before = new Set(Array.prototype.slice.call(grid.children));
        grid.insertAdjacentHTML('beforeend', html);
        var added = Array.prototype.slice.call(grid.children).filter(function (el) {
            return !before.has(el);
        });

        fullLayout(grid);

        if (window.cbkStoriesViewer && typeof window.cbkStoriesViewer.setupUniversalVideoCards === 'function') {
            window.cbkStoriesViewer.setupUniversalVideoCards();
        }

        if (window.elementorFrontend && window.elementorFrontend.elementsHandler
            && typeof window.elementorFrontend.elementsHandler.runReadyTrigger === 'function') {
            added.forEach(function (el) {
                window.elementorFrontend.elementsHandler.runReadyTrigger(el);
            });
        }
    }

    function initPagination(paginationEl) {
        if (paginationEl.dataset.cbkBound) return;
        paginationEl.dataset.cbkBound = 'true';

        var grid = paginationEl.previousElementSibling;
        if (!grid || !grid.classList.contains('cbk-bento-grid')) return;

        var config = JSON.parse(paginationEl.dataset.settings);
        var currentPage = config.currentPage || 1;
        var done = false;
        var loading = false;

        function loadNext() {
            if (loading || done) return;
            loading = true;
            paginationEl.classList.add('cbk-bento-pagination--loading');

            fetchPage(config.restUrl, config.query, currentPage + 1)
                .then(function (data) {
                    // current_page/has_more come from the server on every
                    // response - treated as authoritative so a stray empty
                    // page can never stall currentPage and cause the same
                    // page number to be re-requested indefinitely.
                    if (data && data.success) {
                        if (data.html) appendHtml(grid, data.html);
                        currentPage = data.current_page;
                        if (!data.has_more) done = true;
                    } else {
                        done = true; // malformed response - stop rather than retry forever
                    }
                })
                .catch(function () { done = true; })
                .then(function () {
                    loading = false;
                    paginationEl.classList.remove('cbk-bento-pagination--loading');

                    if (done) {
                        paginationEl.classList.add('cbk-bento-pagination--done');
                        if (observer) observer.disconnect();
                    }
                });
        }

        var observer = null;

        if (paginationEl.dataset.type === 'load_more') {
            var btn = paginationEl.querySelector('.cbk-bento-load-more');
            if (btn) btn.addEventListener('click', loadNext);
        } else if (paginationEl.dataset.type === 'infinite_scroll' && 'IntersectionObserver' in window) {
            observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) loadNext();
                });
            }, { rootMargin: '400px' });
            observer.observe(paginationEl);
        }
    }

    function init() {
        relayoutAll();
        document.querySelectorAll('.cbk-bento-pagination').forEach(initPagination);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(relayoutAll);
        }

        window.addEventListener('resize', debounce(relayoutAll, 150));
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
