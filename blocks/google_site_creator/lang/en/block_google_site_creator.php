<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Google Site Creator';
$string['google_site_creator:addinstance'] = 'Add a Google Site Creator block';
$string['google_site_creator:myaddinstance'] = 'Add a Google Site Creator block to Dashboard';
$string['google_site_creator:create'] = 'Create a Google Site';
$string['google_site_creator:managecourse'] = 'Manage course Google Site settings';
$string['google_site_creator:create_for_others'] = 'Create Google Site on behalf of another user (web service)';

$string['creategooglesite'] = 'Create a Google Site';
$string['mygooglesite'] = 'My Google Site';
$string['createsite'] = 'Create Google Site';
$string['sitetitle'] = 'Site title';
$string['sitetitle_help'] = 'The title for your new Google Site.';
$string['sitedescription'] = 'Site description';
$string['sitedescription_help'] = 'Optional description for this site listing and the Google Site file metadata.';
$string['sitebanner'] = 'Banner image';
$string['sitebanner_help'] = 'Optional banner image used in Learning Logs listing views.';
$string['visibility'] = 'Visibility';
$string['visibility_help'] = 'Who can view this site: Tutors only, your unit group (coursemates and tutors), or all of OCA.';
$string['visibility_tutors'] = 'Tutors';
$string['visibility_unit_group'] = 'Coursemates and Tutors';
$string['visibility_all_oca'] = 'All of OCA';
$string['save'] = 'Save';
$string['cancel'] = 'Cancel';
$string['coursesettings'] = 'Course settings';
$string['coursesettings_help'] = 'Set the unit-specific template and Unit Group email for this course.';
$string['pluginsettings'] = 'Plugin settings';
$string['instancebuttonlabel'] = 'Create button label';
$string['instancebuttonlabel_help'] = 'Optional label for the "Create a Google Site" button in this block instance.';
$string['defaulttemplate'] = 'Default Google Site Template ID';
$string['defaulttemplate_help'] = 'Site-level default Drive file ID of the Google Site template. Used when the course has no unit-specific template.';
$string['coursetemplate'] = 'Unit-specific Site Template ID';
$string['coursetemplate_help'] = 'Optional. Drive file ID of the template for this course. If empty, the site default is used.';
$string['unitgroup'] = 'Unit Group';
$string['unitgroup_help'] = 'Google Group email address for this unit (e.g. unit@oca.ac.uk). Used when visibility is "Coursemates and Tutors".';
$string['serviceaccountjson'] = 'Service account JSON';
$string['serviceaccountjson_help'] = 'Path to the service account JSON key file (relative to $CFG->dataroot or absolute), or paste the JSON content. Required for Google Drive API.';
$string['delegateduser'] = 'Delegated Workspace user';
$string['delegateduser_help'] = 'User email to impersonate via domain-wide delegation (for example extranet@oca.ac.uk). Leave empty to act as the service account directly.';
$string['sitescreated'] = 'Google Sites created';
$string['viewsite'] = 'View site';
$string['createdon'] = 'Created on';
$string['nocourseconfig'] = 'Course template and Unit Group are not configured. Please ask your teacher to set Course settings.';
$string['notemplate'] = 'No Google Site template is configured. Please contact the site administrator.';
$string['createfailed'] = 'Failed to create Google Site. Please try again or contact support.';
$string['createsuccess'] = 'Google Site created successfully.';
$string['editsitelisting'] = 'Edit site listing';
$string['editsitepartialsave'] = 'Saved in Moodle, but one or more Google Site updates could not be applied automatically.';
$string['privacy:metadata:block_google_site_creator_sites'] = 'Stores created Google Site records (title, URL, visibility).';
$string['privacy:metadata:block_google_site_creator_sites:userid'] = 'The user who created the site.';
$string['privacy:metadata:block_google_site_creator_sites:title'] = 'The site title.';
$string['privacy:metadata:block_google_site_creator_sites:url'] = 'The Google Site URL.';
