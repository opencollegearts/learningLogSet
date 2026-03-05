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
        $editing = !empty($this->_customdata['editing']);
        $submitlabel = $editing ? get_string('savelearninglog', 'mod_learninglog') : get_string('createlearninglog', 'block_learninglog');
        $this->add_action_buttons(true, $submitlabel);
    }
}
