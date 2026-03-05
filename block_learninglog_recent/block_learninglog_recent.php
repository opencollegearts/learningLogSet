<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

/**
 * Block showing recent learning log posts within the course.
 *
 * @package   block_learninglog_recent
 */
class block_learninglog_recent extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_learninglog_recent');
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
        ];
    }

    public function get_content() {
        global $COURSE, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        if (empty($COURSE) || $COURSE->id == SITEID || !isloggedin() || isguestuser()) {
            return $this->content;
        }

        $course = $COURSE;

        // Recent published posts visible within the course (course or org visibility).
        list($vissql, $visparams) = $DB->get_in_or_equal(['course', 'org'], SQL_PARAMS_NAMED);
        $params = ['courseid' => $course->id] + $visparams;
        $sql = "SELECT p.*
                  FROM {learninglog_posts} p
                 WHERE p.courseid = :courseid
                   AND p.status = 'published'
                   AND p.visibility $vissql
              ORDER BY p.timecreated DESC";
        $posts = $DB->get_records_sql($sql, $params, 0, 15);

        $output = html_writer::start_div('block_learninglog_recent');

        if (empty($posts)) {
            $output .= html_writer::div(get_string('noposts', 'block_learninglog_recent'), 'no-posts');
        } else {
            $viewurlbase = new moodle_url('/mod/learninglog/view.php', ['courseid' => $course->id]);
            $output .= html_writer::start_tag('ul', ['class' => 'list-unstyled']);
            foreach ($posts as $post) {
                $posturl = clone $viewurlbase;
                $posturl->param('postid', $post->id);
                // Load full user record so fullname() has all required fields.
                $user = \core_user::get_user($post->userid);
                $fullname = fullname($user);
                $categoryname = learninglog_get_post_category_display_name($post, $course);
                $date = userdate($post->timecreated, get_string('strftimedatefullshort', 'langconfig'));
                $title = html_writer::link($posturl, format_string($post->title));
                $meta = $date . ' · ' . $fullname;
                if ($categoryname !== '') {
                    $meta .= ' · ' . $categoryname;
                }
                $output .= html_writer::tag('li',
                    $title . html_writer::tag('div', $meta, ['class' => 'small text-muted']),
                    ['class' => 'mb-3']
                );
            }
            $output .= html_writer::end_tag('ul');
        }

        $output .= html_writer::end_div();
        $this->content->text = $output;
        return $this->content;
    }
}
