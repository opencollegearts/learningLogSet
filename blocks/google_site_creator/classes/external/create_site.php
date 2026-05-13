<?php
// This file is part of Moodle - http://moodle.org/

namespace block_google_site_creator\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
require_once(__DIR__ . '/../../lib.php');
require_once(__DIR__ . '/../../classes/drive_service.php');

/**
 * External function: create a Google Site on behalf of a user.
 *
 * @package   block_google_site_creator
 */
class create_site extends \external_api {

    public static function execute_parameters(): \external_function_parameters {
        return new \external_function_parameters([
            'courseid' => new \external_value(PARAM_INT, 'Course ID'),
            'userid' => new \external_value(PARAM_INT, 'User ID (owner of the site)'),
            'title' => new \external_value(PARAM_TEXT, 'Site title'),
            'templateid' => new \external_value(PARAM_TEXT, 'Template Drive file ID (optional; overrides course/site default)', VALUE_OPTIONAL, null),
            'visibility' => new \external_value(PARAM_ALPHANUMEXT, 'Visibility: tutors, unit_group, all_oca', VALUE_DEFAULT, 'tutors'),
        ]);
    }

    public static function execute(int $courseid, int $userid, string $title, ?string $templateid, string $visibility): array {
        global $DB, $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'userid' => $userid,
            'title' => $title,
            'templateid' => $templateid,
            'visibility' => $visibility,
        ]);
        $courseid = $params['courseid'];
        $userid = $params['userid'];
        $title = $params['title'];
        $templateid = $params['templateid'];
        $visibility = $params['visibility'];

        $course = get_course($courseid);
        $context = \context_course::instance($courseid);
        self::validate_context($context);
        require_capability('block/google_site_creator:create_for_others', \context_system::instance());

        $templateid = !empty(trim($templateid ?? '')) ? trim($templateid) : block_google_site_creator_get_template_id($courseid);
        if (empty($templateid)) {
            throw new \moodle_exception('notemplate', 'block_google_site_creator');
        }

        $allowed = ['tutors', 'unit_group', 'all_oca'];
        if (!in_array($visibility, $allowed, true)) {
            throw new \invalid_parameter_exception('visibility must be one of: ' . implode(', ', $allowed));
        }

        $shareemail = block_google_site_creator_get_share_email($visibility, $courseid);
        if (empty($shareemail)) {
            throw new \moodle_exception('nocourseconfig', 'block_google_site_creator');
        }

        $credentials = block_google_site_creator_get_service_account_json();
        if (empty($credentials)) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }

        $drive = new \block_google_site_creator\drive_service($credentials);
        $foldername = $course->shortname;
        $folderid = $drive->ensure_folder_by_name($foldername);
        $copy = $drive->copy_file($templateid, $title, $folderid);
        $newfileid = $copy['id'] ?? null;
        $url = $copy['webViewLink'] ?? $copy['webContentLink'] ?? 'https://drive.google.com/file/d/' . $newfileid . '/view';
        if (empty($newfileid)) {
            throw new \moodle_exception('createfailed', 'block_google_site_creator');
        }
        $drive->add_permission($newfileid, $shareemail, 'writer');

        $record = (object)[
            'userid' => $userid,
            'courseid' => $courseid,
            'title' => $title,
            'drive_file_id' => $newfileid,
            'url' => $url,
            'visibility' => $visibility,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $id = $DB->insert_record('block_google_site_creator_sites', $record);

        $site = $DB->get_record('block_google_site_creator_sites', ['id' => $id], '*', MUST_EXIST);
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
