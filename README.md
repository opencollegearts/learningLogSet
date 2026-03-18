# Learning Log (Moodle plugin set)

A Moodle plugin set that adds **Learning Log** — a reflective journal or blog activity where each learner has their own named log per course. Learners write entries (posts), organise them with categories, control visibility, and optionally share logs site-wide. Educators can track progress via completion rules and view recent posts in the course.

---

## What it does

- **Per-learner learning logs** — Each student creates and names one Learning Log per course (e.g. “My Reflective Journal”). Entries belong to that log.
- **Posts with status and visibility** — Entries can be **Draft**, **Published**, or **Personal research**. Visibility can be **Private** (only me and staff), **Visible within this course**, or **Visible across the organisation**.
- **Categories** — Learners can organise posts with section-based or custom categories and filter their log view.
- **Comments** — Option to allow comments on posts for peer or tutor feedback.
- **Completion** — Activity completion can be based on a required number of published posts.
- **Optional AI** — Per-activity setting to enable AI-generated summaries and alt-text for posts (when configured).
- **Import / export** — Import from WordPress (WXR) and export learning log content as WXR.
- **Site-wide view** — Staff can view organisation-visible posts and site-wide learning logs (with filters by course category and course) from a dedicated page.
- **Optional TinyMCE Editor enhancement** — TinyMCE “Blog layouts” plugin for inserting image grids and sliders in post content.

---

## Components in this plugin set

This repository contains several plugins that work together. They must be installed into the matching locations inside your Moodle installation:

| Folder in this repo              | Copy to (inside Moodle root)     |
|----------------------------------|-----------------------------------|
| `mod/learninglog`                | `mod/learninglog`                 |
| `blocks/learninglog`             | `blocks/learninglog`              |
| `blocks/learninglog_recent`      | `blocks/learninglog_recent`       |
| `local/learninglog`              | `local/learninglog`               |
| `bloglayouts`                    | `lib/editor/tiny/plugins/bloglayouts` |

---

## Requirements

- **Moodle 4.5** or later (see `mod/learninglog/version.php` for the exact `requires` value).
- PHP and database as required by your Moodle version.

---

## Setup (Moodle admins)

### 1. Install the plugins

Copy each component from this repository into your Moodle directory so that the structure matches the table above. For example, from the directory that contains this repo:

```bash
# Replace /path/to/moodle with your Moodle root path
MOODLE=/path/to/moodle

cp -r mod/learninglog           "$MOODLE/mod/"
cp -r blocks/learninglog        "$MOODLE/blocks/"
cp -r blocks/learninglog_recent "$MOODLE/blocks/"
cp -r local/learninglog         "$MOODLE/local/"
cp -r bloglayouts               "$MOODLE/lib/editor/tiny/plugins/"
```

### 2. Run Moodle upgrade

- Visit **Site administration → Notifications** (or open your Moodle in a browser so the upgrade runs).
- Confirm the new plugins (Learning log activity, Learning log block, Recent learning log posts block, Learning log (global), Blog layouts) are detected and complete the upgrade.

### 3. (Optional) Add the site-wide Learning logs page to navigation

The site-wide view (organisation-visible posts and list of site-wide logs) is at:

`/local/learninglog/index.php`

Users need the capability **local/learninglog:vieworg** (by default: managers and teachers). To make it easy to find, add a link in **Site administration → Appearance → Navigation** or in a custom menu / dashboard block pointing to this URL.

### 4. (Optional) Configure AI features

If you use the “Enable AI-generated summaries and alt-text” option in a Learning log activity, ensure your site has the required AI/LLM integration configured (see your Moodle AI documentation). The activity-level setting only takes effect when that integration is available.

---

## For educators: using Learning Log in a course

### Add the activity and blocks

1. **Add a Learning log activity**  
   Turn editing on → **Add an activity or resource** → choose **Learning log**. Give it a name (e.g. “Reflective journal”) and, if you like, set:
   - **Required number of posts for completion** (e.g. 5).
   - **Enable AI-generated summaries and alt-text** (if your site supports it).

2. **Add the Learning log block**  
   Add a block → **Learning log**.  
   This block shows:
   - **Create Learning Log** until the student has created their log (they choose a name and optional description).
   - After that: **Add Learning Log Entry**, **View my learning log**, **Edit my learning log**, and recent drafts/personal research entries.

3. **Optional: Recent learning log posts block**  
   Add the **Recent learning log posts** block to show recent published posts from the course (course- or organisation-visible) so learners and staff can see what others are posting.

### How learners use it

- **First time:** From the Learning log block they click **Create Learning Log**, enter a name (and optionally description and “Show this learning log site-wide”), then save.
- **Adding entries:** They use **Add Learning Log Entry** (from the block or from the activity). They can link an entry to a course section, set status (draft / published / personal research), visibility (private / course / organisation), categories, and allow or disallow comments.
- **Viewing their log:** **View my learning log** opens their log (grid/list of posts); they can filter by category and open individual posts to edit or delete.
- **Section-based prompts:** If you use course sections, a “Log about this section” button can appear on section pages (via the local plugin), taking them straight to add an entry in that section’s context.

### Completion

- In the Learning log activity settings, set **Required number of posts for completion** to the number of **published** posts needed (e.g. 5).
- Configure the activity’s completion condition (e.g. “Require X published posts”) in the activity completion settings. Learners complete the activity when they have at least that many published posts.

### Site-wide Learning logs (staff)

- Users with **local/learninglog:vieworg** can open the site-wide Learning logs page (e.g. via the link you added in step 3 above).
- They can switch between **Posts** (organisation-visible posts from all logs) and **Learning logs** (list of logs that are marked site-wide), and filter by course category and course.

---

## Capabilities (summary)

- **mod/learninglog:addinstance** — Add a Learning log activity (editing teachers, managers).
- **mod/learninglog:view** / **mod/learninglog:write** — View and create/edit entries (students and staff in the course).
- **mod/learninglog:viewall** / **mod/learninglog:vieworgvisible** — View all posts or org-visible posts (teachers, managers).
- **mod/learninglog:comment** — Comment on posts (students and staff as per roles).
- **mod/learninglog:managecategories** — Manage categories (editing teachers, managers).
- **local/learninglog:vieworg** — Access the site-wide Learning logs page (managers, teachers by default).

Adjust these in **Site administration → Users → Permissions** if you need to restrict or extend access.

---

## License

This plugin set is distributed under the [GNU GPL v3](https://www.gnu.org/copyleft/gpl.html) (or later), consistent with Moodle.

---

## Version and maturity

- **Release:** 1.0.0  
- **Maturity:** BETA (see `mod/learninglog/version.php`). Feature-complete and suitable for production use; continue to test in your environment and maintain backups.
