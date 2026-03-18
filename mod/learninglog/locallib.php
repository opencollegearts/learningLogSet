<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Local helpers for mod_learninglog data access.
 *
 * @package   mod_learninglog
 */

/**
 * Get a learning log instance record.
 *
 * @param int $id
 * @return stdClass|false
 */
function learninglog_get_instance(int $id) {
    global $DB;
    return $DB->get_record('learninglog', ['id' => $id]);
}

/**
 * Check whether the user has created their learning log for this course (by userid + courseid only).
 *
 * @param int $userid
 * @param int $courseid
 * @return bool
 */
function learninglog_has_user_log_by_course(int $userid, int $courseid): bool {
    global $DB;
    return $DB->record_exists('learninglog_user_log', ['userid' => $userid, 'courseid' => $courseid]);
}

/**
 * Get the user's learning log record for a course (if any).
 *
 * @param int $userid
 * @param int $courseid
 * @return stdClass|false
 */
function learninglog_get_user_log(int $userid, int $courseid) {
    global $DB;
    return $DB->get_record('learninglog_user_log', ['userid' => $userid, 'courseid' => $courseid]);
}

/**
 * Check whether the user has a log for this course and (optionally) this activity.
 *
 * @param int|null $learninglogid
 * @param int $userid
 * @param int $courseid
 * @return bool
 */
function learninglog_has_user_log(?int $learninglogid, int $userid, int $courseid): bool {
    global $DB;
    $params = ['userid' => $userid, 'courseid' => $courseid];
    if ($learninglogid !== null) {
        $params['learninglogid'] = $learninglogid;
    }
    return $DB->record_exists('learninglog_user_log', $params);
}

/**
 * Create or update the user's learning log for this course.
 *
 * @param int $userid
 * @param int $courseid
 * @param string $name
 * @param string|null $description
 * @param int|null $learninglogid
 * @param bool $sitewide allow this log to be listed site-wide via local_learninglog (default for post visibility)
 * @return stdClass the user_log record
 */
function learninglog_create_user_log(int $userid, int $courseid, string $name, ?string $description = null, ?int $learninglogid = null, bool $sitewide = false): stdClass {
    global $DB;
    $existing = $DB->get_record('learninglog_user_log', ['userid' => $userid, 'courseid' => $courseid]);
    if ($existing) {
        $changed = false;
        if ($learninglogid !== null && empty($existing->learninglogid)) {
            $existing->learninglogid = $learninglogid;
            $changed = true;
        }
        if ($existing->name !== $name) {
            $existing->name = $name;
            $changed = true;
        }
        if (property_exists($existing, 'description') && $existing->description !== $description) {
            $existing->description = $description;
            $changed = true;
        }
        $newsitewide = $sitewide ? 1 : 0;
        if (property_exists($existing, 'sitewide') && (int)$existing->sitewide !== $newsitewide) {
            $existing->sitewide = $newsitewide;
            $changed = true;
        }
        if ($changed) {
            $DB->update_record('learninglog_user_log', $existing);
        }
        return $existing;
    }
    $record = (object)[
        'userid' => $userid,
        'courseid' => $courseid,
        'learninglogid' => $learninglogid,
        'name' => $name,
        'description' => $description,
        'sitewide' => $sitewide ? 1 : 0,
        'timecreated' => time(),
    ];
    $record->id = $DB->insert_record('learninglog_user_log', $record);

    // Create section-based categories at course level (one per section, learninglogid null).
    learninglog_ensure_section_categories_for_course($userid, $courseid);

    return $record;
}

/**
 * Ensure section-based category rows exist for this user/course (course-level: learninglogid null).
 * Creates one row per course section (except section 0) if missing.
 *
 * @param int $userid
 * @param int $courseid
 */
function learninglog_ensure_section_categories_for_course(int $userid, int $courseid): void {
    global $DB;
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
    $sort = 0;
    foreach ($sections as $section) {
        if ((int)$section->section === 0) {
            continue;
        }
        $exists = $DB->record_exists('learninglog_categories', [
            'userid' => $userid,
            'courseid' => $courseid,
            'learninglogid' => null,
            'sectionid' => $section->id,
        ]);
        if ($exists) {
            continue;
        }
        $cat = (object)[
            'courseid' => $courseid,
            'learninglogid' => null,
            'userid' => $userid,
            'parentid' => null,
            'sectionid' => $section->id,
            'name' => get_section_name($course, $section),
            'slug' => null,
            'sortorder' => $sort,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $DB->insert_record('learninglog_categories', $cat);
        $sort++;
    }
}

/**
 * Get posts for a learning log instance and user, optionally filtered by category.
 *
 * @param int $learninglogid
 * @param int $userid
 * @param array $options sectionid (int), categoryid (int), status, sort, limit
 * @return stdClass[]
 */
function learninglog_get_user_posts(int $learninglogid, int $userid, array $options = []): array {
    global $DB;

    $params = ['learninglogid' => $learninglogid, 'userid' => $userid];
    $conditions = 'learninglogid = :learninglogid AND userid = :userid';

    if (!empty($options['sectionid'])) {
        $conditions .= ' AND sectionid = :sectionid';
        $params['sectionid'] = $options['sectionid'];
    }
    if (!empty($options['categoryid'])) {
        $conditions .= ' AND id IN (SELECT postid FROM {learninglog_postcats} WHERE categoryid = :categoryid)';
        $params['categoryid'] = $options['categoryid'];
    }
    if (!empty($options['status'])) {
        list($insql, $inparams) = $DB->get_in_or_equal($options['status'], SQL_PARAMS_NAMED);
        $conditions .= " AND status $insql";
        $params += $inparams;
    }

    $sort = 'timemodified DESC';
    if (!empty($options['sort'])) {
        $sort = $options['sort'];
    }

    $limit = $options['limit'] ?? 0;

    return $DB->get_records_select('learninglog_posts', $conditions, $params, $sort, '*', 0, $limit);
}

/**
 * Get all posts for a user's learning log in a course (activity-linked and course-only posts).
 *
 * @param int $userid
 * @param int $courseid
 * @param array $options sectionid (int), categoryid (int), status, sort, limit
 * @return stdClass[]
 */
function learninglog_get_user_posts_for_course(int $userid, int $courseid, array $options = []): array {
    global $DB;

    // Course-level log: show all of the user's posts in this course,
    // regardless of which Learning Log activity (if any) they came from.
    $params = ['userid' => $userid, 'courseid' => $courseid];
    $conditions = 'userid = :userid AND courseid = :courseid';

    if (!empty($options['sectionid'])) {
        $conditions .= ' AND sectionid = :sectionid';
        $params['sectionid'] = $options['sectionid'];
    }
    if (!empty($options['categoryid'])) {
        $conditions .= ' AND id IN (SELECT postid FROM {learninglog_postcats} WHERE categoryid = :categoryid)';
        $params['categoryid'] = $options['categoryid'];
    }
    if (!empty($options['status'])) {
        list($insql, $inparams) = $DB->get_in_or_equal($options['status'], SQL_PARAMS_NAMED);
        $conditions .= " AND status $insql";
        $params += $inparams;
    }

    $sort = $options['sort'] ?? 'timemodified DESC';
    $limit = $options['limit'] ?? 0;

    return $DB->get_records_select('learninglog_posts', $conditions, $params, $sort, '*', 0, $limit);
}

/**
 * Get the file context for a post (course context when learninglogid is null, else module context).
 *
 * @param stdClass $post learninglog_posts row
 * @param stdClass $course
 * @return context
 */
function learninglog_get_context_for_post(stdClass $post, stdClass $course): context {
    if (empty($post->learninglogid)) {
        return context_course::instance($course->id);
    }
    $modinfo = get_fast_modinfo($course);
    $cms = $modinfo->get_instances_of('learninglog');
    foreach ($cms as $c) {
        if ($c->instance == $post->learninglogid) {
            return context_module::instance($c->id);
        }
    }
    return context_course::instance($course->id);
}

/**
 * Get the banner image URL for a learning log (user log), if set.
 *
 * @param stdClass $userlog learninglog_user_log record (must have id, courseid)
 * @return string|null URL to the banner image or null if none
 */
function learninglog_get_log_banner_url(stdClass $userlog): ?string {
    $ctx = context_course::instance($userlog->courseid);
    $fs = get_file_storage();
    $files = $fs->get_area_files($ctx->id, 'mod_learninglog', 'logbanner', $userlog->id, 'filename', false);
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
    )->out(true);
}

/**
 * Get or create the secret used for WXR export media token signing.
 *
 * @return string
 */
function learninglog_get_export_media_secret(): string {
    $secret = get_config('mod_learninglog', 'export_media_secret');
    if (empty($secret)) {
        $secret = bin2hex(random_bytes(32));
        set_config('export_media_secret', $secret, 'mod_learninglog');
    }
    return $secret;
}

/**
 * Build a token-based URL for a file so WordPress (or another consumer) can fetch it without Moodle login.
 * HMAC + expiry; the URL is valid until $expiryseconds from now.
 *
 * @param \stored_file $file
 * @param int $expiryseconds seconds until the link expires (default 7 days)
 * @return string absolute URL
 */
function learninglog_export_media_token_url(\stored_file $file, int $expiryseconds = 604800): string {
    global $CFG;
    $expiry = (string) (time() + $expiryseconds);
    $payload = json_encode([
        'c' => $file->get_contextid(),
        'co' => $file->get_component(),
        'a' => $file->get_filearea(),
        'i' => $file->get_itemid(),
        'p' => $file->get_filepath(),
        'n' => $file->get_filename(),
    ]);
    $f = strtr(base64_encode($payload), '+/', '-_');
    $f = rtrim($f, '=');
    $secret = learninglog_get_export_media_secret();
    $token = hash_hmac('sha256', $expiry . $f, $secret);
    $url = new moodle_url('/mod/learninglog/serve_export_media.php', [
        'token' => $token,
        'expiry' => $expiry,
        'f' => $f,
    ]);
    return $url->out(false);
}

/**
 * Get categories (sections + custom) with post counts for navigation.
 * Categories are course-level (userid + courseid, learninglogid null).
 *
 * @param stdClass $course
 * @param int $userid
 * @param int|null $learninglogid unused; kept for API compatibility
 * @return array [ ['key' => 'section_1', 'name' => '...', 'postcount' => N ], ... ]
 */
function learninglog_get_categories_for_nav(stdClass $course, int $userid, ?int $learninglogid = null): array {
    global $DB;

    $out = [];
    $userlog = learninglog_get_user_log($userid, $course->id);
    if (!$userlog) {
        return $out;
    }

    // All categories are course-level: userid + courseid, learninglogid IS NULL.
    $cats = $DB->get_records_select(
        'learninglog_categories',
        'userid = :userid AND courseid = :courseid AND learninglogid IS NULL',
        ['userid' => $userid, 'courseid' => $course->id],
        'sortorder ASC, name ASC'
    );

    foreach ($cats as $cat) {
        if (!empty($cat->sectionid)) {
            $count = $DB->count_records_sql(
                'SELECT COUNT(*) FROM {learninglog_posts} p
                 WHERE p.userid = :userid AND p.courseid = :courseid AND p.sectionid = :sectionid',
                ['userid' => $userid, 'courseid' => $course->id, 'sectionid' => $cat->sectionid]
            );
            $out[] = (object)[
                'key' => 'section_' . $cat->sectionid,
                'name' => format_string($cat->name),
                'postcount' => (int) $count,
            ];
        } else {
            $count = $DB->count_records_sql(
                'SELECT COUNT(*) FROM {learninglog_postcats} pc
                   JOIN {learninglog_posts} p ON p.id = pc.postid
                 WHERE pc.categoryid = :catid AND p.userid = :userid AND p.courseid = :courseid',
                ['catid' => $cat->id, 'userid' => $userid, 'courseid' => $course->id]
            );
            $out[] = (object)[
                'key' => 'cat_' . $cat->id,
                'name' => format_string($cat->name),
                'postcount' => (int) $count,
            ];
        }
    }

    return $out;
}

/**
 * Get section options for the post form (section id => section name).
 * Uses course section titles as default categories.
 *
 * @param stdClass $course
 * @return array [ sectionid => section name ]
 */
function learninglog_get_section_options(stdClass $course): array {
    global $DB;
    $sections = $DB->get_records('course_sections', ['course' => $course->id], 'section ASC');
    $options = [];
    foreach ($sections as $section) {
        if ($section->section == 0) {
            continue;
        }
        $options['section_' . $section->id] = get_section_name($course, $section);
    }
    return $options;
}

/**
 * Get category options for the post form (category id => name).
 * Course-level: pass $courseid (and $learninglogid can be null). Activity-level: pass $learninglogid.
 *
 * @param int|null $learninglogid activity instance id, or null for course-level
 * @param int $userid
 * @param int|null $courseid required when $learninglogid is null
 * @return array [ 'cat_N' => name, 'section_N' => name, ... ]
 */
function learninglog_get_user_category_options(?int $learninglogid, int $userid, ?int $courseid = null): array {
    global $DB;
    if ($courseid !== null) {
        $cats = $DB->get_records_select(
            'learninglog_categories',
            'userid = :userid AND courseid = :courseid AND learninglogid IS NULL',
            ['userid' => $userid, 'courseid' => $courseid],
            'sortorder ASC, name ASC'
        );
    } else {
        $cats = $DB->get_records('learninglog_categories', [
            'learninglogid' => $learninglogid,
            'userid' => $userid,
        ], 'sortorder ASC, name ASC');
    }
    $out = [];
    foreach ($cats as $c) {
        if (!empty($c->sectionid)) {
            $out['section_' . $c->sectionid] = format_string($c->name);
        } else {
            $out['cat_' . $c->id] = format_string($c->name);
        }
    }
    return $out;
}

/**
 * Build template context for the post grid (cards) for use with mod_learninglog/post_grid template.
 * Use this instead of the plugin renderer to avoid theme renderer lookup issues.
 *
 * @param stdClass $course
 * @param stdClass[] $posts
 * @param context $context current view context (module or course)
 * @param array $viewparams params for view URL: ['id' => cmid], ['courseid' => courseid], or ['_global' => true]
 * @return stdClass { cards: array of card objects }
 */
function learninglog_get_post_grid_context(stdClass $course, array $posts, \context $context, array $viewparams): stdClass {
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
        if (!empty($viewparams['_global'])) {
            $params = ['courseid' => (int)$post->courseid, 'postid' => $post->id];
        } else {
            $params = $viewparams + ['postid' => $post->id];
        }
        $card->url = (new moodle_url('/mod/learninglog/view.php', $params))->out(false);

        // Always load full user so fullname() has all required/optional name fields (avoids debugging warning).
        $user = \core_user::get_user($post->userid, '*', MUST_EXIST);
        $card->userpicture = $OUTPUT->user_picture($user, ['size' => 48, 'link' => false]);
        $card->fullname = fullname($user);

        $courseforcontext = (!empty($viewparams['_global'])) ? (object)['id' => $post->courseid] : $course;
        $postcontext = learninglog_get_context_for_post($post, $courseforcontext);
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
            $card->bannerurl = moodle_url::make_pluginfile_url(
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

/**
 * Build template context for the learning log grid (tiles) for use with mod_learninglog/log_grid template.
 * Each log record should have: userlogid, logname, userid, courseid, coursename, categoryid, firstname, lastname.
 *
 * @param array $logs array of stdClass from DB (with userlogid, logname, courseid, coursename, etc.)
 * @return stdClass { cards: array of card objects for template }
 */
function learninglog_get_log_grid_context(array $logs): stdClass {
    global $DB, $OUTPUT;
    $cards = [];
    foreach ($logs as $log) {
        $userlog = $DB->get_record('learninglog_user_log', ['id' => $log->userlogid], 'id, courseid, description');
        $card = new stdClass();
        $card->title = format_string($log->logname);
        $card->url = (new moodle_url('/local/learninglog/viewlog.php', ['userlogid' => $log->userlogid]))->out(false);
        $card->summary = $userlog && !empty($userlog->description)
            ? shorten_text(strip_tags($userlog->description), 200)
            : '';
        $card->coursename = format_string($log->coursename);
        $user = \core_user::get_user($log->userid, '*', MUST_EXIST);
        $card->fullname = fullname($user);
        $userlogforbanner = $userlog ?: (object)['id' => $log->userlogid, 'courseid' => $log->courseid];
        $card->bannerurl = learninglog_get_log_banner_url($userlogforbanner);
        $cards[] = $card;
    }
    $contextdata = new stdClass();
    $contextdata->cards = $cards;
    return $contextdata;
}

/**
 * Get the display name of a post's category (section or custom category).
 *
 * @param stdClass $post must have sectionid or be linked via learninglog_postcats
 * @param stdClass $course
 * @return string category name for display, or empty string if none
 */
function learninglog_get_post_category_display_name(stdClass $post, stdClass $course): string {
    global $DB;
    if (!empty($post->sectionid)) {
        $section = $DB->get_record('course_sections', ['id' => $post->sectionid, 'course' => $course->id]);
        return $section ? get_section_name($course, $section) : '';
    }
    $pc = $DB->get_record('learninglog_postcats', ['postid' => $post->id]);
    if ($pc) {
        $cat = $DB->get_record('learninglog_categories', ['id' => $pc->categoryid], 'name');
        return $cat ? format_string($cat->name) : '';
    }
    return '';
}

