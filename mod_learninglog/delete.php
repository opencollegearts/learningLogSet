<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

if ($id) {
    $cm = get_coursemodule_from_id('learninglog', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
    $viewparams = ['id' => $id];
} elseif ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course, true);
    $context = context_course::instance($course->id);
    $viewparams = ['courseid' => $course->id];
} else {
    throw new moodle_exception('missingparam', 'error', '', 'id or courseid');
}

require_capability('mod/learninglog:write', $context);

$post = $DB->get_record('learninglog_posts', [
    'id' => $postid,
    'userid' => $USER->id,
    'courseid' => $course->id,
], '*', MUST_EXIST);

$PAGE->set_url('/mod/learninglog/delete.php', $viewparams + ['postid' => $postid]);
$PAGE->set_title(get_string('deletepost', 'mod_learninglog'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

if ($confirm && confirm_sesskey()) {
    $postcontext = learninglog_get_context_for_post($post, $course);
    $fs = get_file_storage();
    $fs->delete_area_files($postcontext->id, 'mod_learninglog', 'banner', $postid);
    $fs->delete_area_files($postcontext->id, 'mod_learninglog', 'embedded', $postid);
    $DB->delete_records('learninglog_postcats', ['postid' => $postid]);
    if ($DB->get_manager()->table_exists('learninglog_comments')) {
        $DB->delete_records('learninglog_comments', ['postid' => $postid]);
    }
    $DB->delete_records('learninglog_posts', ['id' => $postid]);
    redirect(new moodle_url('/mod/learninglog/view.php', $viewparams), get_string('postdeleted', 'mod_learninglog'));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('deletepost', 'mod_learninglog'), 2);
echo $OUTPUT->confirm(
    get_string('deletepostconfirm', 'mod_learninglog', format_string($post->title)),
    new moodle_url('/mod/learninglog/delete.php', $viewparams + ['postid' => $postid, 'confirm' => 1]),
    new moodle_url('/mod/learninglog/view.php', $viewparams + ['postid' => $postid])
);
echo $OUTPUT->footer();
