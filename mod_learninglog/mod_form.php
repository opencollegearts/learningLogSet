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

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Activity configuration form for mod_learninglog.
 *
 * @package   mod_learninglog
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_learninglog_mod_form extends moodleform_mod {

    /**
     * Defines the form fields.
     */
    public function definition() {
        $mform = $this->_form;

        // Name.
        $mform->addElement('text', 'name', get_string('name', 'mod_learninglog'), ['size' => 64]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');

        // Intro / description.
        $this->standard_intro_elements();

        // Completion settings: target number of posts.
        $mform->addElement('text', 'completionpoststarget', get_string('completionpoststarget', 'mod_learninglog'));
        $mform->setType('completionpoststarget', PARAM_INT);
        $mform->setDefault('completionpoststarget', 0);

        // AI enable toggle (instance-level).
        $mform->addElement('advcheckbox', 'enableai', get_string('enableai', 'mod_learninglog'));
        $mform->setDefault('enableai', 1);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }
}

