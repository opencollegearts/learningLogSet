<?php
// This file is part of Moodle - http://moodle.org/
// View a single site-wide learning log (org-visible posts only).

require('../../config.php');
require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/learninglog:vieworg', $systemcontext);

$userlogid = required_param('userlogid', PARAM_INT);

$userlog = $DB->get_record('learninglog_user_log', ['id' => $userlogid], '*', MUST_EXIST);
if (empty($userlog->sitewide)) {
    throw new \moodle_exception('nopermissions', 'error', '', 'view this log');
}

$course = $DB->get_record('course', ['id' => $userlog->courseid], '*', MUST_EXIST);
$owner = \core_user::get_user($userlog->userid, '*', MUST_EXIST);

$pageurl = new moodle_url('/local/learninglog/viewlog.php', ['userlogid' => $userlogid]);
$PAGE->set_url($pageurl);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('viewlogheading', 'local_learninglog', format_string($userlog->name)));
$PAGE->set_heading(get_string('viewlogheading', 'local_learninglog', format_string($userlog->name)));

// Breadcrumb: Learning logs -> Learning logs (list) -> This log
$PAGE->navbar->add(get_string('navlearninglogs', 'local_learninglog'), new moodle_url('/local/learninglog/index.php', ['view' => 'logs']));
$PAGE->navbar->add(format_string($userlog->name));

$PAGE->requires->css('/mod/learninglog/styles.css');
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js'), true);
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js'), true);
$PAGE->requires->js_call_amd('tiny_bloglayouts/frontend', 'init');

echo $OUTPUT->header();

echo $OUTPUT->heading(format_string($userlog->name), 2);
echo html_writer::tag('p', get_string('course') . ': ' . html_writer::link(
    new moodle_url('/course/view.php', ['id' => $course->id]),
    format_string($course->fullname)
));
echo html_writer::tag('p', get_string('author', 'local_learninglog') . ': ' . fullname($owner));
if (!empty($userlog->description)) {
    echo $OUTPUT->box(format_text($userlog->description, FORMAT_HTML), 'generalbox');
}

// Posts from this log that are organisation-visible (post-level override respected).
$posts = $DB->get_records(
    'learninglog_posts',
    [
        'userid' => $userlog->userid,
        'courseid' => $userlog->courseid,
        'visibility' => 'org',
    ],
    'timecreated DESC'
);
$postsarray = array_values($posts);

if ($postsarray) {
    $gridcontext = learninglog_get_post_grid_context($course, $postsarray, $systemcontext, ['_global' => true]);
    echo $OUTPUT->render_from_template('mod_learninglog/post_grid', $gridcontext);
} else {
    echo $OUTPUT->notification(get_string('noglobalposts', 'local_learninglog'), \core\output\notification::NOTIFY_INFO);
}

echo $OUTPUT->footer();
