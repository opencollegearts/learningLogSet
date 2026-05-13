<?php
// This file is part of Moodle - http://moodle.org/

namespace block_google_site_creator\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for block_google_site_creator.
 *
 * @package   block_google_site_creator
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('block_google_site_creator_sites', [
            'userid' => 'privacy:metadata:block_google_site_creator_sites:userid',
            'courseid' => 'privacy:metadata:block_google_site_creator_sites:courseid',
            'title' => 'privacy:metadata:block_google_site_creator_sites:title',
            'url' => 'privacy:metadata:block_google_site_creator_sites:url',
            'visibility' => 'privacy:metadata:block_google_site_creator_sites:visibility',
        ], 'privacy:metadata:block_google_site_creator_sites');
        return $collection;
    }

    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $sql = "SELECT c.id FROM {context} c
                JOIN {block_google_site_creator_sites} s ON s.courseid = c.instanceid AND c.contextlevel = :courselevel
                WHERE s.userid = :userid";
        $contextlist->add_from_sql($sql, ['courselevel' => CONTEXT_COURSE, 'userid' => $userid]);
        return $contextlist;
    }

    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        $sql = "SELECT userid FROM {block_google_site_creator_sites} WHERE courseid = :courseid";
        $userlist->add_from_sql('userid', $sql, ['courseid' => $context->instanceid]);
    }

    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_COURSE) {
                continue;
            }
            $sites = $DB->get_records('block_google_site_creator_sites', [
                'userid' => $contextlist->get_user()->id,
                'courseid' => $context->instanceid,
            ]);
            if (empty($sites)) {
                continue;
            }
            $data = (object) ['sites' => array_values($sites)];
            writer::with_context($context)->export_data(
                [get_string('pluginname', 'block_google_site_creator')],
                $data
            );
        }
    }

    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel === CONTEXT_COURSE) {
            $DB->delete_records('block_google_site_creator_sites', ['courseid' => $context->instanceid]);
        }
    }

    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;
        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_COURSE) {
                $DB->delete_records('block_google_site_creator_sites', [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ]);
            }
        }
    }

    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_COURSE) {
            return;
        }
        list($insql, $params) = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;
        $DB->delete_records_select(
            'block_google_site_creator_sites',
            "courseid = :courseid AND userid $insql",
            $params
        );
    }
}
