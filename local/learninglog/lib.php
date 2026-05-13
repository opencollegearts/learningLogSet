<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Extends the navigation with a link to the global learning log view.
 *
 * @param global_navigation $nav
 */
function local_learninglog_extend_navigation(global_navigation $nav) {
    $systemcontext = context_system::instance();
    if (!has_capability('local/learninglog:vieworg', $systemcontext)) {
        return;
    }

    $node = $nav->add(
        get_string('navlearninglogs', 'local_learninglog'),
        new moodle_url('/local/learninglog/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'nav_learninglog'
    );
    $node->showinflatnavigation = true;
}


