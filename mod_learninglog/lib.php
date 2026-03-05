<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

defined('MOODLE_INTERNAL') || die();

/**
 * Library of interface functions and constants for mod_learninglog.
 *
 * @package   mod_learninglog
 * @copyright 2026
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Returns information about supported features.
 *
 * @param string $feature FEATURE_xx constant for requested feature.
 * @return mixed True if module supports feature, null if doesn't know.
 */
function learninglog_supports(string $feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
        case FEATURE_SHOW_DESCRIPTION:
        case FEATURE_BACKUP_MOODLE2:
            return true;

        case FEATURE_COMPLETION_HAS_RULES:
            return true;

        case FEATURE_GRADE_HAS_GRADE:
        case FEATURE_GRADE_OUTCOMES:
            // This module is formative only, no grades.
            return false;

        default:
            return null;
    }
}

/**
 * Add learninglog instance.
 *
 * @param stdClass $data
 * @param mod_learninglog_mod_form $mform
 * @return int new instance id
 */
function learninglog_add_instance(stdClass $data, mod_learninglog_mod_form $mform = null): int {
    global $DB;

    $data->timecreated = time();
    $data->timemodified = $data->timecreated;

    $data->id = $DB->insert_record('learninglog', $data);

    // Link any existing per-student logs (created via block without an activity) to this activity.
    $DB->set_field_select(
        'learninglog_user_log',
        'learninglogid',
        $data->id,
        'courseid = :courseid AND (learninglogid IS NULL OR learninglogid = 0)',
        ['courseid' => $data->course]
    );

    return $data->id;
}

/**
 * Update learninglog instance.
 *
 * @param stdClass $data
 * @param mod_learninglog_mod_form $mform
 * @return bool
 */
function learninglog_update_instance(stdClass $data, mod_learninglog_mod_form $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    return $DB->update_record('learninglog', $data);
}

/**
 * Delete learninglog instance.
 *
 * @param int $id
 * @return bool
 */
function learninglog_delete_instance(int $id): bool {
    global $DB;

    if (!$instance = $DB->get_record('learninglog', ['id' => $id])) {
        return false;
    }

    // Related posts, categories and files will be handled when data model is implemented.
    $DB->delete_records('learninglog', ['id' => $instance->id]);

    return true;
}

/**
 * Obtains the automatic completion state for this module based on the conditionpoststarget setting.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param int $userid
 * @param bool $type
 * @return bool
 */
function learninglog_get_completion_state($course, $cm, $userid, $type) {
    global $DB;

    $instance = $DB->get_record('learninglog', ['id' => $cm->instance], 'id, completionpoststarget', IGNORE_MISSING);
    if (!$instance || empty($instance->completionpoststarget)) {
        // No target configured, use default behaviour.
        return $type;
    }

    $conditions = [
        'learninglogid' => $instance->id,
        'userid' => $userid,
        'status' => 'published',
    ];

    $count = $DB->count_records('learninglog_posts', $conditions);
    $completed = ($count >= $instance->completionpoststarget);

    if ($type == COMPLETION_AND) {
        return $completed;
    }

    return $completed;
}

/**
 * File areas for learninglog.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array
 */
function learninglog_get_file_areas($course, $cm, $context) {
    return [
        'banner' => get_string('bannerimage', 'mod_learninglog'),
        'embedded' => get_string('embeddedfiles', 'mod_learninglog'),
        'logbanner' => get_string('logbannerimage', 'mod_learninglog'),
    ];
}

/**
 * File serving callback.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 */
function learninglog_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel != CONTEXT_MODULE && $context->contextlevel != CONTEXT_COURSE) {
        send_file_not_found();
    }
    if ($filearea !== 'banner' && $filearea !== 'embedded' && $filearea !== 'logbanner') {
        send_file_not_found();
    }

    if ($filearea === 'logbanner') {
        global $DB;
        if ($context->contextlevel != CONTEXT_COURSE) {
            send_file_not_found();
        }
        require_login($course, true);
        
        $userlogid = (int)array_shift($args);
        $filename = array_pop($args);
        $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
        
        $userlog = $DB->get_record('learninglog_user_log', ['id' => $userlogid], 'id, courseid', IGNORE_MISSING);
        if (!$userlog || (int)$userlog->courseid !== (int)$course->id) {
            send_file_not_found();
        }
        
        $fs = get_file_storage();
        
        // FIX: Separate assignment and evaluation
        $file = $fs->get_file($context->id, 'mod_learninglog', 'logbanner', $userlogid, $filepath, $filename);
        if (!$file || $file->is_directory()) {
            send_file_not_found();
        }
        
        send_stored_file($file, 0, 0, $forcedownload, []);
        return;
    }

    require_login($course, true, $context->contextlevel == CONTEXT_MODULE ? $cm : null);

    $postid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();

    // FIX: Separate assignment and evaluation for the second fallback area too
    $file = $fs->get_file($context->id, 'mod_learninglog', $filearea, $postid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

