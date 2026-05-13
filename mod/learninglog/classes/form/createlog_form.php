<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_learninglog\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to name the learning log when creating from the block.
 *
 * @package   mod_learninglog
 */
class createlog_form extends \moodleform {

    public function definition() {
        $mform = $this->_form;
        $mform->addElement('hidden', 'courseid');
        $mform->setType('courseid', PARAM_INT);
        $mform->addElement('hidden', 'cmid');
        $mform->setType('cmid', PARAM_INT);
        $mform->addElement('text', 'name', get_string('logname', 'mod_learninglog'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $editoroptions = $this->_customdata['editoroptions'] ?? [];
        $mform->addElement('editor', 'description_editor', get_string('logdescription', 'mod_learninglog'), null, $editoroptions);
        $mform->setType('description_editor', PARAM_RAW);
        $mform->addElement('advcheckbox', 'sitewide', get_string('logsitewide', 'mod_learninglog'), get_string('logsitewide_help', 'mod_learninglog'), null, [0, 1]);
        $mform->setType('sitewide', PARAM_INT);
        if (!empty($this->_customdata['banneroptions'])) {
            $mform->addElement(
                'filemanager',
                'bannerfile',
                get_string('logbannerimage', 'mod_learninglog'),
                null,
                $this->_customdata['banneroptions']
            );
        }

        // Inline category management only when opened from block (course-level).
        $manage_categories = !empty($this->_customdata['manage_categories']);
        $categories = $this->_customdata['categories'] ?? [];
        if ($manage_categories) {
            $mform->addElement('header', 'categoryheader', get_string('categories', 'mod_learninglog'));

            $tablehtml = '<table class="generaltable learninglog-category-table"><thead><tr>' .
                '<th>' . get_string('categoryname', 'mod_learninglog') . '</th>' .
                '<th style="width: 4rem; text-align:center;">' . get_string('delete') . '</th>' .
                '</tr></thead><tbody>';

            foreach ($categories as $cat) {
                $id = (int)$cat->id;
                $name = s($cat->name);
                $readonlydelete = !empty($cat->sectionid);
                $tablehtml .= '<tr>';
                $tablehtml .= '<td><input type="text" name="category_existing[' . $id . ']" value="' . $name .
                    '" class="form-control" /></td>';
                $tablehtml .= '<td style="text-align:center;">';
                if ($readonlydelete) {
                    $tablehtml .= '<span class="text-muted" title="' . get_string('sectionname', 'mod_learninglog') . '">&#x1F512;</span>';
                } else {
                    $tablehtml .= '<button type="submit" name="category_delete[' . $id .
                        ']" value="1" class="btn btn-link text-danger p-0" title="' . get_string('delete') . '">&#x2715;</button>';
                }
                $tablehtml .= '</td>';
                $tablehtml .= '</tr>';
            }

            // A few blank rows for new categories.
            for ($i = 0; $i < 3; $i++) {
                $tablehtml .= '<tr>';
                $tablehtml .= '<td><input type="text" name="category_new[]" value="" class="form-control" /></td>';
                $tablehtml .= '<td></td>';
                $tablehtml .= '</tr>';
            }

            $tablehtml .= '</tbody></table>';
            $mform->addElement('html', $tablehtml);
        }

        $editing = !empty($this->_customdata['editing']);
        $submitlabel = $editing ? get_string('savelearninglog', 'mod_learninglog') : get_string('createlearninglog', 'block_learninglog');
        $this->add_action_buttons(true, $submitlabel);
    }
}
