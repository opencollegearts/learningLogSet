<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_learninglog\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating and editing learning log posts.
 *
 * @package   mod_learninglog
 */
class post_form extends \moodleform {

    /**
     * Define form fields.
     */
    public function definition() {
        $mform = $this->_form;
        $customdata = $this->_customdata ?? [];

        // Hidden identifiers.
        $mform->addElement('hidden', 'postid');
        $mform->setType('postid', PARAM_INT);

        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('hidden', 'learninglogid');
        $mform->setType('learninglogid', PARAM_INT);

        $mform->addElement('hidden', 'sectionid');
        $mform->setType('sectionid', PARAM_INT);

        // Title.
        $mform->addElement('text', 'title', get_string('posttitle', 'mod_learninglog'), ['size' => 64]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');

        // Content editor.
        $editoroptions = $customdata['editoroptions'] ?? [];
        $mform->addElement('editor', 'content_editor', get_string('postcontent', 'mod_learninglog'), null, $editoroptions);
        $mform->setType('content_editor', PARAM_RAW);

        // Banner image filemanager.
        if (!empty($customdata['banneroptions'])) {
            $mform->addElement(
                'filemanager',
                'bannerfile',
                get_string('bannerimage', 'mod_learninglog'),
                null,
                $customdata['banneroptions']
            );
        }

        // Status selector.
        $statusoptions = [
            'draft' => get_string('statusdraft', 'mod_learninglog'),
            'published' => get_string('statuspublished', 'mod_learninglog'),
            'personalresearch' => get_string('statuspersonalresearch', 'mod_learninglog'),
        ];
        $mform->addElement('select', 'status', get_string('poststatus', 'mod_learninglog'), $statusoptions);
        $mform->setDefault('status', 'draft');

        // Visibility.
        $visibilityoptions = [
            'course' => get_string('visibilitycourse', 'mod_learninglog'),
            'org' => get_string('visibilityorg', 'mod_learninglog'),
            'private' => get_string('visibilityprivate', 'mod_learninglog'),
        ];
        $mform->addElement('select', 'visibility', get_string('postvisibility', 'mod_learninglog'), $visibilityoptions);
        $mform->setDefault('visibility', 'course');

        $mform->addElement('advcheckbox', 'allowcomments', get_string('allowcomments', 'mod_learninglog'), get_string('allowcomments_help', 'mod_learninglog'), null, [0, 1]);
        $mform->setDefault('allowcomments', 1);

        // Category: one selection = section (by default) or user-created category.
        if (!empty($customdata['categoryoptions'])) {
            $mform->addElement(
                'select',
                'category',
                get_string('postcategories', 'mod_learninglog'),
                $customdata['categoryoptions']
            );
        }

        $this->add_action_buttons(true, get_string('savepost', 'mod_learninglog'));
    }
}

