<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_learninglog\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for mod_learninglog.
 *
 * @package   mod_learninglog
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('learninglog_posts', [
            'userid' => 'privacy:metadata:learninglog_posts:userid',
            'title' => 'privacy:metadata:learninglog_posts:title',
            'content' => 'privacy:metadata:learninglog_posts:content',
            'status' => 'privacy:metadata:learninglog_posts:status',
            'visibility' => 'privacy:metadata:learninglog_posts:visibility',
            'aisummary' => 'privacy:metadata:learninglog_posts:aisummary',
            'aialttext' => 'privacy:metadata:learninglog_posts:aialttext',
        ], 'privacy:metadata:learninglog_posts');

        $collection->add_database_table('learninglog_comments', [
            'userid' => 'privacy:metadata:learninglog_comments:userid',
            'content' => 'privacy:metadata:learninglog_comments:content',
            'istutor' => 'privacy:metadata:learninglog_comments:istutor',
            'tutorvisibility' => 'privacy:metadata:learninglog_comments:tutorvisibility',
        ], 'privacy:metadata:learninglog_comments');

        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextmodule
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                  JOIN {learninglog} l ON l.id = cm.instance
                  JOIN {learninglog_posts} p ON p.learninglogid = l.id
                 WHERE p.userid = :userid";
        $params = [
            'contextmodule' => CONTEXT_MODULE,
            'modname' => 'learninglog',
            'userid' => $userid,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist) {
        if ($userlist->get_context()->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $context = $userlist->get_context();

        $sql = "SELECT p.userid
                  FROM {learninglog_posts} p
                  JOIN {learninglog} l ON l.id = p.learninglogid
                  JOIN {course_modules} cm ON cm.instance = l.id
                  JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                 WHERE cm.id = :cmid";
        $params = [
            'modname' => 'learninglog',
            'cmid' => $context->instanceid,
        ];

        $userlist->add_from_sql('userid', $sql, $params);
    }

    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $sql = "SELECT p.*
                      FROM {learninglog_posts} p
                      JOIN {learninglog} l ON l.id = p.learninglogid
                      JOIN {course_modules} cm ON cm.instance = l.id
                      JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                     WHERE cm.id = :cmid
                       AND p.userid = :userid";
            $params = [
                'modname' => 'learninglog',
                'cmid' => $context->instanceid,
                'userid' => $userid,
            ];
            $posts = $DB->get_records_sql($sql, $params);

            if (!$posts) {
                continue;
            }

            $export = [];
            foreach ($posts as $post) {
                $export[] = (object)[
                    'title' => $post->title,
                    'content' => $post->content,
                    'status' => $post->status,
                    'visibility' => $post->visibility,
                    'timecreated' => transform::datetime($post->timecreated),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'mod_learninglog')],
                (object)['posts' => $export]
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('learninglog', $context->instanceid);
        if (!$cm) {
            return;
        }

        if ($learninglog = $DB->get_record('learninglog', ['id' => $cm->instance])) {
            $DB->delete_records('learninglog_posts', ['learninglogid' => $learninglog->id]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('learninglog', $context->instanceid);
            if (!$cm) {
                continue;
            }

            if ($learninglog = $DB->get_record('learninglog', ['id' => $cm->instance])) {
                $DB->delete_records('learninglog_posts', ['learninglogid' => $learninglog->id, 'userid' => $userid]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('learninglog', $context->instanceid);
        if (!$cm) {
            return;
        }

        if ($learninglog = $DB->get_record('learninglog', ['id' => $cm->instance])) {
            $userids = $userlist->get_userids();
            list($insql, $params) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $params['learninglogid'] = $learninglog->id;
            $DB->delete_records_select('learninglog_posts', "learninglogid = :learninglogid AND userid $insql", $params);
        }
    }
}

