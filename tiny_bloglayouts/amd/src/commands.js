import {getButtonImage} from 'editor_tiny/utils';
import {get_string as getString} from 'core/str';
import {
    component,
    insertLayoutButtonName,
    insertLayoutMenuItemName,
    icon,
} from './common';
import {openLayoutDialog} from './ui';

/**
 * Handle activation of the Insert layout action.
 *
 * @param {TinyMCE.editor} editor
 */
const handleAction = (editor) => {
    openLayoutDialog(editor);
};

/**
 * Return a setup function which registers toolbar and menu items.
 *
 * @returns {function(TinyMCE.editor): void}
 */
export const getSetup = async() => {
    const [
        buttonTitle,
        menuTitle,
        buttonImage,
    ] = await Promise.all([
        getString('button_insertlayout', component),
        getString('menuitem_insertlayout', component),
        getButtonImage('icon', component),
    ]);

    return (editor) => {
        editor.ui.registry.addIcon(icon, buttonImage.html);

        editor.ui.registry.addButton(insertLayoutButtonName, {
            icon,
            tooltip: buttonTitle,
            onAction: () => handleAction(editor),
        });

        editor.ui.registry.addMenuItem(insertLayoutMenuItemName, {
            icon,
            text: menuTitle,
            onAction: () => handleAction(editor),
        });
    };
};

