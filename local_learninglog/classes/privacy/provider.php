<?php
// This file is part of Moodle - http://moodle.org/

namespace local_learninglog\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for local_learninglog.
 *
 * This plugin only aggregates data from mod_learninglog
 * and does not store additional user data.
 *
 * @package   local_learninglog
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata:none';
    }
}

