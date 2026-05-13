<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade script for block_google_site_creator.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_google_site_creator_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026031901) {
        $table = new xmldb_table('block_google_site_creator_sites');
        $field = new xmldb_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null, 'title');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_block_savepoint(true, 2026031901, 'google_site_creator');
    }

    return true;
}
