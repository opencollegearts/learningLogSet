<?php
// This file is part of Moodle - http://moodle.org/

namespace mod_learninglog\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Wrapper around Moodle's AI subsystem (Moodle 4.5) for learning log.
 *
 * This implementation is intentionally conservative and does not make
 * assumptions about the exact AI API classes. It provides extension
 * points where real AI calls can be wired in later.
 *
 * @package   mod_learninglog
 */
class ai_service {

    /**
     * Returns whether AI is available and configured.
     *
     * @return bool
     */
    public static function is_available(): bool {
        // In this initial version we only provide an architectural stub.
        // Site administrators can later extend this to check Moodle's AI config.
        return false;
    }

    /**
     * Generate a short summary of the given post content.
     *
     * @param string $content
     * @return string|null
     */
    public static function generate_summary(string $content): ?string {
        if (!self::is_available()) {
            return null;
        }

        // Placeholder: integrate with Moodle AI APIs here.
        return null;
    }

    /**
     * Generate alt-text for the given image file.
     *
     * @param \stored_file $file
     * @return string|null
     */
    public static function generate_alt_text(\stored_file $file): ?string {
        if (!self::is_available()) {
            return null;
        }

        // Placeholder: integrate with Moodle AI APIs here.
        return null;
    }
}

