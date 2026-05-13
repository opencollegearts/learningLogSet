<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/blocks/google_site_creator/lib.php');

/**
 * Google Site Creator block.
 * Provides a button to create a Google Site from a template (Drive copy) with configurable visibility.
 *
 * @package   block_google_site_creator
 */
class block_google_site_creator extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_google_site_creator');
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'site' => false,
            'my' => false,
        ];
    }

    public function has_config() {
        return true;
    }

    public function instance_allow_config() {
        return true;
    }

    public function get_content() {
        global $COURSE, $PAGE, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($COURSE) || $COURSE->id == SITEID || !isloggedin() || isguestuser()) {
            return $this->content;
        }

        $context = context_course::instance($COURSE->id);
        if (!has_capability('block/google_site_creator:create', $context)) {
            return $this->content;
        }

        $templateid = block_google_site_creator_get_template_id($COURSE->id);
        if (empty($templateid)) {
            $this->content->text = html_writer::div(
                get_string('notemplate', 'block_google_site_creator'),
                'alert alert-warning'
            );
            return $this->content;
        }

        $buttonlabel = !empty($this->config->buttonlabel)
            ? format_string($this->config->buttonlabel)
            : get_string('creategooglesite', 'block_google_site_creator');
        $usersite = block_google_site_creator_get_user_site_for_course($USER->id, $COURSE->id);
        if ($usersite && !empty($usersite->url)) {
            $this->content->text .= html_writer::link(
                new moodle_url($usersite->url),
                get_string('mygooglesite', 'block_google_site_creator'),
                [
                    'class' => 'btn btn-primary w-100',
                    'target' => '_blank',
                    'rel' => 'noopener noreferrer',
                ]
            );
            $this->content->text .= html_writer::div(
                html_writer::link(
                    new moodle_url('/blocks/google_site_creator/edit_site.php', ['courseid' => $COURSE->id, 'siteid' => $usersite->id]),
                    get_string('editsitelisting', 'block_google_site_creator'),
                    ['class' => 'small']
                ),
                'mt-2'
            );
        } else {
            $createurl = new moodle_url('/blocks/google_site_creator/create.php', ['courseid' => $COURSE->id]);
            $this->content->text .= html_writer::link(
                $createurl,
                $buttonlabel,
                ['class' => 'btn btn-primary w-100']
            );
        }

        $footerlinks = [];
        if (has_capability('block/google_site_creator:managecourse', $context)) {
            $footerlinks[] = html_writer::link(
                new moodle_url('/blocks/google_site_creator/course_settings.php', ['courseid' => $COURSE->id]),
                get_string('coursesettings', 'block_google_site_creator'),
                ['class' => 'small']
            );
        }
        if (has_capability('moodle/site:config', context_system::instance())) {
            $footerlinks[] = html_writer::link(
                new moodle_url('/admin/settings.php', ['section' => 'blocksettinggoogle_site_creator']),
                get_string('pluginsettings', 'block_google_site_creator'),
                ['class' => 'small']
            );
        }
        if ($footerlinks) {
            $this->content->footer = html_writer::div(implode(' &nbsp;|&nbsp; ', $footerlinks), 'small');
        }

        return $this->content;
    }
}
