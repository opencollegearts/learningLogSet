<?php
// This file is part of Moodle - http://moodle.org/

namespace block_learninglog\privacy;

defined('MOODLE_INTERNAL') || die();

/**
 * Privacy provider for block_learninglog.
 *
 * The block itself does not store additional user data; it only reads
 * data from mod_learninglog.
 *
 * @package   block_learninglog
 */
class provider implements \core_privacy\local\metadata\null_provider {

    public static function get_reason(): string {
        return 'privacy:metadata:none';
    }
}

