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

/**
 * Admin page for local_homepage_config — export / import visual configuration.
 *
 * GET  ?action=export  → streams the ZIP download.
 * POST ?action=import  → processes the uploaded ZIP.
 * (none)               → displays the management UI.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

require_login();

$context = context_system::instance();
require_capability('local/homepage_config:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/homepage_config/index.php'));
$PAGE->set_title(get_string('settings', 'local_homepage_config'));
$PAGE->set_heading(get_string('settings', 'local_homepage_config'));
$PAGE->set_pagelayout('admin');

$action = optional_param('action', '', PARAM_ALPHA);

// Instantiate the import form early so it can detect its own submission.
$import_form = new \local_homepage_config\form\import_form(
    new moodle_url('/local/homepage_config/index.php', ['action' => 'import'])
);

// =============================================================================
// ACTION: EXPORT
// =============================================================================
if ($action === 'export') {
    require_sesskey();
    try {
        $zippath = \local_homepage_config\manager::export_to_zip();
        set_config('last_exported', time(), 'local_homepage_config');
        \local_homepage_config\event\config_exported::create([
            'context' => $context,
            'other'   => ['themecomponent' => get_config('local_homepage_config', 'themecomponent') ?: 'theme_boost_union'],
        ])->trigger();
        send_temp_file($zippath, 'homepage_config_' . date('Ymd') . '.zip');
    } catch (\moodle_exception $e) {
        redirect(new moodle_url('/local/homepage_config/index.php'), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
}

// =============================================================================
// ACTION: IMPORT
// =============================================================================
if ($import_form->is_submitted() && $import_form->is_validated()) {
    global $SESSION;

    $data           = $import_form->get_data();
    $restore_blocks = !empty($data->restore_blocks);

    // Retrieve the uploaded file from Moodle's draft file area.
    $fs      = get_file_storage();
    $context = context_user::instance($USER->id);
    $files   = $fs->get_area_files($context->id, 'user', 'draft', $data->configfile, '', false);
    $file    = reset($files);

    if (!$file) {
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('import_err_upload', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Copy to a temp path that ZipArchive can open.
    $tmppath = make_temp_directory('local_homepage_config') . '/' . $file->get_filename();
    $file->copy_content_to($tmppath);

    try {
        $stats = \local_homepage_config\manager::import_from_zip($tmppath, $restore_blocks);
        @unlink($tmppath);
        \local_homepage_config\event\config_imported::create([
            'context' => $context,
            'other'   => [
                'themecomponent' => get_config('local_homepage_config', 'themecomponent') ?: 'theme_boost_union',
                'stats'          => array_diff_key($stats, ['errors' => '']),
            ],
        ])->trigger();
    } catch (\moodle_exception $e) {
        @unlink($tmppath);
        redirect(new moodle_url('/local/homepage_config/index.php'), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Build notification message.
    $a   = (object)['settings' => $stats['settings'], 'core_settings' => $stats['core_settings'], 'files' => $stats['files']];
    $msg = get_string('import_success', 'local_homepage_config', $a);

    if ($stats['menus'] > 0) {
        $msg .= ' ' . get_string('import_success_menus',    'local_homepage_config', $stats['menus']);
    }
    if ($stats['flavours'] > 0) {
        $msg .= ' ' . get_string('import_success_flavours', 'local_homepage_config', $stats['flavours']);
    }
    if ($stats['blocks'] > 0) {
        $msg .= ' ' . get_string('import_success_blocks',   'local_homepage_config', $stats['blocks']);
    }
    if (!empty($stats['errors'])) {
        $msg .= ' ' . get_string('import_errors', 'local_homepage_config', count($stats['errors']));
        $SESSION->homepage_config_import_errors = $stats['errors'];
    }

    set_config('last_imported', time(), 'local_homepage_config');

    $type = empty($stats['errors'])
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_WARNING;

    redirect(new moodle_url('/local/homepage_config/index.php'), $msg, null, $type);
}

// =============================================================================
// DEFAULT: render UI
// =============================================================================
global $SESSION;
$summary    = \local_homepage_config\manager::get_summary();

// Collect and clear any error details stored during the previous import.
$import_errors = [];
if (!empty($SESSION->homepage_config_import_errors)) {
    $import_errors = $SESSION->homepage_config_import_errors;
    unset($SESSION->homepage_config_import_errors);
}
$export_url = new moodle_url('/local/homepage_config/index.php', ['action' => 'export', 'sesskey' => sesskey()]);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('settings', 'local_homepage_config'));

// ── Current configuration summary ────────────────────────────────────────────
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('current_config', 'local_homepage_config'), ['class' => 'card-title']);

$rows = [
    ['fa-sliders',    'settings_count', $summary['settings_count']],
    ['fa-image',      'files_count',    $summary['files_count']],
    ['fa-bars',       'menus_count',    $summary['menus_count']],
    ['fa-paint-brush','flavours_count', $summary['flavours_count']],
    ['fa-th-large',   'blocks_count',   $summary['blocks_count']],
];
foreach ($rows as [$icon, $key, $val]) {
    echo html_writer::tag('p',
        html_writer::tag('i', '', ['class' => "fa $icon mr-2"]) .
        get_string($key, 'local_homepage_config', $val),
        ['class' => 'mb-1']
    );
}

$never = get_string('never', 'local_homepage_config');
$ts_rows = [
    ['fa-upload',   'last_exported', $summary['last_exported'] ? userdate($summary['last_exported']) : $never],
    ['fa-download', 'last_imported', $summary['last_imported'] ? userdate($summary['last_imported']) : $never],
];
foreach ($ts_rows as [$icon, $key, $val]) {
    echo html_writer::tag('p',
        html_writer::tag('i', '', ['class' => "fa $icon mr-2"]) .
        get_string($key, 'local_homepage_config', $val),
        ['class' => 'mb-1 text-muted small']
    );
}

echo html_writer::end_div();
echo html_writer::end_div();

// ── Pending import error details (stored in session by previous import) ───────
if (!empty($import_errors)) {
    echo html_writer::start_div('card mb-4 border-warning');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h6',
        html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle mr-1 text-warning']) .
        get_string('import_error_details', 'local_homepage_config'),
        ['class' => 'card-title']
    );
    $items = implode('', array_map(fn($e) => html_writer::tag('li', s($e)), $import_errors));
    echo html_writer::tag('ul', $items, ['class' => 'mb-0 small text-muted']);
    echo html_writer::end_div();
    echo html_writer::end_div();
}

// ── Export ───────────────────────────────────────────────────────────────────
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('export', 'local_homepage_config'), ['class' => 'card-title']);
echo html_writer::tag('p', get_string('export_desc', 'local_homepage_config'), ['class' => 'card-text']);
echo html_writer::link($export_url,
    html_writer::tag('i', '', ['class' => 'fa fa-download mr-1']) .
    get_string('export_btn', 'local_homepage_config'),
    ['class' => 'btn btn-primary']
);
echo html_writer::end_div();
echo html_writer::end_div();

// ── Import ───────────────────────────────────────────────────────────────────
echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-body');
echo html_writer::tag('h5', get_string('import', 'local_homepage_config'), ['class' => 'card-title']);
echo html_writer::tag('p', get_string('import_desc', 'local_homepage_config'), ['class' => 'card-text']);
$import_form->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
