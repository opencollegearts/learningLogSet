<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');

$courseid = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);

$PAGE->set_url('/mod/learninglog/index.php', ['id' => $course->id]);
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_learninglog'));

// Simple table listing all learning log instances in the course.
if (!$cms = get_coursemodules_in_course('learninglog', $course->id)) {
    echo $OUTPUT->notification(get_string('noinstances', 'mod_learninglog'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [get_string('name'), get_string('sectionname', 'mod_learninglog')];

foreach ($cms as $cm) {
    $link = html_writer::link(
        new moodle_url('/mod/learninglog/view.php', ['id' => $cm->id]),
        format_string($cm->name)
    );
    $sectionname = get_section_name($course, $cm->section);
    $table->data[] = [$link, s($sectionname)];
}

echo html_writer::table($table);

echo $OUTPUT->footer();

