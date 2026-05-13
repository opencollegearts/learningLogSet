<?php
// This file is part of Moodle - http://moodle.org/

namespace block_google_site_creator\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Course-level settings: unit template ID and Unit Group email.
 *
 * @package   block_google_site_creator
 */
class course_settings_form extends \moodleform {

    protected function definition() {
        $mform = $this->_form;
        $custom = $this->_customdata ?? [];
        $existing = $custom['existing'] ?? null;

        $mform->addElement('hidden', 'courseid', $custom['courseid'] ?? 0);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('text', 'template_id', get_string('coursetemplate', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('template_id', PARAM_TEXT);
        $mform->addHelpButton('template_id', 'coursetemplate', 'block_google_site_creator');
        if ($existing && $existing->template_id !== null) {
            $mform->setDefault('template_id', $existing->template_id);
        }

        $mform->addElement('text', 'unit_group_email', get_string('unitgroup', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('unit_group_email', PARAM_TEXT);
        $mform->addHelpButton('unit_group_email', 'unitgroup', 'block_google_site_creator');
        if ($existing && $existing->unit_group_email !== null) {
            $mform->setDefault('unit_group_email', $existing->unit_group_email);
        }

        $this->add_action_buttons(true, get_string('savechanges'));
    }
}
