import {get_string as getString} from 'core/str';
import Notification from 'core/notification';
import {component} from './common';
import {openFilePicker} from './filepicker';

/**
 * Build the HTML block for a chosen layout and list of images.
 *
 * @param {string} layoutType
 * @param {Array} images
 * @returns {string}
 */
const buildLayoutHtml = (layoutType, images) => {
    if (!images.length) {
        return '';
    }

    const layoutClass = layoutType === 'slider' ? 'layout-slider' : 'layout-grid';

    const itemsHtml = images.map((img) => {
        const src = img.url;
        const alt = img.name || '';
        const full = img.url;

        return `
            <div class="tbl-image-item">
                <img src="${src}" alt="${alt}" data-fullres="${full}">
            </div>
        `;
    }).join('');

    return `
        <div class="tiny-bloglayout-wrapper ${layoutClass}" contenteditable="false">
            ${itemsHtml}
        </div>
    `;
};

/**
 * Extract the current block data if the selection is inside an existing layout.
 *
 * @param {TinyMCE.editor} editor
 * @returns {{layoutType: string, images: Array}}
 */
const getExistingLayoutData = (editor) => {
    const node = editor.selection.getNode();
    const wrapper = editor.dom.getParent(node, '.tiny-bloglayout-wrapper');
    if (!wrapper) {
        return {
            layoutType: 'grid',
            images: [],
        };
    }

    const isSlider = editor.dom.hasClass(wrapper, 'layout-slider');
    const images = Array.from(wrapper.querySelectorAll('img')).map((img) => ({
        url: img.getAttribute('src'),
        name: img.getAttribute('alt') || '',
    }));

    return {
        layoutType: isSlider ? 'slider' : 'grid',
        images,
    };
};

/**
 * Open the TinyMCE dialog for inserting/editing a layout.
 *
 * @param {TinyMCE.editor} editor
 */
export const openLayoutDialog = async(editor) => {
    const [
        dialogTitle,
        layoutFieldLabel,
        layoutGridLabel,
        layoutSliderLabel,
        imagesFieldLabel,
        manageImagesLabel,
        insertLabel,
        updateLabel,
    ] = await Promise.all([
        getString('dialog_title', component),
        getString('field_layouttype', component),
        getString('layouttype_grid', component),
        getString('layouttype_slider', component),
        getString('field_images', component),
        getString('button_manageimages', component),
        getString('button_insert', component),
        getString('button_update', component),
    ]);

    const initial = getExistingLayoutData(editor);
    const isEditing = !!editor.dom.getParent(editor.selection.getNode(), '.tiny-bloglayout-wrapper');

    const dialogConfig = {
        title: dialogTitle,
        size: 'normal',
        body: {
            type: 'panel',
            items: [
                {
                    type: 'selectbox',
                    name: 'layouttype',
                    label: layoutFieldLabel,
                    items: [
                        {value: 'grid', text: layoutGridLabel},
                        {value: 'slider', text: layoutSliderLabel},
                    ],
                },
                {
                    type: 'htmlpanel',
                    name: 'imagesummary',
                    html: `<p class="tbl-images-summary">${imagesFieldLabel} (${initial.images.length} selected)</p>`,
                },
                {
                    type: 'button',
                    name: 'manageimages',
                    text: manageImagesLabel,
                },
            ],
        },
        initialData: {
            layouttype: initial.layoutType,
        },
        buttons: [
            {
                type: 'cancel',
                name: 'cancel',
                text: 'Cancel',
            },
            {
                type: 'submit',
                name: 'submit',
                text: isEditing ? updateLabel : insertLabel,
                primary: true,
            },
        ],
        onAction: async(api, details) => {
            if (details.name === 'manageimages') {
                const currentImages = initial.images;
                document.body.classList.add('tiny-bloglayout-filepicker-open');
                try {
                    const picked = await openFilePicker(editor, currentImages);
                    initial.images = picked;
                // Update the "X selected" count in the dialog (htmlpanel does not bind to setData).
                const newSummaryHtml = `${imagesFieldLabel} (${picked.length} selected)`;
                try {
                    api.setData({imagesummary: newSummaryHtml});
                } catch (e) {
                    // Ignore.
                }
                const summaryEl = document.querySelector('.tox-dialog .tbl-images-summary');
                if (summaryEl) {
                    summaryEl.textContent = newSummaryHtml;
                }
                if (picked.length > currentImages.length) {
                    Notification.addNotification({
                        message: picked.length === 1 ? 'Image added.' : `${picked.length} images selected.`,
                        type: 'success',
                    });
                }
                } finally {
                    document.body.classList.remove('tiny-bloglayout-filepicker-open');
                }
            }
        },
        onSubmit: (api) => {
            const data = api.getData();
            const html = buildLayoutHtml(data.layouttype, initial.images);
            if (!html) {
                api.close();
                return;
            }

            const wrapper = editor.dom.getParent(editor.selection.getNode(), '.tiny-bloglayout-wrapper');
            if (wrapper) {
                wrapper.outerHTML = html;
            } else {
                editor.insertContent(html);
            }

            api.close();
        },
    };

    editor.windowManager.open(dialogConfig);
};

