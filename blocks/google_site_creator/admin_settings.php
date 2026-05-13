<?php
// This file is part of Moodle - http://moodle.org/
// Standalone admin form for block settings (reachable via Site administration → Plugins → Blocks → Google Site Creator).

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

admin_externalpage_setup('block_google_site_creator_settings');

class block_google_site_creator_admin_form extends moodleform {

    protected function definition() {
        $mform = $this->_form;
        $mform->addElement('text', 'defaulttemplate', get_string('defaulttemplate', 'block_google_site_creator'), ['size' => 60]);
        $mform->setType('defaulttemplate', PARAM_TEXT);
        $mform->addHelpButton('defaulttemplate', 'defaulttemplate', 'block_google_site_creator');

        $mform->addElement('textarea', 'serviceaccountjson', get_string('serviceaccountjson', 'block_google_site_creator'), ['rows' => 8, 'cols' => 60]);
        $mform->setType('serviceaccountjson', PARAM_RAW);
        $mform->addHelpButton('serviceaccountjson', 'serviceaccountjson', 'block_google_site_creator');

        $this->add_action_buttons();
    }
}

$form = new block_google_site_creator_admin_form(new moodle_url('/blocks/google_site_creator/admin_settings.php'));

if ($form->is_cancelled()) {
    redirect(new moodle_url('/admin/settings.php', ['section' => 'blocks']));
}

$data = $form->get_data();
if ($data) {
    set_config('defaulttemplate', $data->defaulttemplate, 'block_google_site_creator');
    set_config('serviceaccountjson', $data->serviceaccountjson, 'block_google_site_creator');
    redirect($PAGE->url, get_string('saved', 'core'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$current = get_config('block_google_site_creator', 'defaulttemplate');
$form->set_data([
    'defaulttemplate' => $current !== false ? $current : '',
    'serviceaccountjson' => get_config('block_google_site_creator', 'serviceaccountjson') ?: '',
]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'block_google_site_creator'));
$form->display();
echo $OUTPUT->footer();
