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

require('../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = optional_param('id', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Resolve course and context in the same way as view.php.
$cm = null;
$learninglog = null;

if ($id) {
    $cmrecord = get_coursemodule_from_id('learninglog', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cmrecord->course], '*', MUST_EXIST);
    $learninglog = $DB->get_record('learninglog', ['id' => $cmrecord->instance], '*', MUST_EXIST);
    require_login($course, true, $cmrecord);
    $modinfo = get_fast_modinfo($course);
    $cm = $modinfo->get_cm($cmrecord->id);
    $context = context_module::instance($cm->id);
} elseif ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    require_login($course, true);
    $context = context_course::instance($course->id);
} else {
    throw new moodle_exception('missingparam', 'error', '', 'id or courseid');
}

require_capability('mod/learninglog:write', $context);

// Ensure the user has a learning log for this course.
$userlog = learninglog_get_user_log($USER->id, $course->id);
if (!$userlog) {
    throw new moodle_exception('createfirst', 'mod_learninglog');
}

// Collect posts for this user's learning log in this course.
$posts = learninglog_get_user_posts_for_course($USER->id, $course->id, [
    'sort' => 'timecreated ASC',
]);

// Prepare category lookup (slug => ['id' => int, 'name' => string, 'slug' => string]).
$categories = [];
$nextcatid = 1;

// Helper to add a category and return its slug.
$add_category = function(string $slug, string $name) use (&$categories, &$nextcatid): string {
    if (isset($categories[$slug])) {
        return $slug;
    }
    $categories[$slug] = [
        'id' => $nextcatid++,
        'name' => $name,
        'slug' => $slug,
    ];
    return $slug;
};

// Build category list from sections and custom categories actually used by posts.
foreach ($posts as $post) {
    if (!empty($post->sectionid)) {
        $section = $DB->get_record('course_sections', ['id' => $post->sectionid, 'course' => $course->id]);
        if ($section) {
            $name = get_section_name($course, $section);
            $slug = 'section-' . $section->id;
            $add_category($slug, $name);
        }
        continue;
    }
    $pc = $DB->get_record('learninglog_postcats', ['postid' => $post->id]);
    if ($pc && !empty($userlog->learninglogid)) {
        $cat = $DB->get_record('learninglog_categories', [
            'id' => $pc->categoryid,
            'learninglogid' => $userlog->learninglogid,
        ]);
        if ($cat) {
            $name = format_string($cat->name);
            $slug = $cat->slug ?: core_text::strtolower(preg_replace('/[^a-z0-9]+/', '-', clean_param($name, PARAM_TEXT)));
            if ($slug === '') {
                $slug = 'cat-' . $cat->id;
            }
            $add_category($slug, $name);
        }
    }
}

// Collect banner files and discover embedded files in post content for attachment items.
$fs = get_file_storage();
$attachments = []; // Banner attachments: ['post' => post, 'file' => file].
$embeddedbypost = []; // postid => [ file, ... ] for embedded files in content.
$expiryseconds = 604800; // 7 days for token URLs.

foreach ($posts as $post) {
    $postcontext = learninglog_get_context_for_post($post, $course);
    $files = $fs->get_area_files(
        $postcontext->id,
        'mod_learninglog',
        'banner',
        $post->id,
        'filename',
        false
    );
    if ($files) {
        $file = reset($files);
        $attachments[] = ['post' => $post, 'file' => $file];
    }

    // Find embedded pluginfile URLs in content (mod_learninglog only).
    $content = $post->content ?? '';
    if (preg_match_all('~' . preg_quote($CFG->wwwroot, '~') . '/pluginfile\.php/(\d+)/mod_learninglog/(banner|embedded)/(\d+)/([^"\'>\s]+)~u', $content, $matches, PREG_SET_ORDER)) {
        foreach ($matches as $m) {
            $ctxid = (int) $m[1];
            $area = $m[2];
            $itemid = (int) $m[3];
            $path = $m[4];
            $path = trim($path, '/');
            if ($path === '') {
                continue;
            }
            $parts = explode('/', $path);
            $filename = array_pop($parts);
            $filepath = $parts ? '/' . implode('/', $parts) . '/' : '/';
            $file = $fs->get_file($ctxid, 'mod_learninglog', $area, $itemid, $filepath, $filename);
            if ($file && !$file->is_directory()) {
                $key = $ctxid . '-' . $area . '-' . $itemid . '-' . $filepath . '-' . $filename;
                if (!isset($embeddedbypost[$post->id][$key])) {
                    $embeddedbypost[$post->id][$key] = $file;
                }
            }
        }
    }
}

// Prepare channel metadata.
$sitename = format_string($SITE->fullname);
$blogtitle = $course->shortname . ': ' . format_string($userlog->name);
$blogdescription = get_string('pluginname', 'mod_learninglog') . ' export from ' . $sitename;
$siteurl = $CFG->wwwroot;
$blogurl = (new moodle_url('/mod/learninglog/view.php', $id ? ['id' => $cm->id] : ['courseid' => $course->id]))->out(false);
$language = current_language();

$author = \core_user::get_user($USER->id, '*', MUST_EXIST);

// Helper for wrapping text in CDATA safely.
$cdata = function(string $text): string {
    $text = str_replace(']]>', ']]&gt;', $text);
    return '<![CDATA[' . $text . ']]>';
};

// Start output.
$filename = 'learninglog-' . $course->shortname . '-' . $USER->id . '-' . date('Ymd-His') . '.xml';

header('Content-Type: application/xml; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0"
    xmlns:excerpt="http://wordpress.org/export/1.2/excerpt/"
    xmlns:content="http://purl.org/rss/1.0/modules/content/"
    xmlns:wfw="http://wellformedweb.org/CommentAPI/"
    xmlns:dc="http://purl.org/dc/elements/1.1/"
    xmlns:wp="http://wordpress.org/export/1.2/"
>' . "\n";
echo "<channel>\n";
echo '  <title>' . htmlspecialchars($blogtitle, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</title>\n";
echo '  <link>' . htmlspecialchars($blogurl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</link>\n";
echo '  <description>' . htmlspecialchars($blogdescription, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</description>\n";
echo '  <language>' . htmlspecialchars($language, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</language>\n";
echo "  <wp:wxr_version>1.2</wp:wxr_version>\n";
echo '  <wp:base_site_url>' . htmlspecialchars($siteurl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:base_site_url>\n";
echo '  <wp:base_blog_url>' . htmlspecialchars($blogurl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:base_blog_url>\n";

// Single author (current user).
echo "  <wp:author>\n";
echo '    <wp:author_login>' . htmlspecialchars($author->username, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:author_login>\n";
echo '    <wp:author_email>' . htmlspecialchars($author->email, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:author_email>\n";
echo '    <wp:author_display_name>' . $cdata(fullname($author)) . "</wp:author_display_name>\n";
echo '    <wp:author_first_name>' . $cdata($author->firstname) . "</wp:author_first_name>\n";
echo '    <wp:author_last_name>' . $cdata($author->lastname) . "</wp:author_last_name>\n";
echo "  </wp:author>\n";

// Channel-level categories.
foreach ($categories as $cat) {
    echo "  <wp:category>\n";
    echo '    <wp:term_id>' . $cat['id'] . "</wp:term_id>\n";
    echo '    <wp:category_nicename>' . htmlspecialchars($cat['slug'], ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:category_nicename>\n";
    echo "    <wp:category_parent></wp:category_parent>\n";
    echo '    <wp:cat_name>' . $cdata($cat['name']) . "</wp:cat_name>\n";
    echo "  </wp:category>\n";
}

// Rewrite post content: replace pluginfile URLs with token URLs for media we export.
$rewrite_content_media_urls = function(string $content, stdClass $post) use ($fs, $embeddedbypost, $expiryseconds, $course): string {
    $replacements = [];
    $postcontext = learninglog_get_context_for_post($post, $course);
    foreach (['banner', 'embedded'] as $area) {
        $files = $fs->get_area_files($postcontext->id, 'mod_learninglog', $area, $post->id, 'filename', false);
        foreach ($files as $file) {
            if ($file->is_directory()) {
                continue;
            }
            $oldurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $replacements[$oldurl] = learninglog_export_media_token_url($file, $expiryseconds);
        }
    }
    if (isset($embeddedbypost[$post->id])) {
        foreach ($embeddedbypost[$post->id] as $file) {
            $oldurl = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                $file->get_itemid(),
                $file->get_filepath(),
                $file->get_filename()
            )->out(false);
            $replacements[$oldurl] = learninglog_export_media_token_url($file, $expiryseconds);
        }
    }
    foreach ($replacements as $old => $new) {
        $content = str_replace($old, $new, $content);
    }
    return $content;
};

// Helper to map internal status to WXR status.
$map_status = function(string $status): string {
    switch ($status) {
        case 'published':
            return 'publish';
        case 'personalresearch':
            return 'private';
        case 'draft':
        default:
            return 'draft';
    }
};

// Export posts.
foreach ($posts as $post) {
    $poststatus = $map_status($post->status);
    $postdate = date('Y-m-d H:i:s', $post->timecreated);
    $postdategmt = gmdate('Y-m-d H:i:s', $post->timecreated);
    $postlink = (new moodle_url('/mod/learninglog/view.php', ['courseid' => $course->id, 'postid' => $post->id]))->out(false);
    $guid = $postlink;

    // Determine category for this post (single, for now).
    $postcatname = '';
    $postcatslug = '';
    if (!empty($post->sectionid)) {
        $section = $DB->get_record('course_sections', ['id' => $post->sectionid, 'course' => $course->id]);
        if ($section) {
            $postcatname = get_section_name($course, $section);
            $postcatslug = 'section-' . $section->id;
        }
    } else {
        $pc = $DB->get_record('learninglog_postcats', ['postid' => $post->id]);
        if ($pc && !empty($userlog->learninglogid)) {
            $cat = $DB->get_record('learninglog_categories', [
                'id' => $pc->categoryid,
                'learninglogid' => $userlog->learninglogid,
            ]);
            if ($cat) {
                $postcatname = format_string($cat->name);
                $postcatslug = $cat->slug ?: core_text::strtolower(preg_replace('/[^a-z0-9]+/', '-', clean_param($postcatname, PARAM_TEXT)));
                if ($postcatslug === '') {
                    $postcatslug = 'cat-' . $cat->id;
                }
            }
        }
    }
    if ($postcatslug !== '' && $postcatname !== '') {
        $add_category($postcatslug, $postcatname);
    }

    echo "  <item>\n";
    echo '    <title>' . $cdata(format_string($post->title)) . "</title>\n";
    echo '    <link>' . htmlspecialchars($postlink, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</link>\n";
    echo '    <pubDate>' . date(DATE_RSS, $post->timecreated) . "</pubDate>\n";
    echo '    <dc:creator>' . $cdata($author->username) . "</dc:creator>\n";
    echo '    <guid isPermaLink="false">' . htmlspecialchars($guid, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</guid>\n";
    echo "    <description></description>\n";
    $postcontent = $rewrite_content_media_urls($post->content ?? '', $post);
    echo '    <content:encoded>' . $cdata($postcontent) . "</content:encoded>\n";
    echo "    <excerpt:encoded></excerpt:encoded>\n";
    echo '    <wp:post_id>' . (int)$post->id . "</wp:post_id>\n";
    echo '    <wp:post_date>' . $postdate . "</wp:post_date>\n";
    echo '    <wp:post_date_gmt>' . $postdategmt . "</wp:post_date_gmt>\n";
    echo "    <wp:comment_status>closed</wp:comment_status>\n";
    echo "    <wp:ping_status>closed</wp:ping_status>\n";
    echo '    <wp:post_name>' . htmlspecialchars('learning-log-post-' . $post->id, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:post_name>\n";
    echo '    <wp:status>' . $poststatus . "</wp:status>\n";
    echo "    <wp:post_parent>0</wp:post_parent>\n";
    echo "    <wp:menu_order>0</wp:menu_order>\n";
    echo "    <wp:post_type>post</wp:post_type>\n";
    echo "    <wp:post_password></wp:post_password>\n";
    echo "    <wp:is_sticky>0</wp:is_sticky>\n";
    if ($postcatslug !== '' && $postcatname !== '') {
        echo '    <category domain="category" nicename="' . htmlspecialchars($postcatslug, ENT_QUOTES | ENT_XML1, 'UTF-8') . '">' .
            $cdata($postcatname) . "</category>\n";
    }
    echo "  </item>\n";
}

// Export banner attachments (minimal media support).
foreach ($attachments as $entry) {
    $post = $entry['post'];
    $file = $entry['file'];

    $postlink = (new moodle_url('/mod/learninglog/view.php', ['courseid' => $course->id, 'postid' => $post->id]))->out(false);
    $guid = $file->get_contenthash();
    $fileurl = learninglog_export_media_token_url($file, $expiryseconds);

    $postdate = date('Y-m-d H:i:s', $post->timecreated);
    $postdategmt = gmdate('Y-m-d H:i:s', $post->timecreated);

    echo "  <item>\n";
    echo '    <title>' . $cdata($file->get_filename()) . "</title>\n";
    echo '    <link>' . htmlspecialchars($postlink, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</link>\n";
    echo '    <pubDate>' . date(DATE_RSS, $post->timecreated) . "</pubDate>\n";
    echo '    <dc:creator>' . $cdata($author->username) . "</dc:creator>\n";
    echo '    <guid isPermaLink="false">' . htmlspecialchars($guid, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</guid>\n";
    echo "    <description></description>\n";
    echo "    <content:encoded></content:encoded>\n";
    echo "    <excerpt:encoded></excerpt:encoded>\n";
    echo '    <wp:post_id>' . (int)$file->get_id() . "</wp:post_id>\n";
    echo '    <wp:post_date>' . $postdate . "</wp:post_date>\n";
    echo '    <wp:post_date_gmt>' . $postdategmt . "</wp:post_date_gmt>\n";
    echo "    <wp:comment_status>closed</wp:comment_status>\n";
    echo "    <wp:ping_status>closed</wp:ping_status>\n";
    echo '    <wp:post_name>' . htmlspecialchars('learning-log-attachment-' . $file->get_id(), ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:post_name>\n";
    echo "    <wp:status>inherit</wp:status>\n";
    echo '    <wp:post_parent>' . (int)$post->id . "</wp:post_parent>\n";
    echo "    <wp:menu_order>0</wp:menu_order>\n";
    echo "    <wp:post_type>attachment</wp:post_type>\n";
    echo "    <wp:post_password></wp:post_password>\n";
    echo "    <wp:is_sticky>0</wp:is_sticky>\n";
    echo '    <wp:attachment_url>' . htmlspecialchars($fileurl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:attachment_url>\n";
    echo "  </item>\n";
}

// Export embedded (in-content) media as attachment items so WordPress can use them.
$embeddedindex = 0;
foreach ($embeddedbypost as $postid => $files) {
    $post = null;
    foreach ($posts as $p) {
        if ((int) $p->id === (int) $postid) {
            $post = $p;
            break;
        }
    }
    if (!$post) {
        continue;
    }
    $postlink = (new moodle_url('/mod/learninglog/view.php', ['courseid' => $course->id, 'postid' => $post->id]))->out(false);
    $postdate = date('Y-m-d H:i:s', $post->timecreated);
    $postdategmt = gmdate('Y-m-d H:i:s', $post->timecreated);
    foreach ($files as $file) {
        $fileurl = learninglog_export_media_token_url($file, $expiryseconds);
        $guid = $file->get_contenthash();
        $embeddedindex++;
        $syntheticid = 1000000 + $embeddedindex;
        echo "  <item>\n";
        echo '    <title>' . $cdata($file->get_filename()) . "</title>\n";
        echo '    <link>' . htmlspecialchars($postlink, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</link>\n";
        echo '    <pubDate>' . date(DATE_RSS, $post->timecreated) . "</pubDate>\n";
        echo '    <dc:creator>' . $cdata($author->username) . "</dc:creator>\n";
        echo '    <guid isPermaLink="false">' . htmlspecialchars($guid, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</guid>\n";
        echo "    <description></description>\n";
        echo "    <content:encoded></content:encoded>\n";
        echo "    <excerpt:encoded></excerpt:encoded>\n";
        echo '    <wp:post_id>' . $syntheticid . "</wp:post_id>\n";
        echo '    <wp:post_date>' . $postdate . "</wp:post_date>\n";
        echo '    <wp:post_date_gmt>' . $postdategmt . "</wp:post_date_gmt>\n";
        echo "    <wp:comment_status>closed</wp:comment_status>\n";
        echo "    <wp:ping_status>closed</wp:ping_status>\n";
        echo '    <wp:post_name>' . htmlspecialchars('learning-log-embedded-' . $syntheticid, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:post_name>\n";
        echo "    <wp:status>inherit</wp:status>\n";
        echo '    <wp:post_parent>' . (int)$post->id . "</wp:post_parent>\n";
        echo "    <wp:menu_order>0</wp:menu_order>\n";
        echo "    <wp:post_type>attachment</wp:post_type>\n";
        echo "    <wp:post_password></wp:post_password>\n";
        echo "    <wp:is_sticky>0</wp:is_sticky>\n";
        echo '    <wp:attachment_url>' . htmlspecialchars($fileurl, ENT_QUOTES | ENT_XML1, 'UTF-8') . "</wp:attachment_url>\n";
        echo "  </item>\n";
    }
}

echo "</channel>\n";
echo "</rss>\n";

exit;

