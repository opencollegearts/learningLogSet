<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$postid = required_param('postid', PARAM_INT);
$deletecomment = optional_param('deletecomment', 0, PARAM_INT);

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

$post = $DB->get_record('learninglog_posts', ['id' => $postid], '*', MUST_EXIST);
if ((int)$post->courseid !== (int)$course->id) {
    throw new moodle_exception('invalidpost', 'mod_learninglog');
}

$cancomment = has_capability('mod/learninglog:comment', $context);
if (!learninglog_user_can_view_post($post, $course, $context, (int) $USER->id)) {
    throw new moodle_exception('nopermissions', 'error', '', 'view post');
}

// Delete own comment.
if ($deletecomment && confirm_sesskey()) {
    $comment = $DB->get_record('learninglog_comments', ['id' => $deletecomment, 'postid' => $postid], '*', IGNORE_MISSING);
    if ($comment && $comment->userid == $USER->id) {
        $DB->delete_records('learninglog_comments', ['id' => $comment->id]);
    }
    redirect(new moodle_url('/mod/learninglog/view.php', $viewparams + ['postid' => $postid]));
}

// Add comment.
$content = optional_param('content', '', PARAM_RAW);
if ($content !== '' && $cancomment && !empty($post->allowcomments) && confirm_sesskey()) {
    $content = trim($content);
    if ($content !== '') {
        $istutor = learninglog_user_is_tutor_in_course((int) $USER->id, (int) $course->id) ? 1 : 0;
        $tutorvisibility = null;
        if ($istutor) {
            $tv = optional_param('tutor_visibility', 'private', PARAM_ALPHA);
            $tutorvisibility = ($tv === 'public') ? 'public' : 'private';
        }
        $record = (object)[
            'postid' => $postid,
            'userid' => $USER->id,
            'content' => $content,
            'contentformat' => FORMAT_PLAIN,
            'timecreated' => time(),
            'timemodified' => time(),
            'istutor' => $istutor,
        ];
        if ($istutor) {
            $record->tutorvisibility = $tutorvisibility;
        }
        $DB->insert_record('learninglog_comments', $record);
    }
}

redirect(new moodle_url('/mod/learninglog/view.php', $viewparams + ['postid' => $postid]));
