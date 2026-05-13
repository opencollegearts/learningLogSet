/**
 * Frontend behaviour for Blog layouts blocks.
 *
 * This module looks for .tiny-bloglayout-wrapper elements and, depending on
 * the layout type, initialises a lightbox or slider where the host page has
 * loaded the relevant libraries (e.g. GLightbox, Swiper), or a built-in
 * minimal lightbox for grid images.
 */

/**
 * Simple lightbox: show one image in an overlay; click or Escape to close.
 * The caption is taken from the nearest .tbl-image-caption, so it works even
 * if data attributes are stripped and alt is empty.
 *
 * @param {HTMLElement} wrapper The layout grid wrapper element.
 */
const initSimpleLightbox = (wrapper) => {
    let images = wrapper.querySelectorAll('.tbl-image-item img');
    if (!images.length) {
        images = wrapper.querySelectorAll('img[data-fullres]');
    }
    if (!images.length) {
        return;
    }

    let overlay = null;

    const close = () => {
        if (overlay && overlay.parentNode) {
            overlay.parentNode.removeChild(overlay);
            overlay = null;
        }
        document.removeEventListener('keydown', onKeyDown);
    };

    const onKeyDown = (e) => {
        if (e.key === 'Escape') {
            close();
        }
    };

    const show = (src, caption) => {
        close();
        overlay = document.createElement('div');
        overlay.className = 'tiny-bloglayout-lightbox';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-label', caption || 'Enlarged image');

        const backdrop = document.createElement('div');
        backdrop.className = 'tiny-bloglayout-lightbox-backdrop';
        const content = document.createElement('div');
        content.className = 'tiny-bloglayout-lightbox-content';
        const fullImg = document.createElement('img');
        fullImg.src = src;
        fullImg.alt = caption || '';
        content.appendChild(fullImg);

        if (caption) {
            const captionEl = document.createElement('div');
            captionEl.className = 'tiny-bloglayout-lightbox-caption';
            captionEl.textContent = caption;
            content.appendChild(captionEl);
        }
        overlay.appendChild(backdrop);
        overlay.appendChild(content);

        backdrop.addEventListener('click', close);
        fullImg.addEventListener('click', (e) => e.stopPropagation());
        overlay.addEventListener('click', (e) => {
            if (e.target === overlay) {
                close();
            }
        });
        document.addEventListener('keydown', onKeyDown);
        document.body.appendChild(overlay);
    };

    images.forEach((img) => {
        img.style.cursor = 'pointer';
        img.addEventListener('click', (e) => {
            e.preventDefault();
            const full = img.getAttribute('data-fullres') || img.src;

            let caption = '';
            const item = img.closest('.tbl-image-item');
            if (item) {
                const captionEl = item.querySelector('.tbl-image-caption');
                if (captionEl) {
                    caption = captionEl.textContent.trim();
                }
            }

            show(full, caption);
        });
    });
};

/**
 * Find layout grid wrappers: by class (if preserved) or by structure (div containing
 * multiple divs each with one img[data-fullres]), in case format_text stripped classes.
 *
 * @returns {NodeList|HTMLElement[]} Wrapper elements for grid layouts.
 */
const getGridWrappers = () => {
    const byClass = document.querySelectorAll('.tiny-bloglayout-wrapper.layout-grid');
    if (byClass.length) {
        return byClass;
    }
    const wrappers = new Set();
    document.querySelectorAll('img[data-fullres]').forEach((img) => {
        const itemDiv = img.parentElement;
        const wrapper = itemDiv && itemDiv.parentElement;
        if (!wrapper || wrapper.children.length < 2) {
            return;
        }
        const siblings = Array.from(wrapper.children);
        const allHaveOneImg = siblings.every((c) =>
            c.children.length === 1 && c.querySelector('img[data-fullres]'));
        if (allHaveOneImg) {
            wrappers.add(wrapper);
        }
    });
    return Array.from(wrappers);
};

export const init = () => {
    try {
        const gridWrappers = getGridWrappers();
        gridWrappers.forEach((wrapper) => {
            initSimpleLightbox(wrapper);
        });

        const sliderWrappers = document.querySelectorAll('.tiny-bloglayout-wrapper.layout-slider');
        sliderWrappers.forEach((wrapper) => {
            if (window.Swiper) {
                // Expect the theme or parent plugin to have included Swiper CSS/JS.
                // Wrap images in the required Swiper markup.
                const container = document.createElement('div');
                container.classList.add('swiper', 'tiny-bloglayout-swiper');
                const wrapperEl = document.createElement('div');
                wrapperEl.classList.add('swiper-wrapper');

                wrapper.querySelectorAll('img').forEach((img) => {
                    const slide = document.createElement('div');
                    slide.classList.add('swiper-slide');

                    const inner = document.createElement('div');
                    inner.classList.add('tiny-bloglayout-slide-inner');

                    let captionText = '';
                    const item = img.closest('.tbl-image-item');
                    if (item) {
                        const captionEl = item.querySelector('.tbl-image-caption');
                        if (captionEl) {
                            captionText = captionEl.textContent.trim();
                        }
                    }

                    if (captionText) {
                        const captionEl = document.createElement('div');
                        captionEl.className = 'tiny-bloglayout-caption';
                        captionEl.textContent = captionText;
                        inner.appendChild(captionEl);
                    }

                    inner.appendChild(img.cloneNode(true));
                    slide.appendChild(inner);
                    wrapperEl.appendChild(slide);
                });

                container.appendChild(wrapperEl);
                wrapper.innerHTML = '';
                wrapper.appendChild(container);

                // eslint-disable-next-line no-new
                new window.Swiper(container, {
                    loop: true,
                });
            }
        });
    } catch (e) {
        // Avoid breaking the page if run in an unexpected context (e.g. editor iframe).
        if (typeof window.console !== 'undefined' && window.console.warn) {
            window.console.warn('tiny_bloglayouts/frontend init:', e);
        }
    }
};

