<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_learninglog\output;

defined('MOODLE_INTERNAL') || die();

use renderable;
use renderer_base;
use templatable;
use stdClass;

/**
 * Renderer for learning log outputs.
 *
 * @package   mod_learninglog
 */
class renderer extends \plugin_renderer_base {

    /**
     * Build context for a grid of posts.
     *
     * @param \stdClass $course
     * @param \stdClass[] $posts
     * @param \context $context current view context (module or course)
     * @param array $viewparams params for view URL: ['id' => cmid] or ['courseid' => courseid]
     * @return stdClass
     */
    public function render_post_grid(\stdClass $course, array $posts, \context $context, array $viewparams): stdClass {
        global $OUTPUT, $USER;

        $cards = [];
        foreach ($posts as $post) {
            $card = new stdClass();
            $card->id = $post->id;
            $card->title = format_string($post->title);
            $card->summary = !empty($post->aisummary)
                ? shorten_text($post->aisummary, 200)
                : shorten_text(strip_tags($post->content), 200);
            $card->date = userdate($post->timecreated);
            $card->visibility = $post->visibility;
            $card->status = $post->status;
            // Site-wide view: link to the post in its course (courseid + postid). Never put _global in the URL.
            if (!empty($viewparams['_global'])) {
                $params = ['courseid' => (int)$post->courseid, 'postid' => $post->id];
            } else {
                $params = $viewparams + ['postid' => $post->id];
            }
            $card->url = (new \moodle_url('/mod/learninglog/view.php', $params))->out(false);

            // User avatar (owner of the post).
            $user = $USER;
            if ($post->userid != $USER->id) {
                $user = \core_user::get_user($post->userid, '*', MUST_EXIST);
            }
            $card->userpicture = $OUTPUT->user_picture($user, ['size' => 48, 'link' => false]);
            $card->fullname = fullname($user);

            // Banner image: stored in module or course context depending on post.
            $courseforcontext = (!empty($viewparams['_global'])) ? (object)['id' => $post->courseid] : $course;
            $postcontext = \learninglog_get_context_for_post($post, $courseforcontext);
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $postcontext->id,
                'mod_learninglog',
                'banner',
                $post->id,
                'filename',
                false
            );
            $card->bannerurl = null;
            if ($files) {
                $file = reset($files);
                $card->bannerurl = \moodle_url::make_pluginfile_url(
                    $file->get_contextid(),
                    $file->get_component(),
                    $file->get_filearea(),
                    $file->get_itemid(),
                    $file->get_filepath(),
                    $file->get_filename()
                )->out(false);
            }

            $cards[] = $card;
        }

        $contextdata = new stdClass();
        $contextdata->cards = $cards;

        return $contextdata;
    }
}

