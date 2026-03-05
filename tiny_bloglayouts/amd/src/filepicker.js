import Notification from 'core/notification';
import {displayFilepicker} from 'editor_tiny/utils';

/**
 * Open Moodle's file picker using the same API as the Tiny image/media plugins.
 * Uses editor_tiny/utils displayFilepicker(editor, 'image') which reads the
 * editor's filepicker options (from the form's draft area) and opens the repository picker.
 * Returns currentImages plus the newly selected image (if any).
 *
 * @param {TinyMCE.editor} editor The TinyMCE editor instance.
 * @param {Array} currentImages Existing list of {url, name}.
 * @returns {Promise<Array>} Resolves with updated images array (new image appended, or currentImages if cancelled).
 */
export const openFilePicker = async(editor, currentImages = []) => {
    try {
        const params = await displayFilepicker(editor, 'image');
        if (params && params.url) {
            const name = params.title ?? '';
            return currentImages.concat([{url: params.url, name}]);
        }
        return currentImages;
    } catch (e) {
        Notification.addNotification({
            message: 'File picker is not available in this editor.',
            type: 'warning',
        });
        return currentImages;
    }
};
