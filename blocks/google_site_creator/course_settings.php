<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/google_site_creator:managecourse', $context);

$PAGE->set_url(new moodle_url('/blocks/google_site_creator/course_settings.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('coursesettings', 'block_google_site_creator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('coursesettings', 'block_google_site_creator'));

global $DB;

$existing = $DB->get_record('block_google_site_creator_course', ['courseid' => $course->id]);

$form = new \block_google_site_creator\form\course_settings_form(null, [
    'courseid' => $course->id,
    'existing' => $existing,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $record = (object)[
        'courseid' => $course->id,
        'template_id' => trim($data->template_id ?? ''),
        'unit_group_email' => trim($data->unit_group_email ?? ''),
        'timemodified' => time(),
    ];
    if ($existing) {
        $record->id = $existing->id;
        $DB->update_record('block_google_site_creator_course', $record);
    } else {
        $DB->insert_record('block_google_site_creator_course', $record);
    }
    \core\notification::success(get_string('saved', 'core'));
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('coursesettings', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
