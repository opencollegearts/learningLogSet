<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

/**
 * Recent Learning Log comments for the current user's posts in this course.
 *
 * @package   block_learninglog_comments
 */
class block_learninglog_comments extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_learninglog_comments');
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'mod' => true,
        ];
    }

    public function get_content() {
        global $COURSE, $USER, $DB, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($COURSE) || $COURSE->id == SITEID || !isloggedin() || isguestuser()) {
            return $this->content;
        }

        $PAGE->requires->css('/mod/learninglog/styles.css');

        if (!$DB->get_manager()->table_exists('learninglog_comments')) {
            return $this->content;
        }

        $courseid = (int) $COURSE->id;
        $userid = (int) $USER->id;

        $sql = "SELECT c.*, p.title AS posttitle, p.id AS postid
                  FROM {learninglog_comments} c
                  JOIN {learninglog_posts} p ON p.id = c.postid
                 WHERE p.courseid = :courseid AND p.userid = :userid
              ORDER BY c.timecreated DESC";

        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'userid' => $userid], 0, 20);

        if (!$rows) {
            $this->content->text = html_writer::div(get_string('nocomments', 'block_learninglog_comments'), 'small');
            return $this->content;
        }

        $viewbase = new moodle_url('/mod/learninglog/view.php', ['courseid' => $courseid]);

        $out = html_writer::start_div('learninglog-block-comments');
        $out .= html_writer::start_tag('ul', ['class' => 'list-unstyled']);
        foreach ($rows as $row) {
            $istutor = !empty($row->istutor);
            $liclass = $istutor ? 'learninglog-block-comment--tutor' : '';
            $icon = '';
            if ($istutor) {
                $icon = html_writer::tag(
                    'span',
                    '',
                    [
                        'class' => 'icon fa fa-graduation-cap fa-fw text-primary learninglog-tutor-comment-icon',
                        'title' => get_string('tutorcomment', 'mod_learninglog'),
                        'aria-hidden' => 'true',
                    ]
                );
            }
            $posturl = clone $viewbase;
            $posturl->param('postid', $row->postid);

            $meta = userdate($row->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
            if ($istutor) {
                $tv = isset($row->tutorvisibility) ? (string) $row->tutorvisibility : '';
                if ($tv === 'private') {
                    $meta .= ' · ' . get_string('tutorcommentprivate', 'mod_learninglog');
                } else if ($tv === 'public') {
                    $meta .= ' · ' . get_string('tutorcommentpublic', 'mod_learninglog');
                }
            }

            $titlelink = html_writer::link($posturl, format_string($row->posttitle));
            $excerpt = shorten_text(format_string($row->content), 120);

            $out .= html_writer::tag(
                'li',
                $icon . ' ' . html_writer::div($excerpt, 'small') .
                html_writer::div($meta, 'small text-muted') .
                html_writer::div(get_string('onpost', 'block_learninglog_comments', $titlelink), 'small mt-1'),
                ['class' => 'mb-3 pb-2 border-bottom ' . $liclass]
            );
        }
        $out .= html_writer::end_tag('ul');
        $out .= html_writer::end_div();

        $this->content->text = $out;
        return $this->content;
    }
}
