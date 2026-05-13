<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/defaulttemplate',
        get_string('defaulttemplate', 'block_google_site_creator'),
        get_string('defaulttemplate_help', 'block_google_site_creator'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'block_google_site_creator/serviceaccountjson',
        get_string('serviceaccountjson', 'block_google_site_creator'),
        get_string('serviceaccountjson_help', 'block_google_site_creator'),
        '',
        PARAM_RAW,
        8,
        60
    ));

    $settings->add(new admin_setting_configtext(
        'block_google_site_creator/delegateduser',
        get_string('delegateduser', 'block_google_site_creator'),
        get_string('delegateduser_help', 'block_google_site_creator'),
        'extranet@oca.ac.uk',
        PARAM_EMAIL
    ));
}
