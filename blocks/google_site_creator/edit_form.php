<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/edit_form.php');

class block_google_site_creator_edit_form extends block_edit_form {
    /**
     * Add per-instance settings shown in "Configure block" form.
     *
     * @param MoodleQuickForm $mform
     */
    protected function specific_definition($mform): void {
        $mform->addElement('header', 'config_header', get_string('blocksettings', 'block'));

        $mform->addElement(
            'text',
            'config_buttonlabel',
            get_string('instancebuttonlabel', 'block_google_site_creator')
        );
        $mform->setType('config_buttonlabel', PARAM_TEXT);
        $mform->setDefault('config_buttonlabel', get_string('creategooglesite', 'block_google_site_creator'));
        $mform->addHelpButton('config_buttonlabel', 'instancebuttonlabel', 'block_google_site_creator');
    }
}
