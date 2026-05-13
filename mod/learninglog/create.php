<?php
// This file is part of Moodle - http://moodle.org/
// Create (and name) the current user's learning log for the course. Called from the block.

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

use mod_learninglog\form\createlog_form;

$courseid = optional_param('courseid', 0, PARAM_INT);
$cmid = optional_param('id', 0, PARAM_INT);

$course = null;
$cm = null;
$learninglog = null;

if ($cmid) {
    $cm = get_coursemodule_from_id('learninglog', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $learninglog = $DB->get_record('learninglog', ['id' => $cm->instance], '*', MUST_EXIST);
} elseif ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
} else {
    throw new \moodle_exception('missingparam', 'error', '', 'courseid or id');
}

require_login($course, true);
if ($cm) {
    require_capability('mod/learninglog:write', context_module::instance($cm->id));
}
$context = $cm ? context_module::instance($cm->id) : context_course::instance($course->id);

$learninglogid = $learninglog ? $learninglog->id : null;
$userlog = learninglog_get_user_log($USER->id, $course->id);

$editoroptions = [
    'context' => $context,
    'maxfiles' => 0,
    'maxbytes' => 0,
    'trusttext' => false,
    'changeformat' => false,
];
$banneroptions = [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $course->maxbytes,
    'accepted_types' => ['image'],
];
$coursecontext = context_course::instance($course->id);
$manage_categories = ($courseid && !$cmid);
$customdata = [
    'editoroptions' => $editoroptions,
    'banneroptions' => $banneroptions,
    'editing' => !empty($userlog),
    'manage_categories' => $manage_categories,
    'categories' => [],
];

// Load categories for inline management only when opened from block (course-level).
if ($manage_categories) {
    learninglog_ensure_section_categories_for_course($USER->id, $course->id);
    $customdata['categories'] = $DB->get_records_select(
        'learninglog_categories',
        'userid = :userid AND courseid = :courseid AND learninglogid IS NULL',
        ['userid' => $USER->id, 'courseid' => $course->id],
        'sortorder ASC, name ASC'
    );
}
$mform = new createlog_form(null, $customdata, 'post', '', ['class' => 'createlog-form']);
$defaults = (object)[
    'courseid' => $course->id,
    'cmid' => $cmid,
];
if ($userlog) {
    $defaults->name = $userlog->name;
    $defaults->sitewide = !empty($userlog->sitewide);
    $draftitemid = file_get_submitted_draft_itemid('bannerfile');
    file_prepare_draft_area($draftitemid, $coursecontext->id, 'mod_learninglog', 'logbanner', $userlog->id, $banneroptions);
    $defaults->bannerfile = $draftitemid;
} else {
    $defaults->bannerfile = file_get_submitted_draft_itemid('bannerfile');
}
$defaults->description_editor = [
    'text' => ($userlog && property_exists($userlog, 'description')) ? $userlog->description : '',
    'format' => FORMAT_HTML,
];
$mform->set_data($defaults);

if ($mform->is_cancelled()) {
    if ($cmid) {
        redirect(new moodle_url('/mod/learninglog/view.php', ['id' => $cmid]));
    }
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $mform->get_data()) {
    $name = trim($data->name);
    if ($name === '') {
        $name = get_string('modulename', 'mod_learninglog');
    }
    $description = isset($data->description_editor['text']) ? $data->description_editor['text'] : null;
    $sitewide = !empty($data->sitewide);
    $userlog = learninglog_create_user_log($USER->id, $course->id, $name, $description, $learninglogid, $sitewide);
    if (!empty($data->bannerfile)) {
        file_save_draft_area_files(
            $data->bannerfile,
            $coursecontext->id,
            'mod_learninglog',
            'logbanner',
            $userlog->id,
            $banneroptions
        );
    }

    // Process inline category edits when opened from block (course-level only).
    if ($manage_categories) {
        $existingnames = optional_param_array('category_existing', [], PARAM_TEXT);
        $deleteflags = optional_param_array('category_delete', [], PARAM_BOOL);
        $newnames = optional_param_array('category_new', [], PARAM_TEXT);

        $categories = $DB->get_records_select(
            'learninglog_categories',
            'userid = :userid AND courseid = :courseid AND learninglogid IS NULL',
            ['userid' => $USER->id, 'courseid' => $course->id]
        );

        // Update or delete existing categories.
        if (!empty($existingnames)) {
            foreach ($categories as $cat) {
                $id = (int)$cat->id;
                $name = trim($existingnames[$id] ?? '');
                $delete = !empty($deleteflags[$id]);

                // Never delete section-linked categories.
                $issectionlinked = !empty($cat->sectionid);
                if ($delete && !$issectionlinked) {
                    $DB->delete_records('learninglog_postcats', ['categoryid' => $id]);
                    $DB->delete_records('learninglog_categories', ['id' => $id]);
                    continue;
                }

                if ($name !== '' && $name !== $cat->name) {
                    $cat->name = $name;
                    $cat->timemodified = time();
                    $DB->update_record('learninglog_categories', $cat);
                }
            }
        }

        // Insert new categories (course-level: courseid set, learninglogid null).
        foreach ($newnames as $newname) {
            $newname = trim($newname);
            if ($newname === '') {
                continue;
            }
            $maxsort = (int)$DB->get_field_sql(
                'SELECT COALESCE(MAX(sortorder), 0) FROM {learninglog_categories} WHERE userid = ? AND courseid = ? AND learninglogid IS NULL',
                [$USER->id, $course->id]
            );
            $record = (object)[
                'courseid' => $course->id,
                'learninglogid' => null,
                'userid' => $USER->id,
                'parentid' => null,
                'sectionid' => null,
                'name' => $newname,
                'slug' => null,
                'sortorder' => $maxsort + 1,
                'timecreated' => time(),
                'timemodified' => time(),
            ];
            $DB->insert_record('learninglog_categories', $record);
        }
    }
    if ($cmid) {
        redirect(new moodle_url('/mod/learninglog/view.php', ['id' => $cmid]));
    }
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

$PAGE->set_url('/mod/learninglog/create.php', ['courseid' => $course->id, 'id' => $cmid]);
$PAGE->set_title(get_string('createlearninglog', 'block_learninglog'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('createlearninglog', 'block_learninglog'), 2);
$mform->display();
echo $OUTPUT->footer();
