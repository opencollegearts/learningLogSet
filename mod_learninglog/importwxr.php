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
require_once($CFG->libdir . '/filelib.php');
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

$viewparams = $id ? ['id' => $cm->id] : ['courseid' => $course->id];
$PAGE->set_url('/mod/learninglog/importwxr.php', $viewparams);
$PAGE->set_title(get_string('importwxrheading', 'mod_learninglog'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$postcount = 0;
$imagecount = 0;
$error = '';

if (optional_param('import', 0, PARAM_BOOL) && confirm_sesskey()) {
    if (!isset($_FILES['wxrfile']) || empty($_FILES['wxrfile']['tmp_name'])) {
        $error = get_string('importwxrnofile', 'mod_learninglog');
    } else {
        $tmpname = $_FILES['wxrfile']['tmp_name'];
        $xmlcontent = file_get_contents($tmpname);
        if ($xmlcontent === false || trim($xmlcontent) === '') {
            $error = get_string('importwxrinvalid', 'mod_learninglog');
        } else {
            // Strip UTF-8 BOM and any leading non-XML content (e.g. before <?xml or <rss>).
            $xmlcontent = ltrim($xmlcontent);
            if (substr($xmlcontent, 0, 3) === "\xef\xbb\xbf") {
                $xmlcontent = substr($xmlcontent, 3);
            }
            $start = null;
            if (preg_match('/\G\s*<\?xml/i', $xmlcontent) === 1) {
                $start = 0;
            } else {
                $rsspos = stripos($xmlcontent, '<rss');
                if ($rsspos !== false) {
                    $start = $rsspos;
                }
            }
            if ($start !== null && $start > 0) {
                $xmlcontent = substr($xmlcontent, $start);
            }

            // Escape ]]> inside CDATA sections so the parser does not treat it as end-of-CDATA.
            // (Post content can contain ]]> e.g. in "Accessibility and Inclusivity" or code.)
            // We identify the real terminator by what follows: ]]> then <![CDATA[ (next section) or ]]> then </ (closing tag).
            $cdataopen = '<![CDATA[';
            $cdataclose = ']]>';
            $cdataescap = ']]]]><![CDATA[>';
            $pos = 0;
            $len = strlen($xmlcontent);
            while (($start = strpos($xmlcontent, $cdataopen, $pos)) !== false) {
                $contentstart = $start + strlen($cdataopen);
                // Terminator is ]]> followed by <![CDATA[ or by </ (closing tag like </content:encoded>).
                $term1 = strpos($xmlcontent, ']]><![CDATA[', $contentstart);
                $term2 = strpos($xmlcontent, ']]></', $contentstart);
                $terminatorpos = $len;
                if ($term1 !== false) {
                    $terminatorpos = $term1;
                }
                if ($term2 !== false && $term2 < $terminatorpos) {
                    $terminatorpos = $term2;
                }
                if ($terminatorpos === $len) {
                    // No clear terminator pattern; use last ]]> before next <![CDATA[ as fallback.
                    $nextcdata = strpos($xmlcontent, $cdataopen, $contentstart);
                    $searchlimit = $nextcdata !== false ? $nextcdata : $len;
                    $positions = [];
                    $searchfrom = $contentstart;
                    while ($searchfrom < $searchlimit && ($end = strpos($xmlcontent, $cdataclose, $searchfrom)) !== false && $end < $searchlimit) {
                        $positions[] = $end;
                        $searchfrom = $end + 1;
                    }
                    $n = count($positions);
                    if ($n > 1) {
                        for ($i = $n - 2; $i >= 0; $i--) {
                            $p = $positions[$i];
                            $xmlcontent = substr_replace($xmlcontent, $cdataescap, $p, strlen($cdataclose));
                        }
                    }
                    $pos = $n > 0 ? $positions[$n - 1] + (($n > 1) ? ($n - 1) * (strlen($cdataescap) - strlen($cdataclose)) : 0) + strlen($cdataclose) : $searchlimit;
                } else {
                    // Replace every ]]> strictly before the terminator.
                    $positions = [];
                    $searchfrom = $contentstart;
                    while ($searchfrom < $terminatorpos && ($end = strpos($xmlcontent, $cdataclose, $searchfrom)) !== false && $end < $terminatorpos) {
                        $positions[] = $end;
                        $searchfrom = $end + 1;
                    }
                    $n = count($positions);
                    if ($n > 0) {
                        for ($i = $n - 1; $i >= 0; $i--) {
                            $p = $positions[$i];
                            $xmlcontent = substr_replace($xmlcontent, $cdataescap, $p, strlen($cdataclose));
                        }
                        $inserted = $n * (strlen($cdataescap) - strlen($cdataclose));
                        $pos = $terminatorpos + $inserted + strlen($cdataclose);
                    } else {
                        $pos = $terminatorpos + strlen($cdataclose);
                    }
                }
            }

            // Try to parse as SimpleXML, including CDATA.
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlcontent, 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml === false) {
                $xmlerrors = libxml_get_errors();
                libxml_clear_errors();
                if (debugging('', DEBUG_DEVELOPER) && !empty($xmlerrors)) {
                    $first = reset($xmlerrors);
                    $error = get_string('importwxrinvalid', 'mod_learninglog') . ' (' . trim($first->message) . ')';
                } else {
                    $error = get_string('importwxrinvalid', 'mod_learninglog');
                }
            } else if (!isset($xml->channel)) {
                $error = get_string('importwxrinvalid', 'mod_learninglog');
            } else {
                // Close session before long-running import to avoid "session mutated after close" warnings.
                session_write_close();

                $namespaces = $xml->getNamespaces(true);
                // WordPress WXR namespace URIs; children() expects URI with $isPrefix = false.
                $wpns = $namespaces['wp'] ?? 'http://wordpress.org/export/1.2/';
                $contentns = $namespaces['content'] ?? 'http://purl.org/rss/1.0/modules/content/';

                $channel = $xml->channel;
                $channel->registerXPathNamespace('wp', $wpns);

                $learninglogid = !empty($userlog->learninglogid) ? (int)$userlog->learninglogid : null;
                $fs = get_file_storage();

                // Map WXR category slug => learninglog_categories.id.
                $categorymap = [];

                // Helper to create or reuse a category for this learning log/user.
                $create_or_get_category = function(string $slug, string $name) use (
                    &$categorymap,
                    $learninglogid,
                    $USER,
                    $DB
                ): ?int {
                    if ($learninglogid === null) {
                        // No activity-linked learning log: skip custom categories.
                        return null;
                    }
                    if (isset($categorymap[$slug])) {
                        return $categorymap[$slug];
                    }
                    // Try to find existing category by slug or name.
                    $params = [
                        'learninglogid' => $learninglogid,
                        'userid' => $USER->id,
                    ];
                    $existing = $DB->get_records('learninglog_categories', $params);
                    foreach ($existing as $cat) {
                        if ($cat->slug === $slug || format_string($cat->name) === $name) {
                            $categorymap[$slug] = (int)$cat->id;
                            return (int)$cat->id;
                        }
                    }
                    $record = (object)[
                        'learninglogid' => $learninglogid,
                        'userid' => $USER->id,
                        'parentid' => null,
                        'name' => $name,
                        'slug' => $slug,
                        'sortorder' => 0,
                        'timecreated' => time(),
                        'timemodified' => time(),
                    ];
                    $record->id = $DB->insert_record('learninglog_categories', $record);
                    $categorymap[$slug] = (int)$record->id;
                    return (int)$record->id;
                };

                // Pre-create categories from top-level <wp:category> elements.
                foreach ($channel->xpath('wp:category') as $wpcategory) {
                    $wpchild = $wpcategory->children($wpns, false);
                    $slug = (string)$wpchild->category_nicename;
                    $name = (string)$wpchild->cat_name;
                    if ($slug !== '' && $name !== '') {
                        $create_or_get_category($slug, $name);
                    }
                }

                // First pass: create posts and collect attachment items.
                $postidmap = []; // WXR post_id => new learninglog_posts.id.
                $attachments = []; // Attachment SimpleXML elements.

                foreach ($channel->item as $item) {
                    $wp = $item->children($wpns, false);
                    $content = $item->children($contentns, false);

                    $type = (string)$wp->post_type;
                    $wxrpostid = (int)$wp->post_id;

                    if ($type === 'post') {
                        $title = (string)$item->title;
                        $html = $content && isset($content->encoded) ? (string)$content->encoded : '';
                        $status = core_text::strtolower((string)$wp->status);
                        $date = (string)$wp->post_date;
                        $timestamp = $date ? strtotime($date) : time();
                        if ($timestamp <= 0) {
                            $timestamp = time();
                        }

                        // Map WXR status to learninglog status.
                        switch ($status) {
                            case 'publish':
                                $llstatus = 'published';
                                break;
                            case 'private':
                                $llstatus = 'personalresearch';
                                break;
                            case 'draft':
                            default:
                                $llstatus = 'draft';
                                break;
                        }

                        $record = new stdClass();
                        $record->learninglogid = $learninglogid;
                        $record->userid = $USER->id;
                        $record->courseid = $course->id;
                        $record->sectionid = null;
                        $record->title = core_text::substr($title, 0, 255);
                        $record->content = $html;
                        $record->contentformat = FORMAT_HTML;
                        $record->status = $llstatus;
                        // Default visibility: keep within course (user can change later).
                        $record->visibility = 'course';
                        $record->timecreated = $timestamp;
                        $record->timemodified = $timestamp;

                        $newpostid = $DB->insert_record('learninglog_posts', $record);
                        $postidmap[$wxrpostid] = $newpostid;
                        $postcount++;

                        // Attach first category (if any) as custom category.
                        if ($learninglogid !== null) {
                            foreach ($item->category as $cat) {
                                $domain = (string)$cat['domain'];
                                if ($domain !== 'category') {
                                    continue;
                                }
                                $slug = (string)$cat['nicename'];
                                $name = (string)$cat;
                                if ($slug === '' && $name === '') {
                                    continue;
                                }
                                if ($slug === '') {
                                    $slug = core_text::strtolower(preg_replace('/[^a-z0-9]+/', '-', clean_param($name, PARAM_TEXT)));
                                }
                                $catid = $create_or_get_category($slug, $name);
                                if ($catid) {
                                    $map = new stdClass();
                                    $map->postid = $newpostid;
                                    $map->categoryid = $catid;
                                    $DB->insert_record('learninglog_postcats', $map);
                                }
                                // Only one custom category per post for now.
                                break;
                            }
                        }
                    } else if ($type === 'attachment') {
                        $attachments[] = $item;
                    }
                }

                // Map any URL that may appear in post content (guid, wp:attachment_url) -> preferred display URL (S3 when available).
                // Embedded images will keep pointing to the external URL (e.g. S3); we do not store them in Moodle.
                $url_to_display_url = [];
                $skipimagecount = 0;

                // Second pass: for each attachment, build URL map and optionally download as banner only.
                foreach ($attachments as $item) {
                    $wp = $item->children($wpns, false);
                    $wxrpostid = (int)$wp->post_id;
                    $parentwxr = (int)$wp->post_parent;
                    if (empty($parentwxr) || !isset($postidmap[$parentwxr])) {
                        continue;
                    }
                    $postid = $postidmap[$parentwxr];
                    $post = $DB->get_record('learninglog_posts', ['id' => $postid, 'userid' => $USER->id, 'courseid' => $course->id]);
                    if (!$post) {
                        continue;
                    }

                    // Prefer wp:attachment_url (e.g. S3) as the display URL so embedded images point to the serving URL.
                    $attachmenturl = trim((string)($wp->attachment_url ?? ''));
                    $guidurl = trim((string)($item->guid ?? ''));
                    $displayurl = $attachmenturl !== '' ? $attachmenturl : $guidurl;
                    $downloadurl = $displayurl;
                    if ($displayurl === '') {
                        continue;
                    }

                    // Map both variants so post content (which may contain guid or S3 URL) rewrites to the preferred display URL.
                    $url_to_display_url[$displayurl] = $displayurl;
                    if ($guidurl !== '') {
                        $url_to_display_url[$guidurl] = $displayurl;
                    }
                    if ($attachmenturl !== '' && $attachmenturl !== $guidurl) {
                        $url_to_display_url[$attachmenturl] = $displayurl;
                    }

                    // Try to download for banner only; on failure skip storing but content will still use displayurl (S3).
                    $curl = new curl();
                    $curl->setopt([
                        'CURLOPT_FOLLOWLOCATION' => true,
                        'CURLOPT_TIMEOUT' => 60,
                        'CURLOPT_CONNECTTIMEOUT' => 20,
                    ]);
                    $filedata = $curl->get($downloadurl);
                    $info = $curl->get_info();
                    $httpcode = $info['http_code'] ?? 0;
                    if ($filedata === false || $filedata === '' || $httpcode < 200 || $httpcode >= 300) {
                        $skipimagecount++;
                        continue;
                    }

                    $path = parse_url($downloadurl, PHP_URL_PATH);
                    $basename = $path ? basename($path) : ('attachment-' . $wxrpostid);
                    $ext = pathinfo($basename, PATHINFO_EXTENSION);
                    if ($ext === '') {
                        $ext = 'jpg';
                    }

                    $postcontext = learninglog_get_context_for_post($post, $course);

                    // Banner only: overwrite so last successfully downloaded attachment for this post becomes the banner.
                    $fs->delete_area_files($postcontext->id, 'mod_learninglog', 'banner', $postid);
                    $fs->create_file_from_string([
                        'contextid' => $postcontext->id,
                        'component' => 'mod_learninglog',
                        'filearea' => 'banner',
                        'itemid' => $postid,
                        'filepath' => '/',
                        'filename' => $basename,
                    ], $filedata);

                    $imagecount++;
                }

                // Third pass: rewrite post content so embedded img src use the preferred display URL (e.g. S3, not local/origin).
                foreach ($postidmap as $wxrpostid => $postid) {
                    $post = $DB->get_record('learninglog_posts', ['id' => $postid, 'userid' => $USER->id, 'courseid' => $course->id]);
                    if (!$post || trim($post->content) === '') {
                        continue;
                    }
                    $content = $post->content;
                    foreach ($url_to_display_url as $oldurl => $displayurl) {
                        $content = str_replace($oldurl, $displayurl, $content);
                    }
                    // Additionally, normalise any remaining legacy spaces.oca.ac.uk URLs that follow the pattern:
                    // https://spaces.oca.ac.uk/{siteslug}/wp-content/uploads/...  ->  https://oca-wp-journals.s3.eu-west-2.amazonaws.com/wp-content/uploads/...
                    $spacespattern = '#https://spaces\.oca\.ac\.uk/[^"\']*/(wp-content/uploads/[^\s"\']+)#i';
                    $spacesreplacement = 'https://oca-wp-journals.s3.eu-west-2.amazonaws.com/$1';
                    $content = preg_replace($spacespattern, $spacesreplacement, $content);
                    if ($content !== $post->content) {
                        $DB->update_record('learninglog_posts', (object)['id' => $postid, 'content' => $content]);
                    }
                }

                if ($postcount > 0) {
                    $a = (object)[
                        'postcount' => $postcount,
                        'imagecount' => $imagecount,
                    ];
                    $message = get_string('importwxrsuccess', 'mod_learninglog', $a);
                    if ($skipimagecount > 0) {
                        $message .= ' ' . get_string('importwxrskippedimages', 'mod_learninglog', $skipimagecount);
                    }
                    redirect(
                        new moodle_url('/mod/learninglog/view.php', $viewparams),
                        $message
                    );
                }
            }
        }
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importwxrheading', 'mod_learninglog'), 2);

if ($error !== '') {
    echo $OUTPUT->notification($error, \core\output\notification::NOTIFY_ERROR);
}

echo html_writer::tag('p', get_string('importwxrchoosefile', 'mod_learninglog'));

$form = html_writer::start_tag('form', [
    'method' => 'post',
    'enctype' => 'multipart/form-data',
    'action' => $PAGE->url->out(false),
]);
$form .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
$form .= html_writer::empty_tag('input', ['type' => 'file', 'name' => 'wxrfile', 'accept' => '.xml']);
$form .= html_writer::empty_tag('br');
$form .= html_writer::empty_tag('br');
$form .= html_writer::empty_tag('input', [
    'type' => 'submit',
    'name' => 'import',
    'value' => get_string('importwxrbutton', 'mod_learninglog'),
    'class' => 'btn btn-primary',
]);
$form .= html_writer::end_tag('form');

echo $form;

echo $OUTPUT->footer();

