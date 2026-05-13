<?php
// This file is part of Moodle - http://moodle.org/

namespace local_learninglog\hook;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_learninglog.
 *
 * @package   local_learninglog
 */
class callbacks {

    /**
     * Add "Log about this section" button to the footer on course section pages.
     *
     * @param \core\hook\output\before_footer_html_generation $hook
     */
    public static function before_footer_html_generation(\core\hook\output\before_footer_html_generation $hook): void {
        global $PAGE, $COURSE;

        if (empty($COURSE) || $COURSE->id == SITEID) {
            return;
        }

        if ($PAGE->pagetype !== 'course-view-topics' && $PAGE->pagetype !== 'course-view-single') {
            return;
        }

        $modinfo = get_fast_modinfo($COURSE);
        $cms = $modinfo->get_instances_of('learninglog');
        if (empty($cms)) {
            return;
        }
        $cm = reset($cms);

        $sectionid = optional_param('section', 0, PARAM_INT);
        if (!$sectionid) {
            return;
        }

        $url = new \moodle_url('/mod/learninglog/post.php', [
            'id' => $cm->id,
            'sectionid' => $sectionid,
        ]);

        $button = \html_writer::link(
            $url,
            get_string('logaboutsection', 'local_learninglog'),
            ['class' => 'btn btn-outline-secondary mb-3']
        );

        $hook->add_html(\html_writer::div($button, 'learninglog-section-button'));
    }
}
