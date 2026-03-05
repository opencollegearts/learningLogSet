<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace tiny_bloglayouts;

use context;
use editor_tiny\plugin;
use editor_tiny\plugin_with_configuration;

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin info for the Blog layouts TinyMCE plugin.
 */
class plugininfo extends plugin implements plugin_with_configuration {
    /**
     * Provide per-context configuration passed into TinyMCE.
     *
     * For now we just expose the filepicker options so the JS side can
     * integrate with Moodle's draft area.
     *
     * @param context $context
     * @param array $options
     * @param array $fpoptions
     * @param \editor_tiny\editor|null $editor
     * @return array
     */
    public static function get_plugin_configuration_for_context(
        context $context,
        array $options,
        array $fpoptions,
        ?\editor_tiny\editor $editor = null
    ): array {
        return [
            'filepickeroptions' => $fpoptions,
        ];
    }
}

