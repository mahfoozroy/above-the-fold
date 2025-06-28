
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
            // console.error('ATF Error: Tracker settings (atfLinkTracker) not properly localized by WordPress.');
            return;
        }        const links = document.querySelectorAll('a[href]');
        const visibleLinksData = [];
        const screenWidth = window.screen.width;
        const screenHeight = window.screen.height;



        links.forEach(function(link) {
            // Exclude links from the WP Admin Bar.
            if (link.closest('#wpadminbar')) {
                return;
            }

            // Exclude links from common error, warning, or notice containers.
            if (link.closest('.notice, .error, .warning, .updated, .notice-error, .notice-warning, .notice-info, .notice-success')) {
                return;
            }

            const hrefAttr = link.getAttribute('href');            // Log the raw attribute and the resolved .href property
            // console.log('ATF Debug: Processing link. Attribute href:', hrefAttr, 'Resolved link.href:', link.href, 'Element:', link);

            // Ignore empty hrefs, javascript:void(0), same-page fragments, and tel/mailto links
            if (!hrefAttr || hrefAttr.trim() === '' || hrefAttr.trim() === '#' ||
                hrefAttr.trim().toLowerCase().startsWith('javascript:') ||
                (hrefAttr.startsWith('#') && hrefAttr.length > 1) ||
                hrefAttr.trim().toLowerCase().startsWith('tel:') ||
                hrefAttr.trim().toLowerCase().startsWith('mailto:')) {
                return;
            }

            if (isElementAboveTheFold(link)) {
                // console.log('ATF Debug: Link is ATF:', link.href, 'Element:', link);
                let linkText = (link.innerText || link.textContent || '').trim();
                if (!linkText) {
                    const img = link.querySelector('img[alt]');
                    if (img && img.alt) {
                        linkText = img.alt.trim();
                    }
                }
                if (!linkText && link.getAttribute('aria-label')) {
                    linkText = link.getAttribute('aria-label').trim();
                }
                if (!linkText) {
                    // Try to get text from a child span or div if common
                    const commonTextContainers = link.querySelectorAll('span, div');
                    for (let i = 0; i < commonTextContainers.length; i++) {                        const containerText = (commonTextContainers[i].innerText || commonTextContainers[i].textContent || '').trim();
                        if (containerText) {
                            linkText = containerText;
                            break;
                        }
                    }
                }
                if (!linkText) {
                    linkText = '[No discernible text]';
                }
                visibleLinksData.push({
                    url: link.href, // Absolute URL
                    text: linkText.substring(0, 500) // Limit text length
                });
            }
        });

        if (visibleLinksData.length > 0) {
            const formData = new FormData();
            formData.append('action', atfLinkTracker.action);
            formData.append('nonce', atfLinkTracker.nonce);
            formData.append('screen_width', screenWidth);
            formData.append('screen_height', screenHeight);

            visibleLinksData.forEach(function(linkData, index) {                formData.append('links[' + index + '][url]', linkData.url);
                formData.append('links[' + index + '][text]', linkData.text);
            });

            fetch(atfLinkTracker.ajax_url, {
                method: 'POST',
                body: formData // FormData sets the Content-Type header automatically to multipart/form-data
            })
            .then(function(response) {
                if (!response.ok) {
                    // Attempt to get text for more error details if JSON parsing fails
                    return response.text().then(text => {
                        let errorMessage = 'Server responded with status ' + response.status;
                        try {
                            const errorData = JSON.parse(text);
                            if (errorData && errorData.data && errorData.data.message) {
                                errorMessage += '. Message: ' + errorData.data.message;
                            } else if (text) {
                                errorMessage += '. Response: ' + text.substring(0, 200); // Log part of the non-JSON response
                            }
                        } catch (e) {
                            // If parsing as JSON fails, text is not JSON or empty.
                            errorMessage += '. Response body was not valid JSON: ' + text.substring(0, 200);
                        }
                        throw new Error(errorMessage);
                    });
                }
                return response.json();
            })
            .then(function(result) {
                if (result.success) {
                    // console.log('ATF Links Tracked:', result.data.message, 'Visit ID:', result.data.visit_id);                } else {
                    // console.error('ATF Tracking Error (success:false):', (result.data && result.data.message) ? result.data.message : 'Unknown error from server.');
                }
            })
            .catch(function(error) {
                // console.error('ATF AJAX Communication Error:', error.message);
            });
        } else {
            // console.log('ATF: No links found above the fold or all links were invalid.');
        }
    }

    // Ensure the DOM is fully loaded before running the script
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', trackVisibleLinks);
    } else {
        // DOMContentLoaded has already fired
        trackVisibleLinks();
    }

})();