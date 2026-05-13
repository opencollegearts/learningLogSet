<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Get the Google Site template ID for a course (course-level or site default).
 *
 * @param int $courseid
 * @return string|null Template ID or null if not configured
 */
function block_google_site_creator_get_template_id(int $courseid): ?string {
    global $DB;
    $course = $DB->get_record('block_google_site_creator_course', ['courseid' => $courseid], 'template_id');
    if ($course && !empty(trim($course->template_id ?? ''))) {
        return trim($course->template_id);
    }
    $default = get_config('block_google_site_creator', 'defaulttemplate');
    return ($default !== false && trim($default) !== '') ? trim($default) : null;
}

/**
 * Get the Unit Group email for a course.
 *
 * @param int $courseid
 * @return string|null Unit group email or null
 */
function block_google_site_creator_get_unit_group(int $courseid): ?string {
    global $DB;
    $course = $DB->get_record('block_google_site_creator_course', ['courseid' => $courseid], 'unit_group_email');
    if ($course && !empty(trim($course->unit_group_email ?? ''))) {
        return trim($course->unit_group_email);
    }
    return null;
}

/**
 * Visibility option: share with tutors only (oca-tutors@oca.ac.uk).
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTORS = 'tutors';

/**
 * Visibility option: share with unit group (course setting).
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_UNIT_GROUP = 'unit_group';

/**
 * Visibility option: share with everyone@oca.ac.uk (All of OCA).
 */
const BLOCK_GOOGLE_SITE_CREATOR_VIS_ALL_OCA = 'all_oca';

/**
 * Resolve the sharing email for a visibility setting.
 *
 * @param string $visibility tutors|unit_group|all_oca
 * @param int $courseid used for unit_group
 * @return string|null Email to share with, or null if invalid
 */
function block_google_site_creator_get_share_email(string $visibility, int $courseid): ?string {
    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_TUTORS) {
        return 'oca-tutors@oca.ac.uk';
    }
    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_ALL_OCA) {
        return 'everyone@oca.ac.uk';
    }
    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_UNIT_GROUP) {
        return block_google_site_creator_get_unit_group($courseid);
    }
    return null;
}

/**
 * Resolve Google permission role for a visibility option.
 *
 * @param string $visibility
 * @return string
 */
function block_google_site_creator_get_share_role(string $visibility): string {
    if ($visibility === BLOCK_GOOGLE_SITE_CREATOR_VIS_ALL_OCA) {
        return 'reader';
    }
    return 'writer';
}

/**
 * Resolve Google permission type for a visibility option.
 *
 * @param string $visibility
 * @return string
 */
function block_google_site_creator_get_share_type(string $visibility): string {
    // These are group emails at OCA.
    return 'group';
}

/**
 * Get all Google Sites visible site-wide (visibility = all_oca) for discovery alongside learning logs.
 *
 * @param int $limit
 * @return stdClass[]
 */
function block_google_site_creator_get_sites_for_index(int $limit = 100): array {
    global $DB;
    return $DB->get_records(
        'block_google_site_creator_sites',
        ['visibility' => BLOCK_GOOGLE_SITE_CREATOR_VIS_ALL_OCA],
        'timecreated DESC',
        '*',
        0,
        $limit
    );
}

/**
 * Build card objects for Google Sites for use with post_grid template (same shape as learning log cards).
 *
 * @param stdClass[] $sites
 * @return stdClass[]
 */
function block_google_site_creator_build_cards_for_grid(array $sites): array {
    global $OUTPUT;
    $cards = [];
    foreach ($sites as $site) {
        $user = \core_user::get_user($site->userid, '*', MUST_EXIST);
        $card = new stdClass();
        $card->id = $site->id;
        $card->title = format_string($site->title);
        $card->summary = !empty($site->description)
            ? shorten_text(strip_tags((string)$site->description), 200)
            : get_string('pluginname', 'block_google_site_creator');
        $card->date = userdate($site->timecreated);
        $card->url = $site->url;
        $card->userpicture = $OUTPUT->user_picture($user, ['size' => 48, 'link' => false]);
        $card->fullname = fullname($user);
        $card->status = get_string('pluginname', 'block_google_site_creator');
        $card->bannerurl = block_google_site_creator_get_site_banner_url($site);
        $card->sorttime = $site->timecreated;
        $cards[] = $card;
    }
    return $cards;
}

/**
 * Get service account JSON (path or inline). Returns decoded array or null.
 *
 * @return array|null
 */
function block_google_site_creator_get_service_account_json(): ?array {
    $config = get_config('block_google_site_creator', 'serviceaccountjson');
    if (empty($config)) {
        return null;
    }
    $config = trim($config);
    if (strpos($config, '{') === 0) {
        $decoded = json_decode($config, true);
        return is_array($decoded) ? $decoded : null;
    }
    global $CFG;
    $path = $config;
    if (!str_starts_with($path, '/')) {
        $path = $CFG->dataroot . '/' . $path;
    }
    if (!is_readable($path)) {
        return null;
    }
    $content = file_get_contents($path);
    $decoded = json_decode($content, true);
    return is_array($decoded) ? $decoded : null;
}

/**
 * Get delegated Workspace user email (domain-wide delegation subject), if configured.
 *
 * @return string|null
 */
function block_google_site_creator_get_delegated_user(): ?string {
    $email = get_config('block_google_site_creator', 'delegateduser');
    if ($email === false) {
        return null;
    }
    $email = trim((string)$email);
    return $email !== '' ? $email : null;
}

/**
 * Check whether a Moodle user email is eligible for ownership transfer.
 *
 * @param string|null $email
 * @return bool
 */
function block_google_site_creator_can_transfer_to_user_email(?string $email): bool {
    $email = trim((string)$email);
    if ($email === '') {
        return false;
    }
    return stripos($email, '@oca.ac.uk') !== false;
}

/**
 * Get a user's created Google Site for a course.
 *
 * @param int $userid
 * @param int $courseid
 * @return stdClass|null
 */
function block_google_site_creator_get_user_site_for_course(int $userid, int $courseid): ?stdClass {
    global $DB;
    $record = $DB->get_record(
        'block_google_site_creator_sites',
        ['userid' => $userid, 'courseid' => $courseid],
        '*',
        IGNORE_MULTIPLE
    );
    return $record ?: null;
}

/**
 * Sync a file's visibility permission by removing known group permissions and applying selected one.
 *
 * @param \block_google_site_creator\drive_service $drive
 * @param string $fileid
 * @param int $courseid
 * @param string $visibility
 * @return void
 */
function block_google_site_creator_sync_visibility_permission(
    \block_google_site_creator\drive_service $drive,
    string $fileid,
    int $courseid,
    string $visibility
): void {
    $emails = array_filter([
        'oca-tutors@oca.ac.uk',
        'everyone@oca.ac.uk',
        block_google_site_creator_get_unit_group($courseid),
    ]);
    $knownemails = [];
    foreach ($emails as $email) {
        $knownemails[strtolower(trim($email))] = true;
    }

    $permissions = $drive->list_permissions($fileid);
    foreach ($permissions as $permission) {
        $email = strtolower(trim((string)($permission['emailAddress'] ?? '')));
        $permissionid = (string)($permission['id'] ?? '');
        if ($email !== '' && $permissionid !== '' && isset($knownemails[$email])) {
            $drive->delete_permission($fileid, $permissionid);
        }
    }

    $shareemail = block_google_site_creator_get_share_email($visibility, $courseid);
    if (!empty($shareemail)) {
        $drive->add_permission(
            $fileid,
            $shareemail,
            block_google_site_creator_get_share_role($visibility),
            block_google_site_creator_get_share_type($visibility)
        );
    }
}

/**
 * Save site banner image from draft area.
 *
 * @param context_course $context
 * @param int $siteid
 * @param int $draftitemid
 * @return void
 */
function block_google_site_creator_save_site_banner(context_course $context, int $siteid, int $draftitemid): void {
    file_save_draft_area_files(
        $draftitemid,
        $context->id,
        'block_google_site_creator',
        'sitebanner',
        $siteid,
        [
            'subdirs' => 0,
            'maxfiles' => 1,
            'accepted_types' => ['image'],
        ]
    );
}

/**
 * Get banner image URL for a created Google Site record.
 *
 * @param stdClass $site
 * @return string|null
 */
function block_google_site_creator_get_site_banner_url(stdClass $site): ?string {
    $context = context_course::instance((int)$site->courseid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'block_google_site_creator', 'sitebanner', (int)$site->id, 'filename', false);
    if (!$files) {
        return null;
    }
    $file = reset($files);
    return moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename()
    )->out(false);
}

/**
 * File serving for Google Site Creator banner images.
 */
function block_google_site_creator_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel !== CONTEXT_COURSE) {
        return false;
    }
    if ($filearea !== 'sitebanner') {
        return false;
    }
    require_login($course, false);
    if (!has_capability('local/learninglog:vieworg', context_system::instance())
        && !has_capability('block/google_site_creator:create', $context)
        && !has_capability('block/google_site_creator:managecourse', $context)) {
        return false;
    }
    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';
    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'block_google_site_creator', 'sitebanner', $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }
    send_stored_file($file, 86400, 0, $forcedownload, $options);
}
