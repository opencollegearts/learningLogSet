<?php
// This file is part of Moodle - http://moodle.org/

namespace block_google_site_creator\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');

/**
 * External function: get Google Site details by record id.
 *
 * @package   block_google_site_creator
 */
class get_site_details extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'siteid' => new \external_value(PARAM_INT, 'Moodle record ID of the Google Site'),
        ]);
    }

    public static function execute(int $siteid): array {
        global $DB;
        $params = self::validate_parameters(self::execute_parameters(), ['siteid' => $siteid]);
        $siteid = $params['siteid'];

        $site = $DB->get_record('block_google_site_creator_sites', ['id' => $siteid], '*', MUST_EXIST);
        $context = \context_course::instance($site->courseid);
        self::validate_context($context);
        $systemcontext = \context_system::instance();
        $canget = has_capability('block/google_site_creator:create', $context)
            || has_capability('block/google_site_creator:create_for_others', $systemcontext);
        if (!$canget) {
            throw new \moodle_exception('nopermissions', 'error');
        }

        return [
            'id' => (int) $site->id,
            'userid' => (int) $site->userid,
            'courseid' => (int) $site->courseid,
            'title' => $site->title,
            'drive_file_id' => $site->drive_file_id,
            'url' => $site->url,
            'visibility' => $site->visibility,
            'timecreated' => (int) $site->timecreated,
            'timemodified' => (int) $site->timemodified,
        ];
    }

    public static function execute_returns(): \external_single_structure {
        return new \external_single_structure([
            'id' => new \external_value(PARAM_INT, 'Record ID'),
            'userid' => new \external_value(PARAM_INT, 'User ID'),
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'title' => new \external_value(PARAM_TEXT, 'Site title'),
            'drive_file_id' => new \external_value(PARAM_TEXT, 'Google Drive file ID'),
            'url' => new \external_value(PARAM_URL, 'Site URL'),
            'visibility' => new \external_value(PARAM_ALPHANUMEXT, 'Visibility'),
            'timecreated' => new \external_value(PARAM_INT, 'Time created'),
            'timemodified' => new \external_value(PARAM_INT, 'Time modified'),
        ]);
    }
}
