# Google Site Creator block

Block that lets users create a Google Site from a Drive template (copy), set visibility (Tutors / Unit Group / All of OCA), and have created sites discovered alongside Learning Logs on the site-wide index when visibility is "All of OCA".

## Requirements

- Moodle 4.0+
- Google Cloud project with Drive API enabled
- Service account with a JSON key (stored securely in site or course settings)

## Installation

1. Copy the `google_site_creator` folder into `blocks/`.
2. Visit **Site administration → Notifications** to install the block and create the database tables.

## Configuration

### Site administration

- **Site administration → Plugins → Blocks → Google Site Creator**
  - **Default Google Site Template ID**: Drive file ID of the template site (used when the course has no unit-specific template).
  - **Service account JSON**: Either the path to the JSON key file (relative to `$CFG->dataroot` or absolute) or paste the full JSON content. The service account must have Drive API access.

### Course settings (teachers)

- Add the block to a course, then use the **Course settings** link in the block footer (requires **Manage course Google Site settings**).
  - **Unit-specific Site Template ID**: Optional. If set, this template is used instead of the site default for this course.
  - **Unit Group**: Google Group email (e.g. `unit@oca.ac.uk`) used when visibility is "Coursemates and Tutors".

## Visibility and sharing

- **Tutors**: Shared with `oca-tutors@oca.ac.uk` (no notification email).
- **Coursemates and Tutors**: Shared with the course **Unit Group** email from course settings.
- **All of OCA**: Shared with `everyone@oca.ac.uk` and listed on the **Learning Logs** site-wide index (local_learninglog) alongside mod_learninglog posts.

## Web services

Two external functions are provided for CURL/admin integration:

1. **block_google_site_creator_get_site_details**  
   Parameters: `siteid` (int).  
   Returns: id, userid, courseid, title, drive_file_id, url, visibility, timecreated, timemodified.

2. **block_google_site_creator_create_site**  
   Parameters: `courseid`, `userid`, `title`, `templateid` (optional), `visibility` (tutors | unit_group | all_oca).  
   Returns: same as get_site_details for the created record.

To enable:

1. **Site administration → Plugins → Web services → External services**: create a service and add the two functions.
2. **Manage tokens**: create a token for a user with `block/google_site_creator:create_for_others` (e.g. manager).
3. Call the REST endpoint with the token (see Moodle web services documentation).

## Discovery

When visibility is **All of OCA**, the created site is stored in `block_google_site_creator_sites` and shown on the **Learning Logs** index page (local_learninglog, "Posts" tab) together with organisation-visible learning log posts. No dependency on mod_learninglog schema; the two sources are merged for display only.
