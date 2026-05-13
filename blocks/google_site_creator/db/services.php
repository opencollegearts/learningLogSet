<?php
// This file is part of Moodle - http://moodle.org/
// Register these functions in Site administration → Plugins → Web services → External services,
// then assign to a service and use a token (or OAuth) for CURL/REST calls.

defined('MOODLE_INTERNAL') || die();

$functions = [
    'block_google_site_creator_get_site_details' => [
        'classname' => 'block_google_site_creator\external\get_site_details',
        'methodname' => 'execute',
        'description' => 'Get Google Site details by record ID',
        'type' => 'read',
        'ajax' => true,
        'capabilities' => 'block/google_site_creator:create',
    ],
    'block_google_site_creator_create_site' => [
        'classname' => 'block_google_site_creator\external\create_site',
        'methodname' => 'execute',
        'description' => 'Create a Google Site on behalf of a user (courseid, userid, title, optional templateid, visibility)',
        'type' => 'write',
        'ajax' => true,
        'capabilities' => 'block/google_site_creator:create_for_others',
    ],
];
