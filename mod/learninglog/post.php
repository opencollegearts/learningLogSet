<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

use mod_learninglog\form\post_form;

$cmid = optional_param('id', 0, PARAM_INT);
if ($cmid === 0) {
    $cmid = optional_param('cmid', 0, PARAM_INT);
}
$courseidparam = optional_param('courseid', 0, PARAM_INT);

$postid = optional_param('postid', 0, PARAM_INT);
$sectionid = optional_param('sectionid', 0, PARAM_INT);
$fromactivity = optional_param('fromactivity', 0, PARAM_INT);

$courseonly = ($cmid === 0 && $courseidparam > 0);

if ($courseonly) {
    $course = $DB->get_record('course', ['id' => $courseidparam], '*', MUST_EXIST);
    require_login($course, true);
    $context = context_course::instance($course->id);
    $cm = null;
    $learninglog = null;
} elseif ($cmid > 0) {
    $cm = get_coursemodule_from_id('learninglog', $cmid, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $learninglog = $DB->get_record('learninglog', ['id' => $cm->instance], '*', MUST_EXIST);
    require_login($course, true, $cm);
    $context = context_module::instance($cm->id);
} else {
    throw new \moodle_exception('missingparam', 'error', '', 'id or courseid');
}

require_capability('mod/learninglog:write', $context);
$haslog = learninglog_has_user_log_by_course($USER->id, $course->id);
if (!$haslog) {
    throw new \moodle_exception('createfirst', 'mod_learninglog');
}
$userlog = learninglog_get_user_log($USER->id, $course->id);
$logtitle = $userlog ? format_string($userlog->name) : get_string('modulename', 'mod_learninglog');

$PAGE->set_url('/mod/learninglog/post.php', $courseonly
    ? ['courseid' => $course->id, 'postid' => $postid]
    : ['id' => $cmid, 'postid' => $postid, 'sectionid' => $sectionid]);
$PAGE->set_title($logtitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Ensure Insert layout dialog + file picker z-index fix is applied when editor is used.
$PAGE->requires->css(new moodle_url('/lib/editor/tiny/plugins/bloglayouts/styles.css'));

// Load existing post if editing.
$post = null;
if ($postid) {
    $post = $DB->get_record('learninglog_posts', [
        'id' => $postid,
        'userid' => $USER->id,
        'courseid' => $course->id,
    ], '*', MUST_EXIST);
    if ($post->userid != $USER->id && !has_capability('mod/learninglog:viewall', $context)) {
        print_error('nopermissions', 'error', '', 'edit post');
    }
}

// Category options: course-level (sections + custom from learninglog_categories).
$categoryoptions = learninglog_get_user_category_options(null, $USER->id, $course->id);

$editoroptions = [
    'context' => $context,
    'maxfiles' => -1,
    'maxbytes' => $course->maxbytes,
    'trusttext' => false,
    // Hide format dropdown so HTML is always used and TinyMCE/Atto is shown (not plain textarea).
    'changeformat' => false,
];

$banneroptions = [
    'subdirs' => 0,
    'maxfiles' => 1,
    'maxbytes' => $course->maxbytes,
    'accepted_types' => ['image'],
];

$customdata = [
    'editoroptions' => $editoroptions,
    'banneroptions' => $banneroptions,
    'categoryoptions' => $categoryoptions,
];

$mform = new post_form(null, $customdata);

// Default category when coming from activity: this section.
$defaultcategory = '';
if ($sectionid) {
    $defaultcategory = 'section_' . $sectionid;
}

// Prefill content when opening from activity (prompt at top).
$prefillcontent = '';
if (!$courseonly && $fromactivity && !$post && $learninglog) {
    $prefillcontent = $learninglog->name . "\n\n" . strip_tags($learninglog->intro);
    if ($defaultcategory === '' && $cm->section) {
        $modinfo = get_fast_modinfo($course);
        $secinfo = $modinfo->get_section_info($cm->section);
        if ($secinfo) {
            $defaultcategory = 'section_' . $secinfo->id;
        }
    }
}

// Set initial data.
if ($post) {
    $data = new stdClass();
    $data->postid = $post->id;
    $data->cmid = $courseonly ? 0 : $cm->id;
    $data->learninglogid = $post->learninglogid ?? 0;
    $data->courseid = $courseonly ? $course->id : 0;
    $data->sectionid = $post->sectionid;
    $data->title = $post->title;
    $data->status = $post->status;
    $data->visibility = $post->visibility;
    $data->allowcomments = !empty($post->allowcomments);
    $postcats = $DB->get_records('learninglog_postcats', ['postid' => $post->id], '', 'categoryid');
    if ($post->sectionid) {
        $data->category = 'section_' . $post->sectionid;
    } elseif ($postcats) {
        $first = reset($postcats);
        $data->category = 'cat_' . $first->categoryid;
    } else {
        $data->category = $defaultcategory ?: '';
    }

    $postcontext = learninglog_get_context_for_post($post, $course);
    // Prepare the content editor draft area with existing embedded files so that when the user
    // adds more images and saves, file_postupdate_standard_editor copies all files (existing + new)
    // to embedded instead of replacing the area with only the new draft files.
    $contentdraftitemid = file_get_submitted_draft_itemid('content_editor');
    $prepareoptions = array_merge($editoroptions, ['subdirs' => 1]);
    file_prepare_draft_area(
        $contentdraftitemid,
        $postcontext->id,
        'mod_learninglog',
        'embedded',
        $post->id,
        $prepareoptions
    );
    // Use embedded URLs for display so images load in the editor (draft URLs often fail to load
    // in the editor context). We still set itemid and prepared the draft so new images go to the
    // same draft and save preserves all files.
    $editorcontent = file_rewrite_pluginfile_urls(
        $post->content,
        'pluginfile.php',
        $postcontext->id,
        'mod_learninglog',
        'embedded',
        $post->id
    );
    $data->content_editor = [
        'text' => $editorcontent,
        'format' => FORMAT_HTML,
        'itemid' => $contentdraftitemid,
    ];

    $draftitemid = file_get_submitted_draft_itemid('bannerfile');
    file_prepare_draft_area(
        $draftitemid,
        $postcontext->id,
        'mod_learninglog',
        'banner',
        $post->id,
        $banneroptions
    );
    $data->bannerfile = $draftitemid;

    $mform->set_data($data);
} else {
    $data = new stdClass();
    $data->cmid = $courseonly ? 0 : $cm->id;
    $data->learninglogid = $courseonly ? 0 : $learninglog->id;
    $data->courseid = $courseonly ? $course->id : 0;
    $data->sectionid = $sectionid;
    $data->category = $defaultcategory;
    // Default post visibility from learning log site-wide setting (can be overridden per post).
    $data->visibility = ($userlog && !empty($userlog->sitewide)) ? 'org' : 'course';
    $data->allowcomments = 1;
    $data->content_editor = [
        'text' => $prefillcontent,
        'format' => FORMAT_HTML,
    ];
    $draftitemid = file_get_submitted_draft_itemid('bannerfile');
    $data->bannerfile = $draftitemid;
    $mform->set_data($data);
}

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/mod/learninglog/view.php', $courseonly ? ['courseid' => $course->id] : ['id' => $cm->id]));
}

if ($formdata = $mform->get_data()) {
    $record = new stdClass();
    $record->learninglogid = !empty($formdata->courseid) ? null : $learninglog->id;
    $record->userid = $USER->id;
    $record->courseid = $course->id;
    $record->title = $formdata->title;
    $record->content = $formdata->content_editor['text'] ?? '';
    $record->contentformat = $formdata->content_editor['format'] ?? FORMAT_HTML;
    $record->status = $formdata->status;
    $record->visibility = $formdata->visibility;
    $record->allowcomments = !empty($formdata->allowcomments) ? 1 : 0;
    $record->timemodified = time();

    // Category: either a section or a user-created category.
    $category = $formdata->category ?? '';
    $record->sectionid = null;
    $customcatid = 0;
    if (strpos($category, 'section_') === 0) {
        $record->sectionid = (int) substr($category, 8);
    } elseif (strpos($category, 'cat_') === 0) {
        $customcatid = (int) substr($category, 4);
    }

    $savecontext = !empty($formdata->courseid) ? context_course::instance($course->id) : $context;

    if (!empty($formdata->postid)) {
        $record->id = $formdata->postid;
        $DB->update_record('learninglog_posts', $record);
        $postid = $record->id;
    } else {
        $record->timecreated = $record->timemodified;
        $postid = $DB->insert_record('learninglog_posts', $record);
    }

    // Move editor draft files to mod_learninglog/embedded and rewrite content URLs (fixes embedded images e.g. from blog layouts).
    // The editor element is named 'content_editor', so the base field name for file_postupdate_standard_editor is 'content'.
    $formdata = file_postupdate_standard_editor(
        $formdata,
        'content',
        $editoroptions,
        $savecontext,
        'mod_learninglog',
        'embedded',
        $postid
    );
    $record->content = $formdata->content ?? '';
    $record->contentformat = $formdata->contentformat ?? FORMAT_HTML;
    global $CFG;
    // Rewrite any draft URLs that made it into the content (e.g. from editor with full draft URLs).
    // file_postupdate_standard_editor copies draft files to embedded but may not rewrite these URLs.
    $record->content = preg_replace(
        '#' . preg_quote($CFG->wwwroot, '#') . '/pluginfile\.php/\d+/user/draft/\d+/([^"\'\\s>]*)#u',
        '@@PLUGINFILE@@/$1',
        $record->content
    );
    // Normalise full embedded pluginfile URLs back to @@PLUGINFILE@@ so stored content is portable.
    $base = preg_quote($CFG->wwwroot . '/pluginfile.php/' . $savecontext->id . '/mod_learninglog/embedded/' . $postid, '#');
    $record->content = preg_replace('#' . $base . '(/[^"\'\\s>]*)#', '@@PLUGINFILE@@$1', $record->content);
    $record->id = $postid;
    $DB->update_record('learninglog_posts', $record);

    // Save banner image file (course context for course-only posts).
    if (!empty($formdata->bannerfile)) {
        file_save_draft_area_files(
            $formdata->bannerfile,
            $savecontext->id,
            'mod_learninglog',
            'banner',
            $postid,
            $banneroptions
        );
    }

    // Post–category: at most one custom category per post (only when linked to an activity).
    $DB->delete_records('learninglog_postcats', ['postid' => $postid]);
    if ($customcatid > 0) {
        $map = new stdClass();
        $map->postid = $postid;
        $map->categoryid = $customcatid;
        $DB->insert_record('learninglog_postcats', $map);
    }

    // Update completion when submitting from an activity.
    if (!$courseonly && $cm) {
        $completion = new completion_info($course);
        if ($completion->is_enabled($cm) != COMPLETION_DISABLED) {
            $completion->update_state($cm, COMPLETION_COMPLETE);
        }
    }

    $redirectparams = !empty($formdata->courseid) ? ['courseid' => $course->id] : ['id' => $cm->id];
    $redirectparams['postid'] = $postid;
    redirect(new moodle_url('/mod/learninglog/view.php', $redirectparams));
}

echo $OUTPUT->header();
echo $OUTPUT->heading($logtitle, 2);
echo $OUTPUT->heading(get_string('editpostheading', 'mod_learninglog'), 3);

$mform->display();

echo $OUTPUT->footer();

