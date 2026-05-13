<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for mod_learninglog.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_learninglog_upgrade(int $oldversion): bool {
    global $CFG, $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026022602) {
        // Add table learninglog_user_log (per-student learning log per course).
        $table = new xmldb_table('learninglog_user_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('learninglogid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        $table->add_key('learninglogid', XMLDB_KEY_FOREIGN, ['learninglogid'], 'learninglog', ['id']);
        $table->add_index('user_course_idx', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Add userid to learninglog_categories for user-created categories.
        $cat = new xmldb_table('learninglog_categories');
        $userid = new xmldb_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'learninglogid');
        if (!$dbman->field_exists($cat, $userid)) {
            $dbman->add_field($cat, $userid);
        }

        upgrade_mod_savepoint(true, 2026022602, 'learninglog');
    }

    if ($oldversion < 2026022603) {
        $table = new xmldb_table('learninglog_user_log');
        $name = new xmldb_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'My Learning Log', 'learninglogid');
        if (!$dbman->field_exists($table, $name)) {
            $dbman->add_field($table, $name);
            $DB->set_field_select('learninglog_user_log', 'name', 'My Learning Log', '1=1');
        }
        // Make learninglogid nullable: drop foreign key then the index that blocks the column change.
        $key = new xmldb_key('learninglogid', XMLDB_KEY_FOREIGN, ['learninglogid'], 'learninglog', ['id']);
        try {
            $dbman->drop_key($table, $key);
        } catch (Exception $e) {
            // Key may already be gone or have different internal name.
        }
        // Drop index on learninglogid (dependency blocks change_field_notnull).
        $index = new xmldb_index('learninglogid', XMLDB_INDEX_NOTUNIQUE, ['learninglogid']);
        $indexname = method_exists($dbman, 'find_index_name') ? $dbman->find_index_name($table, $index) : false;
        if ($indexname === false) {
            $indexname = 'learuserlog_lea_ix'; // Fallback: name from ddldependencyerror.
        }
        $idx = new xmldb_index($indexname, XMLDB_INDEX_NOTUNIQUE, ['learninglogid']);
        if ($dbman->index_exists($table, $idx)) {
            $dbman->drop_index($table, $idx);
        }
        $learninglogid = new xmldb_field('learninglogid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'courseid');
        if ($dbman->field_exists($table, $learninglogid)) {
            $dbman->change_field_notnull($table, $learninglogid);
        }
        $dbman->add_key($table, $key);
        upgrade_mod_savepoint(true, 2026022603, 'learninglog');
    }

    if ($oldversion < 2026022604) {
        // Allow posts without an activity (course-only): make learninglog_posts.learninglogid nullable.
        // Drop FK and index first (index "mdl_learpost_leause_ix" blocks the column change).
        $table = new xmldb_table('learninglog_posts');
        $key = new xmldb_key('learninglogid', XMLDB_KEY_FOREIGN, ['learninglogid'], 'learninglog', ['id']);
        try {
            $dbman->drop_key($table, $key);
        } catch (Exception $e) {
            // Key may already be gone.
        }

        // Drop all indexes on learninglogid by raw SQL (Moodle API often cannot resolve physical names).
        // Two indexes block the column change: learpost_leause_ix (learninglogid, userid) and learpost_lea_ix (learninglogid).
        $dbfamily = $DB->get_dbfamily();
        $prefix = $CFG->prefix;
        $tblname = $prefix . 'learninglog_posts';
        $dropindex = function ($idx) use ($DB, $tblname) {
            $sql = "ALTER TABLE `{$tblname}` DROP INDEX `{$idx}`";
            try {
                $DB->execute($sql);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), '1091') === false && strpos($e->getMessage(), 'check that column/key exists') === false) {
                    throw $e;
                }
            }
        };
        if ($dbfamily === 'mysql' || $dbfamily === 'mariadb') {
            $dropindex($prefix . 'learpost_leause_ix');
            $dropindex($prefix . 'learpost_lea_ix');
        } elseif ($dbfamily === 'postgres') {
            $DB->execute("DROP INDEX IF EXISTS \"" . $prefix . "learpost_leause_ix\"");
            $DB->execute("DROP INDEX IF EXISTS \"" . $prefix . "learpost_lea_ix\"");
        }

        // API-based drop in case raw SQL was skipped (e.g. different DB).
        $index = new xmldb_index('learninglog_user_idx', XMLDB_INDEX_NOTUNIQUE, ['learninglogid', 'userid']);
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        $field = new xmldb_field('learninglogid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_notnull($table, $field);
        }
        $dbman->add_key($table, $key);
        $dbman->add_index($table, $index);
        upgrade_mod_savepoint(true, 2026022604, 'learninglog');
    }

    if ($oldversion < 2026022605) {
        // Add optional description to per-student learning log.
        $table = new xmldb_table('learninglog_user_log');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'name');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026022605, 'learninglog');
    }

    if ($oldversion < 2026022609) {
        // Add sitewide visibility for learning log (allow listing in local_learninglog site-wide view).
        $table = new xmldb_table('learninglog_user_log');
        $field = new xmldb_field('sitewide', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'description');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026022609, 'learninglog');
    }

    if ($oldversion < 2026030204) {
        // Add allowcomments to posts and create learninglog_comments table.
        $table = new xmldb_table('learninglog_posts');
        $field = new xmldb_field('allowcomments', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'visibility');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        $table = new xmldb_table('learninglog_comments');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('postid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
        $table->add_field('contentformat', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('postid', XMLDB_KEY_FOREIGN, ['postid'], 'learninglog_posts', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_mod_savepoint(true, 2026030204, 'learninglog');
    }

    if ($oldversion < 2026030208) {
        // Add optional sectionid to learninglog_categories so categories can track origin course section.
        $table = new xmldb_table('learninglog_categories');
        $field = new xmldb_field('sectionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'parentid');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026030208, 'learninglog');
    }

    if ($oldversion < 2026030210) {
        // Categories are course-level: add courseid, make learninglogid nullable.
        $table = new xmldb_table('learninglog_categories');
        $courseid = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        if (!$dbman->field_exists($table, $courseid)) {
            $dbman->add_field($table, $courseid);
            // Backfill from learninglog (learninglogid was NOT NULL in old schema).
            $sql = "UPDATE {learninglog_categories} c
                      SET c.courseid = (SELECT l.course FROM {learninglog} l WHERE l.id = c.learninglogid)
                    WHERE c.learninglogid IS NOT NULL";
            $DB->execute($sql);
            $courseidnotnull = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            $dbman->change_field_notnull($table, $courseidnotnull);
        }
        // Add courseid foreign key if missing.
        $courseidkey = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        try {
            $dbman->add_key($table, $courseidkey);
        } catch (Exception $e) {
            // Key may already exist.
        }
        // Make learninglogid nullable and set to null so all categories are course-level.
        $key = new xmldb_key('learninglogid', XMLDB_KEY_FOREIGN, ['learninglogid'], 'learninglog', ['id']);
        try {
            $dbman->drop_key($table, $key);
        } catch (Exception $e) {
            // Key may have different internal name.
        }
        $f = new xmldb_field('learninglogid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'courseid');
        if ($dbman->field_exists($table, $f)) {
            // Some older installs still have indexes that depend on learninglogid
            // (e.g. (learninglogid, sortorder)). Moodle's DDL layer blocks changing
            // the NOT NULL constraint unless those dependencies are removed first.
            // Drop any indexes that include learninglogid in the underlying table.
            $dbfamily = $DB->get_dbfamily();
            $tblname = $CFG->prefix . 'learninglog_categories';
            if ($dbfamily === 'mysql' || $dbfamily === 'mariadb') {
                // Moodle's DDL dependency errors often include a fully-qualified index name
                // such as "mdl_learcate_leasor_ix". Drop that exact index first to avoid
                // any INFORMATION_SCHEMA filtering quirks on the staging host.
                $indexCandidates = array_values(array_unique(array_filter([
                    $CFG->prefix . 'learcate_leasor_ix',
                    'learcate_leasor_ix',
                ])));
                foreach ($indexCandidates as $cand) {
                    try {
                        $DB->execute("ALTER TABLE `{$tblname}` DROP INDEX `{$cand}`");
                    } catch (Exception $e1) {
                        try {
                            $DB->execute("DROP INDEX `{$cand}` ON `{$tblname}`");
                        } catch (Exception $e2) {
                            // Ignore if already removed.
                        }
                    }
                }

                $schema = '';
                if (!empty($CFG->dbname) && is_string($CFG->dbname)) {
                    $schema = $CFG->dbname;
                }
                $tableschemawhere = $schema !== '' ? "= '" . addslashes($schema) . "'" : "= DATABASE()";

                $indexes = $DB->get_records_sql("
                    SELECT DISTINCT INDEX_NAME AS idx
                      FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA $tableschemawhere
                       AND TABLE_NAME = 'learninglog_categories'
                       AND COLUMN_NAME = 'learninglogid'
                ");
                foreach ($indexes as $row) {
                    $idx = (string)($row->idx ?? '');
                    if ($idx === '') {
                        continue;
                    }
                    try {
                        // MySQL syntax
                        $DB->execute("ALTER TABLE `{$tblname}` DROP INDEX `{$idx}`");
                    } catch (Exception $e1) {
                        try {
                            // Alternate syntax
                            $DB->execute("DROP INDEX `{$idx}` ON `{$tblname}`");
                        } catch (Exception $e2) {
                            debugging(
                                'Failed dropping dependent index ' . $idx . ' on ' . $tblname .
                                ': ' . $e2->getMessage(),
                                DEBUG_DEVELOPER
                            );
                        }
                    }
                }
            } else if ($dbfamily === 'postgres') {
                $indexes = $DB->get_records_sql("
                    SELECT DISTINCT indexname AS idx
                      FROM pg_indexes
                     WHERE schemaname = 'public'
                       AND tablename = 'learninglog_categories'
                       AND indexdef ILIKE '%learninglogid%'
                ");
                foreach ($indexes as $row) {
                    $idx = (string)($row->idx ?? '');
                    if ($idx === '') {
                        continue;
                    }
                    try {
                        $DB->execute('DROP INDEX IF EXISTS "' . addslashes($idx) . '"');
                    } catch (Exception $e) {
                        // Ignore if already removed.
                    }
                }
            }

            $DB->set_field('learninglog_categories', 'learninglogid', null, []);
            $dbman->change_field_notnull($table, $f);
        }
        $dbman->add_key($table, $key);
        // Replace old activity-based index with course-level index.
        $oldindex = new xmldb_index('learninglog_sort_idx', XMLDB_INDEX_NOTUNIQUE, ['learninglogid', 'sortorder']);
        if ($dbman->index_exists($table, $oldindex)) {
            $dbman->drop_index($table, $oldindex);
        }
        $index = new xmldb_index('user_course_sort_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'sortorder']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026030210, 'learninglog');
    }

    if ($oldversion < 2026031801) {
        // Idempotent: ensure learninglog_categories has courseid (fixes sites where 2026030210 never ran or failed).
        $table = new xmldb_table('learninglog_categories');
        $courseid = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'id');
        if (!$dbman->field_exists($table, $courseid)) {
            $dbman->add_field($table, $courseid);
            // Backfill: from learninglog where learninglogid is set.
            $DB->execute("UPDATE {learninglog_categories} c
                          SET c.courseid = (SELECT l.course FROM {learninglog} l WHERE l.id = c.learninglogid)
                          WHERE c.learninglogid IS NOT NULL");
            // Backfill: rows with NULL learninglogid get courseid from user's first learning log.
            $DB->execute("UPDATE {learninglog_categories} c
                          SET c.courseid = (SELECT lul.courseid FROM {learninglog_user_log} lul WHERE lul.userid = c.userid ORDER BY lul.id ASC LIMIT 1)
                          WHERE c.courseid IS NULL");
            // Any remaining NULLs (e.g. no user log): use first course to satisfy NOT NULL.
            $DB->execute("UPDATE {learninglog_categories} SET courseid = (SELECT MIN(id) FROM {course}) WHERE courseid IS NULL");
            $courseidnotnull = new xmldb_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, 'id');
            $dbman->change_field_notnull($table, $courseidnotnull);
        }
        $courseidkey = new xmldb_key('courseid', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);
        try {
            $dbman->add_key($table, $courseidkey);
        } catch (Exception $e) {
            // Key may already exist (e.g. from install.xml or prior upgrade).
        }
        $index = new xmldb_index('user_course_sort_idx', XMLDB_INDEX_NOTUNIQUE, ['userid', 'courseid', 'sortorder']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026031801, 'learninglog');
    }

    if ($oldversion < 2026032001) {
        $table = new xmldb_table('learninglog_comments');
        $istutor = new xmldb_field('istutor', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timemodified');
        if (!$dbman->field_exists($table, $istutor)) {
            $dbman->add_field($table, $istutor);
        }
        $tutorvisibility = new xmldb_field('tutorvisibility', XMLDB_TYPE_CHAR, '10', null, null, null, null, 'istutor');
        if (!$dbman->field_exists($table, $tutorvisibility)) {
            $dbman->add_field($table, $tutorvisibility);
        }
        $index = new xmldb_index('post_time_ix', XMLDB_INDEX_NOTUNIQUE, ['postid', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_mod_savepoint(true, 2026032001, 'learninglog');
    }

    return true;
}

