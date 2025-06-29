
/**
 * Above The Fold Link Tracker JavaScript
 *
 * Identifies visible hyperlinks on page load (homepage) and sends them to a WordPress endpoint.
 */
(function() {
    'use strict';

    /**
     * Checks if an element is visible above the fold.
     * An element is considered visible if its top edge is within the initial viewport
     * and it has actual dimensions and is not hidden.
     *
     * @param {Element} el The element to check.
     * @return {boolean} True if visible, false otherwise.
     */
    function isElementAboveTheFold(el) {
        if (!el || typeof el.getBoundingClientRect !== 'function') {
            return false;
        }
        const style = window.getComputedStyle(el);
        if (style.display === 'none' || style.visibility === 'hidden' || parseFloat(style.opacity) === 0 || style.height === '0px' || style.width === '0px') {
            return false;
        }

        const rect = el.getBoundingClientRect();
        const viewportHeight = window.innerHeight || document.documentElement.clientHeight;
        const viewportWidth = window.innerWidth || document.documentElement.clientWidth;


        const visibleVertically = rect.top < viewportHeight && rect.bottom > 0;


        const visibleHorizontally = rect.left < viewportWidth && rect.right > 0;
        const startsAboveFold = rect.top < viewportHeight;

        return visibleVertically && visibleHorizontally && startsAboveFold && rect.height > 0 && rect.width > 0;
    }

    /**
     * Gathers visible links and sends them via AJAX.
     */
    function trackVisibleLinks() {
        if (typeof atfLinkTracker === 'undefined' || !atfLinkTracker.ajax_url || !atfLinkTracker.action || !atfLinkTracker.nonce) {
            console.error('[ATF FATAL] Tracker settings (atfLinkTracker) not found.');
            return;
        }


        // console.log('[ATF] Starting link tracking...');
        const links = document.querySelectorAll('a[href]');
        const visibleLinksData = [];
        const screenWidth = window.screen.width;
        const screenHeight = window.screen.height;

        links.forEach(function(link) {
            const hrefAttr = link.getAttribute('href');
            // console.log('[ATF Debug] Processing link:', link.href);

            // Rule 1: Exclude links from the WP Admin Bar.
            if (link.closest('#wpadminbar, #query-monitor')) {                // console.log('[ATF Debug] -> SKIPPED: Part of admin/debug bar.');
                return;
            }

            // Rule 2: Exclude links from common error, warning, or notice containers.
            // This is a best-effort approach to ignore notices without being too restrictive.
            if (link.closest('.notice, .error, .warning, .updated, .notice-error, .notice-warning, .notice-info, .notice-success, .xdebug-error, .php-error')) {
                // console.log('[ATF Debug] -> SKIPPED: Inside a notice/error container.');
                return;
            }

            // Rule 3: Ignore empty hrefs, javascript:, some fragments, and tel/mailto links.

            // Links with href="#" are now tracked as requested.
            if (!hrefAttr || hrefAttr.trim() === '' || hrefAttr.trim().toLowerCase().startsWith('javascript:') || (hrefAttr.startsWith('#') && hrefAttr.length > 1) || hrefAttr.trim().toLowerCase().startsWith('tel:') || hrefAttr.trim().toLowerCase().startsWith('mailto:')) {
                // console.log('[ATF Debug] -> SKIPPED: Invalid or non-trackable href attribute.');
                return;
            }

            // Rule 4: Check if the element is actually visible above the fold.
            if (!isElementAboveTheFold(link)) {
                // console.log('[ATF Debug] -> SKIPPED: Not visible above the fold.');
                return;
            }

            let linkText = (link.innerText || link.textContent || '').trim();
            if (!linkText) {
                const img = link.querySelector('img[alt]');
                if (img && img.alt) {
                    linkText = `Image: ${img.alt.trim()}`;
                }
            }
            if (!linkText && link.getAttribute('aria-label')) {
                linkText = link.getAttribute('aria-label').trim();
            }            if (!linkText || linkText.length === 0) {
                linkText = '[No discernible text]';
            }

            // console.log(`[ATF Debug] -> TRACKED. Text: "${linkText}"`);
            visibleLinksData.push({
                url: link.href,
                text: linkText.substring(0, 500)
            });
        });

        // console.log(`[ATF] Found ${visibleLinksData.length} valid links to track.`);

        if (visibleLinksData.length > 0) {
            // console.log('[ATF] Final data being sent to server:', visibleLinksData);
            const formData = new FormData();
            formData.append('action', atfLinkTracker.action);
            formData.append('nonce', atfLinkTracker.nonce);
            formData.append('screen_width', screenWidth);
            formData.append('screen_height', screenHeight);

            visibleLinksData.forEach(function(linkData, index) {
                formData.append('links[' + index + '][url]', linkData.url);
                formData.append('links[' + index + '][text]', linkData.text);
            });

            fetch(atfLinkTracker.ajax_url, {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    console.log('ATF Links Tracked:', result.data.message);
                } else {
                    console.error('ATF Tracking Error:', (result.data && result.data.message) ? result.data.message : 'Unknown error.');
                }            })
            .catch(error => {
                console.error('ATF AJAX Error:', error);
            });        }
    }

    // IMPORTANT: Changed from DOMContentLoaded to 'load'.

    // This waits for all resources (images, scripts) to load, giving the theme    // a chance to calculate final element sizes and positions.
    window.addEventListener('load', trackVisibleLinks);

})();