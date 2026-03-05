<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

/**
 * Learning log block.
 * Shows "Create Learning Log" until the student has created their log, then "Add Learning Log Entry" and recent posts.
 *
 * @package   block_learninglog
 */
class block_learninglog extends block_base {

    public function init() {
        $this->title = get_string('pluginname', 'block_learninglog');
    }

    public function applicable_formats() {
        return [
            'course-view' => true,
            'mod' => true,
            'my' => true,
        ];
    }

    public function get_content() {
        global $COURSE, $PAGE, $USER, $DB;

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
        $modinfo = get_fast_modinfo($course);
        $cms = $modinfo->get_instances_of('learninglog');
        $cm = !empty($cms) ? reset($cms) : null;
        $learninglogid = $cm ? $cm->instance : null;

        $haslog = learninglog_has_user_log_by_course($USER->id, $course->id);
        $userlog = $haslog ? learninglog_get_user_log($USER->id, $course->id) : null;

        $output = html_writer::start_div('learninglog-block');

        if (!$haslog) {
            // Student must create (and name) their log via the block. Link with or without activity.
            if ($cm) {
                $createurl = new moodle_url('/mod/learninglog/create.php', ['id' => $cm->id]);
            } else {
                $createurl = new moodle_url('/mod/learninglog/create.php', ['courseid' => $course->id]);
            }
            $output .= html_writer::link(
                $createurl,
                get_string('createlearninglog', 'block_learninglog'),
                ['class' => 'btn btn-primary w-100 mb-3']
            );
            if (!$cm && has_capability('moodle/course:manageactivities', $PAGE->context)) {
                $output .= html_writer::div(get_string('nolearningloginstance', 'block_learninglog'), 'small text-muted mt-2');
            }
        } else {
            // When adding entries, prefer the activity (for prompt/defaults) if it exists,
            // but "View my learning log" should always go to the course-level log view
            // so no activity header/prompt appears.
            if ($cm) {
                $sectionid = optional_param('section', 0, PARAM_INT);
                $addurl = new moodle_url('/mod/learninglog/post.php', ['id' => $cm->id]);
                if ($sectionid) {
                    $addurl->param('sectionid', $sectionid);
                }
            } else {
                $addurl = new moodle_url('/mod/learninglog/post.php', ['courseid' => $course->id]);
            }
            $viewurl = new moodle_url('/mod/learninglog/view.php', ['courseid' => $course->id]);
            $output .= html_writer::link(
                $addurl,
                get_string('addentry', 'block_learninglog'),
                ['class' => 'btn btn-primary w-100 mb-3']
            );
            $output .= html_writer::link($viewurl, get_string('viewmylog', 'block_learninglog'), ['class' => 'small d-block mb-2']);
            $editlogurl = new moodle_url('/mod/learninglog/create.php', ['courseid' => $course->id]);
            $output .= html_writer::link($editlogurl, get_string('editlearninglog', 'block_learninglog'), ['class' => 'small d-block mb-2']);

            // Recent drafts/personal research: all user's drafts in this course (activity or course-only).
            list($statussql, $statusparams) = $DB->get_in_or_equal(['draft', 'personalresearch'], SQL_PARAMS_NAMED);
            $params = ['userid' => $USER->id, 'courseid' => $course->id] + $statusparams;
            if ($userlog->learninglogid) {
                $sql = "SELECT * FROM {learninglog_posts}
                         WHERE userid = :userid AND courseid = :courseid AND status $statussql
                           AND (learninglogid = :lid OR learninglogid IS NULL)
                      ORDER BY timemodified DESC";
                $params['lid'] = $userlog->learninglogid;
            } else {
                $sql = "SELECT * FROM {learninglog_posts}
                         WHERE userid = :userid AND courseid = :courseid AND status $statussql
                           AND learninglogid IS NULL
                      ORDER BY timemodified DESC";
            }
            $posts = $DB->get_records_sql($sql, $params, 0, 3);

            if ($posts) {
                $output .= html_writer::start_tag('ul', ['class' => 'list-unstyled small']);
                foreach ($posts as $post) {
                    $editurl = $cm
                        ? new moodle_url('/mod/learninglog/post.php', ['id' => $cm->id, 'postid' => $post->id])
                        : new moodle_url('/mod/learninglog/post.php', ['courseid' => $course->id, 'postid' => $post->id]);
                    $output .= html_writer::tag('li',
                        html_writer::link($editurl, format_string($post->title)),
                        ['class' => 'mb-1']
                    );
                }
                $output .= html_writer::end_tag('ul');
            }
        }

        $output .= html_writer::end_div();
        $this->content->text = $output;
        return $this->content;
    }
}

