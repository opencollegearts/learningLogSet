<?php
// This file is part of Moodle - http://moodle.org/

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/formslib.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/form/create_site_form.php');
require_once(__DIR__ . '/classes/drive_service.php');

$courseid = required_param('courseid', PARAM_INT);

global $DB, $USER;

$course = get_course($courseid);
require_login($course);
$context = context_course::instance($course->id);
require_capability('block/google_site_creator:create', $context);

$PAGE->set_url(new moodle_url('/blocks/google_site_creator/create.php', ['courseid' => $course->id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('creategooglesite', 'block_google_site_creator'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->navbar->add(get_string('creategooglesite', 'block_google_site_creator'));

$templateid = block_google_site_creator_get_template_id($course->id);
if (empty($templateid)) {
    throw new moodle_exception('notemplate', 'block_google_site_creator');
}

$bannerdraftitemid = file_get_submitted_draft_itemid('banner');
file_prepare_draft_area(
    $bannerdraftitemid,
    $context->id,
    'block_google_site_creator',
    'sitebanner',
    0,
    ['subdirs' => 0, 'maxfiles' => 1, 'accepted_types' => ['image']]
);

$form = new block_google_site_creator_create_site_form(
    new moodle_url('/blocks/google_site_creator/create.php', ['courseid' => $course->id]),
    [
        'courseid' => $course->id,
        'bannerdraftitemid' => $bannerdraftitemid,
    ]
);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

if ($data = $form->get_data()) {
    $visibility = $data->visibility;
    $shareemail = block_google_site_creator_get_share_email($visibility, $course->id);
    if (empty($shareemail)) {
        if ($visibility === 'unit_group') {
            \core\notification::error(get_string('nocourseconfig', 'block_google_site_creator'));
        } else {
            \core\notification::error(get_string('createfailed', 'block_google_site_creator'));
        }
        redirect($PAGE->url);
    }

    $credentials = block_google_site_creator_get_service_account_json();
    if (empty($credentials)) {
        \core\notification::error(get_string('createfailed', 'block_google_site_creator'));
        redirect($PAGE->url);
    }

    try {
        $drive = new \block_google_site_creator\drive_service(
            $credentials,
            block_google_site_creator_get_delegated_user()
        );
        $delegateduser = block_google_site_creator_get_delegated_user();
        $foldername = $course->shortname;
        $folderid = $drive->ensure_folder_by_name($foldername);
        $copy = $drive->copy_file($templateid, $data->title, $folderid);
        $newfileid = $copy['id'] ?? null;
        $url = $copy['webViewLink'] ?? $copy['webContentLink'] ?? 'https://drive.google.com/file/d/' . $newfileid . '/view';
        if (empty($newfileid)) {
            throw new moodle_exception('createfailed', 'block_google_site_creator');
        }
        $sharerole = block_google_site_creator_get_share_role($visibility);
        $sharetype = block_google_site_creator_get_share_type($visibility);
        $drive->add_permission($newfileid, $shareemail, $sharerole, $sharetype);
        if (!empty(trim((string)$data->description))) {
            $drive->update_file_metadata($newfileid, ['description' => trim((string)$data->description)]);
        }

        $owneremail = trim((string)($USER->email ?? ''));
        if (block_google_site_creator_can_transfer_to_user_email($owneremail)) {
            // Ensure the creator always gets edit access, even if ownership transfer fails.
            if (!empty($delegateduser) && strcasecmp($delegateduser, $owneremail) !== 0) {
                try {
                    $drive->add_permission($newfileid, $owneremail, 'writer');
                    if (!empty($folderid)) {
                        $drive->add_permission($folderid, $owneremail, 'writer');
                    }
                } catch (Exception $addowneraseditore) {
                    debugging('Adding creator as editor failed: ' . $addowneraseditore->getMessage(), DEBUG_DEVELOPER);
                }
            }

            try {
                $drive->transfer_ownership($newfileid, $owneremail);
            } catch (Exception $siteownershipe) {
                debugging('Site ownership transfer failed: ' . $siteownershipe->getMessage(), DEBUG_DEVELOPER);
            }

            if (!empty($folderid)) {
                try {
                    $drive->transfer_ownership($folderid, $owneremail);
                } catch (Exception $folderownershipe) {
                    debugging('Folder ownership transfer failed: ' . $folderownershipe->getMessage(), DEBUG_DEVELOPER);
                }
            }

            try {
                if (!empty($delegateduser) && strcasecmp($delegateduser, $owneremail) !== 0) {
                    $drive->add_permission($newfileid, $delegateduser, 'writer');
                    if (!empty($folderid)) {
                        $drive->add_permission($folderid, $delegateduser, 'writer');
                    }
                }
            } catch (Exception $keepdelegatededitore) {
                debugging('Keeping delegated user as editor failed: ' . $keepdelegatededitore->getMessage(), DEBUG_DEVELOPER);
            }
        }

        $record = (object)[
            'userid' => $USER->id,
            'courseid' => $course->id,
            'title' => $data->title,
            'description' => trim((string)($data->description ?? '')),
            'drive_file_id' => $newfileid,
            'url' => $url,
            'visibility' => $visibility,
            'timecreated' => time(),
            'timemodified' => time(),
        ];
        $record->id = $DB->insert_record('block_google_site_creator_sites', $record);
        if (isset($data->banner)) {
            block_google_site_creator_save_site_banner($context, (int)$record->id, (int)$data->banner);
        }
        \core\notification::success(get_string('createsuccess', 'block_google_site_creator'));
    } catch (Exception $e) {
        \core\notification::error(get_string('createfailed', 'block_google_site_creator') . ' ' . $e->getMessage());
    }
    redirect(new moodle_url('/course/view.php', ['id' => $course->id]));
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('creategooglesite', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
