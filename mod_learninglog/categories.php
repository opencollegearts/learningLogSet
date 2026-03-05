<?php
// This file is part of Moodle - http://moodle.org/
// Manage current user's custom categories for their learning log.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$cmid = required_param('id', PARAM_INT);

$cm = get_coursemodule_from_id('learninglog', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$learninglog = $DB->get_record('learninglog', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);
require_capability('mod/learninglog:write', context_module::instance($cm->id));

$context = context_module::instance($cm->id);
$PAGE->set_url('/mod/learninglog/categories.php', ['id' => $cm->id]);
$PAGE->set_title(get_string('managecategories', 'mod_learninglog'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$addname = optional_param('addname', '', PARAM_NOTAGS);
$delete = optional_param('delete', 0, PARAM_INT);

if ($delete) {
    $cat = $DB->get_record('learninglog_categories', [
        'id' => $delete,
        'learninglogid' => $learninglog->id,
        'userid' => $USER->id,
    ]);
    if ($cat) {
        $DB->delete_records('learninglog_postcats', ['categoryid' => $cat->id]);
        $DB->delete_records('learninglog_categories', ['id' => $cat->id]);
    }
    redirect(new moodle_url('/mod/learninglog/categories.php', ['id' => $cm->id]));
}

if (trim($addname) !== '') {
    $addname = trim($addname);
    $max = $DB->get_field_sql(
        'SELECT COALESCE(MAX(sortorder), 0) FROM {learninglog_categories} WHERE learninglogid = ? AND userid = ?',
        [$learninglog->id, $USER->id]
    );
    $record = (object)[
        'learninglogid' => $learninglog->id,
        'userid' => $USER->id,
        'parentid' => null,
        'name' => $addname,
        'slug' => null,
        'sortorder' => (int)$max + 1,
        'timecreated' => time(),
        'timemodified' => time(),
    ];
    $DB->insert_record('learninglog_categories', $record);
    redirect(new moodle_url('/mod/learninglog/categories.php', ['id' => $cm->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managecategories', 'mod_learninglog'), 2);

$backurl = new moodle_url('/mod/learninglog/view.php', ['id' => $cm->id]);
echo html_writer::link($backurl, get_string('back')) . '<br><br>';

$categories = $DB->get_records('learninglog_categories', [
    'learninglogid' => $learninglog->id,
    'userid' => $USER->id,
], 'sortorder ASC, name ASC');

echo html_writer::start_tag('ul', ['class' => 'list-unstyled']);
foreach ($categories as $cat) {
    $dellink = new moodle_url('/mod/learninglog/categories.php', ['id' => $cm->id, 'delete' => $cat->id]);
    $dellink = html_writer::link($dellink, get_string('delete'), ['class' => 'text-danger']);
    echo html_writer::tag('li', format_string($cat->name) . ' ' . $dellink);
}
echo html_writer::end_tag('ul');

$form = html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out(false), 'class' => 'mt-3']);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'id', 'value' => $cm->id]);
$form .= html_writer::empty_tag('input', ['type' => 'text', 'name' => 'addname', 'placeholder' => get_string('categoryname', 'mod_learninglog'), 'size' => 30]);
$form .= html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('add')]);
$form .= html_writer::end_tag('form');

echo $form;

echo $OUTPUT->footer();
