<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating a Google Site (title + visibility).
 *
 * @package   block_google_site_creator
 */
class block_google_site_creator_create_site_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];

        $mform->addElement('hidden', 'courseid', $custom['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'title', get_string('sitetitle', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', null, 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('title', 'sitetitle', 'block_google_site_creator');

        $mform->addElement('textarea', 'description', get_string('sitedescription', 'block_google_site_creator'), ['rows' => 4, 'cols' => 60]);
        $mform->setType('description', PARAM_TEXT);
        $mform->addHelpButton('description', 'sitedescription', 'block_google_site_creator');

        $options = [
            'tutors' => get_string('visibility_tutors', 'block_google_site_creator'),
            'unit_group' => get_string('visibility_unit_group', 'block_google_site_creator'),
            'all_oca' => get_string('visibility_all_oca', 'block_google_site_creator'),
        ];
        $mform->addElement('select', 'visibility', get_string('visibility', 'block_google_site_creator'), $options);
        $mform->setType('visibility', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('visibility', 'visibility', 'block_google_site_creator');
        $mform->setDefault('visibility', 'tutors');

        $mform->addElement('filemanager', 'banner', get_string('sitebanner', 'block_google_site_creator'), null, [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]);
        $mform->addHelpButton('banner', 'sitebanner', 'block_google_site_creator');
        if (isset($custom['bannerdraftitemid'])) {
            $mform->setDefault('banner', (int)$custom['bannerdraftitemid']);
        }

        $this->add_action_buttons(true, get_string('save', 'block_google_site_creator'));
    }
}
