<?php
// This file is part of Moodle - http://moodle.org/

namespace block_learninglog_comments\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy API: this block stores no user data itself.
 *
 * @package   block_learninglog_comments
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
