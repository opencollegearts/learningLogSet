<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/learninglog/locallib.php');

/**
 * Combined course Learning Logs block.
 * Lists Learning Log posts and Google Sites together in one timeline.
 *
 * @package   block_learninglog_collection
 */
class block_learninglog_collection extends block_base {
    public function init() {
        $this->title = get_string('pluginname', 'block_learninglog_collection');
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

        $courseid = (int)$COURSE->id;
        $items = [];

        // Published learning log posts visible in this course.
        list($vissql, $visparams) = $DB->get_in_or_equal(['course', 'org'], SQL_PARAMS_NAMED);
        $params = ['courseid' => $courseid] + $visparams;
        $sql = "SELECT p.id, p.title, p.userid, p.courseid, p.timecreated
                  FROM {learninglog_posts} p
                 WHERE p.courseid = :courseid
                   AND p.status = 'published'
                   AND p.visibility $vissql
              ORDER BY p.timecreated DESC";
        $posts = $DB->get_records_sql($sql, $params, 0, 50);
        foreach ($posts as $post) {
            $items[] = (object)[
                'title' => format_string($post->title),
                'userid' => (int)$post->userid,
                'date' => (int)$post->timecreated,
                'url' => (new moodle_url('/mod/learninglog/view.php', ['courseid' => $courseid, 'postid' => $post->id]))->out(false),
                'newtab' => false,
                'kind' => 'post',
            ];
        }

        // Google Sites created for this course.
        if ($DB->get_manager()->table_exists('block_google_site_creator_sites')) {
            $sites = $DB->get_records('block_google_site_creator_sites', ['courseid' => $courseid], 'timecreated DESC');
            foreach ($sites as $site) {
                $items[] = (object)[
                    'title' => format_string($site->title),
                    'userid' => (int)$site->userid,
                    'date' => (int)$site->timecreated,
                    'url' => (string)$site->url,
                    'newtab' => true,
                    'kind' => 'site',
                ];
            }
        }

        if (empty($items)) {
            $this->content->text = html_writer::div(get_string('nolearninglogs', 'block_learninglog_collection'), 'small');
            return $this->content;
        }

        usort($items, static function($a, $b) {
            return $b->date <=> $a->date;
        });
        $items = array_slice($items, 0, 20);

        $out = html_writer::start_tag('ul', ['class' => 'list-unstyled']);
        foreach ($items as $item) {
            $user = \core_user::get_user($item->userid, '*', IGNORE_MISSING);
            $author = $user ? fullname($user) : get_string('unknownuser', 'core');
            $attrs = ['class' => ''];
            if ($item->newtab) {
                $attrs['target'] = '_blank';
                $attrs['rel'] = 'noopener noreferrer';
            }
            $title = html_writer::link($item->url, s($item->title), $attrs);
            $badgelabel = ($item->kind === 'site')
                ? get_string('itemlabelsite', 'block_learninglog_collection')
                : get_string('itemlabelpost', 'block_learninglog_collection');
            $badge = html_writer::tag('span', s($badgelabel), ['class' => 'badge bg-secondary ms-2']);
            $meta = userdate($item->date, get_string('strftimedatefullshort', 'langconfig'))
                . ' · ' . s($author)
                . ' · ' . get_string('itemlabel', 'block_learninglog_collection');
            $out .= html_writer::tag(
                'li',
                html_writer::tag('div', $title . $badge, ['class' => 'mb-1'])
                . html_writer::tag('div', $meta, ['class' => 'small text-muted']),
                ['class' => 'mb-3']
            );
        }
        $out .= html_writer::end_tag('ul');

        $this->content->text = $out;
        return $this->content;
    }
}
