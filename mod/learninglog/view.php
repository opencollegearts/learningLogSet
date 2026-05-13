<?php
// This file is part of Moodle - http://moodle.org/

require('../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);
$postid = optional_param('postid', 0, PARAM_INT);
$category = optional_param('category', '', PARAM_ALPHANUMEXT);

// From global learning logs page: link may have postid (and _global) but no courseid; redirect to canonical URL.
if (($id == 0 && $courseid == 0) && $postid > 0) {
    $post = $DB->get_record('learninglog_posts', ['id' => $postid], 'courseid', MUST_EXIST);
    redirect(new moodle_url('/mod/learninglog/view.php', ['courseid' => $post->courseid, 'postid' => $postid]));
}

$cm = null;
$learninglog = null;

if ($id) {
    $cmrecord = get_coursemodule_from_id('learninglog', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cmrecord->course], '*', MUST_EXIST);
    $learninglog = $DB->get_record('learninglog', ['id' => $cmrecord->instance], '*', MUST_EXIST);
    require_login($course, true, $cmrecord);
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmrecord->id);
    $context = context_module::instance($cm->id);
} elseif ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course, true);
    $context = context_course::instance($course->id);
} else {
    throw new \moodle_exception('missingparam', 'error', '', 'id or courseid');
}

$userlog = learninglog_get_user_log($USER->id, $course->id);
$logtitle = $userlog ? format_string($userlog->name) : get_string('modulename', 'mod_learninglog');

$viewparams = $id ? ['id' => $id] : ['courseid' => $course->id];
$PAGE->set_url('/mod/learninglog/view.php', $viewparams + ['category' => $category]);
$PAGE->set_title($logtitle);
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Frontend assets for TinyMCE Blog layouts blocks (grid, lightbox, slider).
$PAGE->requires->css(new moodle_url('/lib/editor/tiny/plugins/bloglayouts/styles.css'));
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/css/glightbox.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/glightbox@3/dist/js/glightbox.min.js'), true);
$PAGE->requires->css(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css'));
$PAGE->requires->js(new moodle_url('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js'), true);
$PAGE->requires->js_call_amd('tiny_bloglayouts/frontend', 'init');
$PAGE->requires->js_call_amd('mod_learninglog/scrollfade', 'init');

$canwrite = $cm ? has_capability('mod/learninglog:write', $context) : has_capability('mod/learninglog:write', $context);
$haslog = learninglog_has_user_log_by_course($USER->id, $course->id);

// Viewing a specific post: allow tutors and other eligible viewers without creating their own log first.
$singlepost = null;
if ($postid > 0) {
    $singlepost = $DB->get_record('learninglog_posts', [
        'id' => $postid,
        'courseid' => $course->id,
    ], '*', IGNORE_MISSING);
    if (!$singlepost) {
        throw new moodle_exception('invalidpost', 'mod_learninglog');
    }
    if (!learninglog_user_can_view_post($singlepost, $course, $context, (int) $USER->id)) {
        throw new moodle_exception('nopermissions', 'error', '', 'view this post');
    }
} else if (!$haslog) {
    // Hub view (grid): need a personal learning log for this course.
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('createfirst', 'mod_learninglog'), 2);
    echo $OUTPUT->box(get_string('createfirstmessage', 'mod_learninglog') . ' ' .
        html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), get_string('backtocourse', 'mod_learninglog')),
        'generalbox');
    echo $OUTPUT->footer();
    exit;
}

// When viewing someone else's post, omit the personal log hero/categories (not your log).
$minimalchrome = ($singlepost && (int) $singlepost->userid !== (int) $USER->id);

echo $OUTPUT->header();

if (!$minimalchrome) {
// Log banner URL for hero (user's learning log banner image).
$logbannerurl = $userlog ? learninglog_get_log_banner_url($userlog) : null;

// Hero banner: full width, background image, gradient overlay, title, description, actions.
echo html_writer::start_div('learninglog-hero-banner' . ($logbannerurl ? ' has-banner' : ''));
if ($logbannerurl) {
    echo html_writer::div('', 'learninglog-hero-banner-bg', ['style' => 'background-image: url(' . s($logbannerurl) . ');']);
}
echo html_writer::div('', 'learninglog-hero-banner-overlay');
echo html_writer::start_div('learninglog-hero-banner-inner container');
echo html_writer::start_div('d-flex flex-wrap align-items-end justify-content-between');

// Left: title, description, edit button.
echo html_writer::start_div('learninglog-hero-banner-content');
echo html_writer::tag('h1', $logtitle, ['class' => 'learninglog-hero-title']);
if ($userlog && !empty($userlog->description)) {
    echo html_writer::div(
        format_text($userlog->description, FORMAT_HTML, ['context' => $context]),
        'learninglog-hero-description'
    );
}
if ($canwrite) {
    $editlogurl = $cm
        ? new moodle_url('/mod/learninglog/create.php', ['id' => $cm->id])
        : new moodle_url('/mod/learninglog/create.php', ['courseid' => $course->id]);
    echo html_writer::div(
        html_writer::link($editlogurl, get_string('editlearninglog', 'block_learninglog'), ['class' => 'btn btn-light btn-sm mt-2']),
        'learninglog-hero-edit'
    );
}
echo html_writer::end_div();

// Right: export / import links (in a tinted pill for contrast).
if ($canwrite) {
    $exporturl = new moodle_url('/mod/learninglog/exportwxr.php', $viewparams);
    $importurl = new moodle_url('/mod/learninglog/importwxr.php', $viewparams);
    echo html_writer::start_div('learninglog-hero-exportlinks-wrap');
    echo html_writer::start_div('learninglog-hero-exportlinks');
    echo html_writer::link($exporturl, get_string('exporttowordpress', 'mod_learninglog'), ['class' => 'learninglog-hero-link']);
    echo html_writer::span(' ', 'mx-1');
    echo html_writer::link($importurl, get_string('importfromwordpress', 'mod_learninglog'), ['class' => 'learninglog-hero-link']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

echo html_writer::end_div(); // .d-flex
echo html_writer::end_div(); // .learninglog-hero-banner-inner
echo html_writer::end_div(); // .learninglog-hero-banner
} // End !$minimalchrome (personal log chrome).

// Category navigation: use user's linked activity for consistent post set.
$categories = [];
if (!$minimalchrome && $userlog) {
    $categories = learninglog_get_categories_for_nav($course, $USER->id, $userlog->learninglogid);
}
$baseurl = new moodle_url('/mod/learninglog/view.php', $viewparams);

if (!$minimalchrome && !empty($categories)) {
    echo html_writer::start_div('learninglog-category-nav mt-2 mb-3');

    // Show the currently selected category label (or "All posts") next to the button.
    $currentlabel = get_string('allcategories', 'mod_learninglog');
    if ($category !== '') {
        foreach ($categories as $cat) {
            if ($cat->key === $category) {
                $currentlabel = $cat->name . ' (' . $cat->postcount . ')';
                break;
            }
        }
    }

    // Bootstrap-style dropdown toggle (Boost theme includes required JS/CSS).
    // Use cm id when available, otherwise fall back to course id.
    $buttonid = 'learninglog-category-dropdown-' . ($cm ? $cm->id : $course->id);
    $button = html_writer::tag(
        'button',
        get_string('categories', 'mod_learninglog'),
        [
            'class' => 'btn btn-secondary dropdown-toggle mr-2',
            'type' => 'button',
            'id' => $buttonid,
            'data-toggle' => 'dropdown',
            'aria-haspopup' => 'true',
            'aria-expanded' => 'false',
        ]
    );

    echo html_writer::start_div('dropdown d-inline-block');
    echo $button;
    echo html_writer::start_tag('div', ['class' => 'dropdown-menu', 'aria-labelledby' => $buttonid]);

    // "All posts" item.
    $allclasses = 'dropdown-item' . ($category === '' ? ' active font-weight-bold' : '');
    echo html_writer::link($baseurl, get_string('allcategories', 'mod_learninglog'), ['class' => $allclasses]);

    // Category items.
    foreach ($categories as $cat) {
        $url = clone $baseurl;
        $url->param('category', $cat->key);
        $classes = 'dropdown-item' . ($category === $cat->key ? ' active font-weight-bold' : '');
        echo html_writer::link(
            $url,
            $cat->name . ' (' . $cat->postcount . ')',
            ['class' => $classes]
        );
    }

    echo html_writer::end_tag('div'); // .dropdown-menu
    echo html_writer::end_div(); // .dropdown

    // Current selection label.
    echo html_writer::tag(
        'span',
        get_string('categories', 'mod_learninglog') . ': ' . $currentlabel,
        ['class' => 'ml-2 text-muted']
    );

    echo html_writer::end_div();
}

// Add Learning Log Entry button (above post cards).
if (!$minimalchrome && $canwrite) {
    if ($cm) {
        $addurl = new moodle_url('/mod/learninglog/post.php', ['id' => $cm->id, 'fromactivity' => 1]);
        $modinfo = get_fast_modinfo($course);
        $sectioninfo = $modinfo->get_section_info($cm->section);
        if ($sectioninfo) {
            $addurl->param('sectionid', $sectioninfo->id);
        }
    } else {
        $addurl = new moodle_url('/mod/learninglog/post.php', ['courseid' => $course->id]);
    }
    echo html_writer::div(
        html_writer::link($addurl, get_string('addentry', 'mod_learninglog'), ['class' => 'btn btn-primary mb-3']),
        'learninglog-addentry'
    );
}

// Activity prompt (if viewing via activity and it has intro).
if (!$minimalchrome && $cm && $learninglog && (trim($learninglog->name) !== '' || trim($learninglog->intro) !== '')) {
    $prompthtml = html_writer::tag('strong', get_string('prompt', 'mod_learninglog') . ': ') . format_string($learninglog->name);
    if (trim($learninglog->intro) !== '') {
        $prompthtml .= html_writer::empty_tag('br') . format_module_intro('learninglog', $learninglog, $cm->id);
    }
    echo $OUTPUT->box($prompthtml, 'generalbox mod_introbox mb-3');
}

if ($postid) {
    // Single post view (already authorised above when $singlepost was loaded).
    $post = $singlepost;
    $user = \core_user::get_user($post->userid, '*', MUST_EXIST);

    $postcontext = learninglog_get_context_for_post($post, $course);
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $postcontext->id,
        'mod_learninglog',
        'banner',
        $post->id,
        'filename',
        false
    );
    $bannerurl = null;
    if ($files) {
        $file = reset($files);
        $bannerurl = \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename()
        )->out(false);
    }

    $editurl = null;
    $deleteurl = null;
    if ($canwrite && $post->userid == $USER->id) {
        $editurl = $cm
            ? new moodle_url('/mod/learninglog/post.php', ['id' => $cm->id, 'postid' => $post->id])
            : new moodle_url('/mod/learninglog/post.php', ['courseid' => $course->id, 'postid' => $post->id]);
        $deleteurl = new moodle_url('/mod/learninglog/delete.php', $viewparams + ['postid' => $post->id]);
    }

    $cancomment = has_capability('mod/learninglog:comment', $context) && !empty($post->allowcomments);
    $showtutorcommentoptions = $cancomment && learninglog_user_is_tutor_in_course((int) $USER->id, (int) $course->id);
    $comments = [];
    if ($DB->get_manager()->table_exists('learninglog_comments')) {
        $commentrows = $DB->get_records('learninglog_comments', ['postid' => $post->id], 'timecreated ASC');
        foreach ($commentrows as $c) {
            if (!learninglog_comment_visible_to_viewer($c, $post, (int) $USER->id)) {
                continue;
            }
            $commentuser = \core_user::get_user($c->userid, '*', IGNORE_MISSING);
            $istutor = !empty($c->istutor);
            $tutorvis = isset($c->tutorvisibility) ? (string) $c->tutorvisibility : '';
            $tutoriconhtml = '';
            if ($istutor) {
                $tutoriconhtml = \html_writer::tag(
                    'span',
                    '',
                    [
                        'class' => 'icon fa fa-graduation-cap fa-fw text-primary learninglog-tutor-comment-icon',
                        'title' => get_string('tutorcomment', 'mod_learninglog'),
                        'aria-hidden' => 'true',
                    ]
                );
            }
            $comments[] = (object)[
                'id' => $c->id,
                'fullname' => $commentuser ? fullname($commentuser) : get_string('unknownuser', 'mod_learninglog'),
                'date' => userdate($c->timecreated),
                'content' => format_string($c->content),
                'candelete' => ($c->userid == $USER->id),
                'deleteurl' => ($c->userid == $USER->id) ? (new moodle_url('/mod/learninglog/comment.php', $viewparams + ['postid' => $post->id, 'deletecomment' => $c->id, 'sesskey' => sesskey()]))->out(false) : null,
                'istutor' => $istutor,
                'istutorprivate' => $istutor && $tutorvis === 'private',
                'istutorpublic' => $istutor && $tutorvis === 'public',
                'tutoriconhtml' => $tutoriconhtml,
            ];
        }
    }
    $commentformurl = new moodle_url('/mod/learninglog/comment.php', $viewparams + ['postid' => $post->id]);
    $hascommentsection = !empty($comments) || $cancomment;

    // Rewrite @@PLUGINFILE@@ placeholders to real pluginfile URLs before format_text().
    $postcontent = file_rewrite_pluginfile_urls(
        $post->content,
        'pluginfile.php',
        $postcontext->id,
        'mod_learninglog',
        'embedded',
        $post->id
    );
    $detailcontext = (object)[
        'title' => format_string($post->title),
        'date' => userdate($post->timecreated),
        'fullname' => fullname($user),
        'content' => format_text($postcontent, $post->contentformat, ['context' => $postcontext]),
        'bannerurl' => $bannerurl,
        'editurl' => $editurl ? $editurl->out(false) : null,
        'deleteurl' => $deleteurl ? $deleteurl->out(false) : null,
        'comments' => $comments,
        'hascommentsection' => $hascommentsection,
        'showcommentform' => $cancomment,
        'showtutorcommentoptions' => $showtutorcommentoptions,
        'commentformaction' => $commentformurl->out(false),
        'sesskey' => sesskey(),
    ];

    echo $OUTPUT->render_from_template('mod_learninglog/post_detail', $detailcontext);
} else {
    $options = [];
    if (strpos($category, 'section_') === 0) {
        $options['sectionid'] = (int) substr($category, 8);
    } elseif (strpos($category, 'cat_') === 0) {
        $options['categoryid'] = (int) substr($category, 4);
    }
    $posts = learninglog_get_user_posts_for_course($USER->id, $course->id, $options);
    $gridcontext = learninglog_get_post_grid_context($course, $posts, $context, $viewparams);
    echo $OUTPUT->render_from_template('mod_learninglog/post_grid', $gridcontext);
}

echo $OUTPUT->footer();

