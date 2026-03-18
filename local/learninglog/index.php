<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

require_login();

$systemcontext = context_system::instance();
require_capability('local/learninglog:vieworg', $systemcontext);

$view = optional_param('view', 'posts', PARAM_ALPHA);
if (!in_array($view, ['posts', 'logs'], true)) {
    $view = 'posts';
}

$pageurl = new moodle_url('/local/learninglog/index.php', ['view' => $view]);
$PAGE->set_url($pageurl);
$PAGE->set_context($systemcontext);
$PAGE->set_title(get_string('pluginname', 'local_learninglog'));
$PAGE->set_heading(get_string('pluginname', 'local_learninglog'));
// Ensure mod_learninglog tile styles apply when we render post_grid or log_grid.
$PAGE->requires->css('/mod/learninglog/styles.css');
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js'), true);
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js'), true);
$PAGE->requires->js_call_amd('tiny_bloglayouts/frontend', 'init');
$PAGE->requires->js_call_amd('mod_learninglog/scrollfade', 'init');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_learninglog'));

// Tabs: Posts | Learning Logs
$tabposts = new tabobject('posts', new moodle_url('/local/learninglog/index.php', ['view' => 'posts']), get_string('viewposts', 'local_learninglog'));
$tablogs = new tabobject('logs', new moodle_url('/local/learninglog/index.php', ['view' => 'logs']), get_string('viewlearninglogs', 'local_learninglog'));
$tabs = [$tabposts, $tablogs];
$selected = $view === 'logs' ? 'logs' : 'posts';
echo $OUTPUT->tabtree($tabs, $selected);

if ($view === 'posts') {
    // Fetch organisation-visible posts across all learning log instances.
    $sql = "SELECT p.*
              FROM {learninglog_posts} p
             WHERE p.visibility = :visibility
          ORDER BY p.timecreated DESC";
    $params = ['visibility' => 'org'];
    $posts = $DB->get_records_sql($sql, $params, 0, 100);

    if ($posts) {
        $fakecourse = (object)['id' => SITEID];
        $postsarray = is_array($posts) ? array_values($posts) : [$posts];
        $gridcontext = learninglog_get_post_grid_context($fakecourse, $postsarray, $systemcontext, ['_global' => true]);
        echo $OUTPUT->render_from_template('mod_learninglog/post_grid', $gridcontext);
    } else {
        echo $OUTPUT->notification(get_string('noglobalposts', 'local_learninglog'), \core\output\notification::NOTIFY_INFO);
    }
} else {
    // Learning Logs view: list logs that have site-wide visibility, with filters.
    $categoryid = optional_param('filtercategory', 0, PARAM_INT);
    $courseid = optional_param('filtercourse', 0, PARAM_INT);

    $filterurl = new moodle_url('/local/learninglog/index.php', ['view' => 'logs']);
    $sql = "SELECT l.id AS userlogid, l.name AS logname, l.userid, l.courseid,
                   c.fullname AS coursename, c.category AS categoryid,
                   u.firstname, u.lastname
              FROM {learninglog_user_log} l
              JOIN {course} c ON c.id = l.courseid
              JOIN {user} u ON u.id = l.userid
             WHERE l.sitewide = 1";
    $params = [];
    if ($categoryid > 0) {
        $sql .= " AND c.category = :categoryid";
        $params['categoryid'] = $categoryid;
    }
    if ($courseid > 0) {
        $sql .= " AND l.courseid = :courseid";
        $params['courseid'] = $courseid;
    }
    $sql .= " ORDER BY c.fullname, l.name";
    $logs = $DB->get_records_sql($sql, $params);

    // Build filter form: course category and course (course unit).
    $categories = $DB->get_records('course_categories', null, 'name', 'id, name');
    $categoryoptions = [0 => get_string('allcategories', 'local_learninglog')];
    foreach ($categories as $cat) {
        $categoryoptions[$cat->id] = $cat->name;
    }
    $courseswithlogs = $DB->get_records_sql(
        "SELECT c.id, c.fullname, c.category
           FROM {learninglog_user_log} l
           JOIN {course} c ON c.id = l.courseid
          WHERE l.sitewide = 1
          GROUP BY c.id, c.fullname, c.category
          ORDER BY c.fullname",
        []
    );
    $courseoptions = [0 => get_string('allcourses', 'local_learninglog')];
    foreach ($courseswithlogs as $c) {
        $courseoptions[$c->id] = $c->fullname;
    }

    echo $OUTPUT->box_start('generalbox mb-3');
    echo html_writer::start_tag('form', ['method' => 'get', 'action' => $filterurl->out(false), 'class' => 'local-learninglog-filters']);
    echo html_writer::input_hidden_params($filterurl);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'view', 'value' => 'logs']);
    echo get_string('filterbycategory', 'local_learninglog') . ' ';
    echo html_writer::select($categoryoptions, 'filtercategory', $categoryid, false, ['id' => 'filtercategory']);
    echo ' ' . get_string('filterbycourse', 'local_learninglog') . ' ';
    echo html_writer::select($courseoptions, 'filtercourse', $courseid, false, ['id' => 'filtercourse']);
    echo ' ' . html_writer::empty_tag('input', ['type' => 'submit', 'value' => get_string('filter', 'local_learninglog')]);
    echo html_writer::end_tag('form');
    echo $OUTPUT->box_end();

    if ($logs) {
        $logsarray = array_values($logs);
        $gridcontext = learninglog_get_log_grid_context($logsarray);
        echo $OUTPUT->render_from_template('mod_learninglog/log_grid', $gridcontext);
    } else {
        echo $OUTPUT->notification(get_string('nositewidelogs', 'local_learninglog'), \core\output\notification::NOTIFY_INFO);
    }
}

echo $OUTPUT->footer();
