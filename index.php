<?php
declare(strict_types=1);

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
// The form posts to ?action=preview — the actual import runs only after the
// admin confirms the preview step.
$import_form = new \local_homepage_config\form\import_form(
    new moodle_url('/local/homepage_config/index.php', ['action' => 'preview'])
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
// ACTION: PREVIEW  (step 1 — upload received, show what the ZIP contains)
// =============================================================================
if ($import_form->is_submitted() && $import_form->is_validated()) {
    global $SESSION;

    $data    = $import_form->get_data();
    $options = [
        'settings'         => !empty($data->import_settings),
        'plugin_settings'  => !empty($data->import_plugin_settings),
        'core_settings'    => !empty($data->import_core_settings),
        'files'            => !empty($data->import_files),
        'menus'            => !empty($data->import_menus),
        'flavours'         => !empty($data->import_flavours),
        'blocks'           => !empty($data->restore_blocks),
        'reset_dashboards' => !empty($data->reset_dashboards) && !empty($data->restore_blocks),
    ];

    // Retrieve the uploaded file from Moodle's draft file area.
    $fs           = get_file_storage();
    $user_context = context_user::instance($USER->id);
    $files        = $fs->get_area_files($user_context->id, 'user', 'draft', $data->configfile, '', false);
    $file         = reset($files);

    if (!$file) {
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('import_err_upload', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Copy to a temp path that ZipArchive can open.
    $tmppath = make_temp_directory('local_homepage_config') . '/' . $file->get_filename();
    $file->copy_content_to($tmppath);

    // Dry-run: read the ZIP and diff against current config without touching the DB.
    $preview = \local_homepage_config\manager::diff_zip($tmppath);

    if (!$preview['valid']) {
        @unlink($tmppath);
        redirect(new moodle_url('/local/homepage_config/index.php'),
            $preview['error'], null, \core\output\notification::NOTIFY_ERROR);
    }

    // Store temp file path + options in session so the confirm step can find them.
    // Paths and options are never exposed in the form to prevent manipulation.
    $SESSION->homepage_config_pending = [
        'tmppath'  => $tmppath,
        'options'  => $options,
        'created'  => time(),
    ];

    // Render the preview page inline (no redirect — avoids a second round trip).
    $cancel_url  = new moodle_url('/local/homepage_config/index.php', ['action' => 'cancel_import']);
    $confirm_url = new moodle_url('/local/homepage_config/index.php', ['action' => 'import_confirmed',
                                                                        'sesskey' => sesskey()]);
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('preview_title', 'local_homepage_config'));

    // ── Source info card ─────────────────────────────────────────────────────
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('preview_source', 'local_homepage_config'), ['class' => 'card-title']);

    $source_rows = [
        ['fa-cube',     'preview_theme',    s($preview['theme_component'])],
        ['fa-code-fork','preview_format',   s($preview['format_version'])],
        ['fa-server',   'preview_moodle',   s($preview['moodle_version'])],
    ];
    if ($preview['exported_at']) {
        $source_rows[] = ['fa-clock-o', 'preview_exported_at', userdate($preview['exported_at'])];
    }
    foreach ($source_rows as [$icon, $key, $val]) {
        echo html_writer::tag('p',
            html_writer::tag('i', '', ['class' => "fa $icon mr-2"]) .
            get_string($key, 'local_homepage_config', $val),
            ['class' => 'mb-1']
        );
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    // ── What will be imported card ────────────────────────────────────────────
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('preview_contents', 'local_homepage_config'), ['class' => 'card-title']);

    // Each row: [option_key, fa_icon, lang_key, count_or_null]
    $section_rows = [
        ['settings',        'fa-sliders',     'preview_settings_count',  $preview['settings_count']],
        ['files',           'fa-image',       'preview_files_count',     $preview['files_count']],
        ['menus',           'fa-bars',        'preview_menus_count',     $preview['menus_count']],
        ['flavours',        'fa-paint-brush', 'preview_flavours_count',  $preview['flavours_count']],
        ['blocks',          'fa-th-large',    'preview_blocks_count',    $preview['blocks_count']],
    ];
    foreach ($section_rows as [$opt_key, $icon, $str_key, $count]) {
        $selected = !empty($options[$opt_key]);
        $class    = $selected ? 'mb-1' : 'mb-1 text-muted';
        $tick     = $selected
            ? html_writer::tag('i', '', ['class' => 'fa fa-check-square-o text-success mr-1'])
            : html_writer::tag('i', '', ['class' => 'fa fa-square-o text-muted mr-1']);
        echo html_writer::tag('p',
            $tick .
            html_writer::tag('i', '', ['class' => "fa $icon mr-1"]) .
            get_string($str_key, 'local_homepage_config', $count),
            ['class' => $class]
        );
    }
    if (!empty($options['blocks']) && !empty($options['reset_dashboards'])) {
        echo html_writer::tag('p',
            html_writer::tag('i', '', ['class' => 'fa fa-users mr-2 text-warning']) .
            get_string('preview_reset_dashboards_note', 'local_homepage_config'),
            ['class' => 'mb-1 small text-muted']
        );
    }
    echo html_writer::end_div();
    echo html_writer::end_div();

    // ── Cohort reference warning ─────────────────────────────────────────────
    $cohort_menus    = (int)($preview['cohort_warn_menus']    ?? 0);
    $cohort_flavours = (int)($preview['cohort_warn_flavours'] ?? 0);
    if ($cohort_menus > 0 || $cohort_flavours > 0) {
        echo html_writer::start_div('card mb-4 border-warning');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h5',
            html_writer::tag('i', '', ['class' => 'fa fa-users mr-2 text-warning']) .
            get_string('cohort_warn_title', 'local_homepage_config'),
            ['class' => 'card-title']
        );
        echo html_writer::tag('p', get_string('cohort_warn_intro', 'local_homepage_config'), ['class' => 'mb-2']);
        $items = '';
        if ($cohort_menus > 0) {
            $items .= html_writer::tag('li', get_string('cohort_warn_menus', 'local_homepage_config', $cohort_menus));
        }
        if ($cohort_flavours > 0) {
            $items .= html_writer::tag('li', get_string('cohort_warn_flavours', 'local_homepage_config', $cohort_flavours));
        }
        echo html_writer::tag('ul', $items, ['class' => 'mb-2']);
        echo html_writer::tag('p', get_string('cohort_warn_action', 'local_homepage_config'), ['class' => 'mb-0 small text-muted']);
        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // ── Settings diff ────────────────────────────────────────────────────────
    if (!empty($preview['diff'])) {
        $changed   = $preview['diff_changed']   + $preview['diff_added'];
        $unchanged = $preview['diff_unchanged'];

        echo html_writer::start_div('card mb-4');
        echo html_writer::start_div('card-body');
        echo html_writer::tag('h5', get_string('diff_title', 'local_homepage_config'), ['class' => 'card-title']);

        // Summary badges.
        $badge_changed   = html_writer::tag('span',
            get_string('diff_badge_changed', 'local_homepage_config', $changed),
            ['class' => 'badge badge-warning mr-2']);
        $badge_unchanged = html_writer::tag('span',
            get_string('diff_badge_unchanged', 'local_homepage_config', $unchanged),
            ['class' => 'badge badge-secondary mr-2']);
        echo html_writer::tag('p', $badge_changed . $badge_unchanged, ['class' => 'mb-3']);

        // Source label helper.
        $source_label = [
            'theme'  => html_writer::tag('span',
                get_string('diff_source_theme', 'local_homepage_config'), ['class' => 'badge badge-info']),
            'plugin' => html_writer::tag('span',
                get_string('diff_source_plugin', 'local_homepage_config'), ['class' => 'badge badge-secondary']),
            'core'   => html_writer::tag('span',
                get_string('diff_source_core', 'local_homepage_config'), ['class' => 'badge badge-dark']),
        ];

        // Build rows — changed/added always visible, unchanged hidden initially.
        $rows_visible = '';
        $rows_hidden  = '';
        foreach ($preview['diff'] as $entry) {
            $status    = $entry['status'];
            $row_class = $status === 'changed' ? 'table-warning' : ($status === 'added' ? 'table-success' : '');
            $cur_cell  = $status === 'added'
                ? html_writer::tag('em', get_string('diff_notset', 'local_homepage_config'), ['class' => 'text-muted'])
                : html_writer::tag('code', s($entry['current']), ['class' => 'small']);
            $row = html_writer::tag('tr',
                html_writer::tag('td', ($source_label[$entry['source']] ?? s($entry['source']))) .
                html_writer::tag('td', html_writer::tag('code', s($entry['name']), ['class' => 'small'])) .
                html_writer::tag('td', $cur_cell) .
                html_writer::tag('td', html_writer::tag('code', s($entry['incoming']), ['class' => 'small'])),
                ['class' => $row_class]
            );
            if ($status === 'unchanged') {
                $rows_hidden .= $row;
            } else {
                $rows_visible .= $row;
            }
        }

        $thead = html_writer::tag('thead', html_writer::tag('tr',
            html_writer::tag('th', get_string('diff_col_source',   'local_homepage_config'), ['width' => '80']) .
            html_writer::tag('th', get_string('diff_col_name',     'local_homepage_config')) .
            html_writer::tag('th', get_string('diff_col_current',  'local_homepage_config')) .
            html_writer::tag('th', get_string('diff_col_incoming', 'local_homepage_config'))
        ));

        echo html_writer::tag('table',
            $thead . html_writer::tag('tbody', $rows_visible),
            ['class' => 'table table-sm table-hover mb-2', 'id' => 'diff-changed-table']
        );

        if ($rows_hidden !== '') {
            echo html_writer::tag('div',
                html_writer::tag('table',
                    $thead . html_writer::tag('tbody', $rows_hidden),
                    ['class' => 'table table-sm table-hover mb-0']
                ),
                ['id' => 'diff-unchanged-section', 'style' => 'display:none;']
            );
            $lbl_show = '<i class=\"fa fa-eye mr-1\"></i>' .
                get_string('diff_show_unchanged', 'local_homepage_config', $unchanged);
            $lbl_hide = '<i class=\"fa fa-eye-slash mr-1\"></i>' .
                get_string('diff_hide_unchanged', 'local_homepage_config');
            echo html_writer::tag('button',
                html_writer::tag('i', '', ['class' => 'fa fa-eye mr-1']) .
                get_string('diff_show_unchanged', 'local_homepage_config', $unchanged),
                [
                    'type'    => 'button',
                    'class'   => 'btn btn-sm btn-link p-0',
                    'onclick' => "
                        var s = document.getElementById('diff-unchanged-section');
                        var isHidden = s.style.display === 'none';
                        s.style.display = isHidden ? '' : 'none';
                        this.innerHTML = isHidden ? '$lbl_hide' : '$lbl_show';
                    ",
                ]
            );
        }

        echo html_writer::end_div();
        echo html_writer::end_div();
    }

    // ── Warning + confirm form ────────────────────────────────────────────────
    echo html_writer::start_div('card mb-4 border-warning');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('p',
        html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle mr-1 text-warning']) .
        get_string('preview_warning', 'local_homepage_config'),
        ['class' => 'mb-2']
    );
    echo html_writer::tag('p',
        html_writer::tag('i', '', ['class' => 'fa fa-camera mr-1 text-success']) .
        get_string('preview_snapshot_note', 'local_homepage_config'),
        ['class' => 'mb-3 small text-muted']
    );
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $confirm_url]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
    echo html_writer::tag('button',
        html_writer::tag('i', '', ['class' => 'fa fa-upload mr-1']) .
        get_string('preview_confirm_btn', 'local_homepage_config'),
        ['type' => 'submit', 'class' => 'btn btn-danger mr-2']
    );
    echo html_writer::link($cancel_url,
        html_writer::tag('i', '', ['class' => 'fa fa-times mr-1']) .
        get_string('preview_cancel', 'local_homepage_config'),
        ['class' => 'btn btn-secondary']
    );
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
    echo html_writer::end_div();

    echo $OUTPUT->footer();
    exit;
}

// =============================================================================
// ACTION: CANCEL IMPORT  (cleans up temp file and session, returns to main UI)
// =============================================================================
if ($action === 'cancel_import') {
    global $SESSION;
    $pending = $SESSION->homepage_config_pending ?? null;
    if ($pending && !empty($pending['tmppath']) && file_exists($pending['tmppath'])) {
        @unlink($pending['tmppath']);
    }
    unset($SESSION->homepage_config_pending);
    redirect(new moodle_url('/local/homepage_config/index.php'));
}

// =============================================================================
// ACTION: IMPORT CONFIRMED  (step 2 — admin confirmed, run the real import)
// =============================================================================
if ($action === 'import_confirmed') {
    global $SESSION;
    require_sesskey();

    $pending = $SESSION->homepage_config_pending ?? null;
    if (!$pending || empty($pending['tmppath']) || !file_exists($pending['tmppath'])) {
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('preview_expired', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    $tmppath = $pending['tmppath'];
    $options = $pending['options'] ?? [];
    unset($SESSION->homepage_config_pending);

    try {
        $stats = \local_homepage_config\manager::import_from_zip($tmppath, $options);
        @unlink($tmppath);
        \local_homepage_config\event\config_imported::create([
            'context' => $context,
            'other'   => [
                'themecomponent' => get_config('local_homepage_config', 'themecomponent') ?: 'theme_boost_union',
                'stats'          => array_merge(
                    array_diff_key($stats, ['errors' => '']),
                    ['error_count' => count($stats['errors'])]
                ),
            ],
        ])->trigger();
    } catch (\moodle_exception $e) {
        @unlink($tmppath);
        redirect(new moodle_url('/local/homepage_config/index.php'), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Build notification message.
    $a   = (object)['settings' => $stats['settings'], 'coresettings' => $stats['coresettings'], 'files' => $stats['files']];
    $msg = get_string('import_success', 'local_homepage_config', $a);

    if ($stats['menus'] > 0) {
        $msg .= ' ' . get_string('import_success_menus',    'local_homepage_config', $stats['menus']);
    }
    if ($stats['flavours'] > 0) {
        $msg .= ' ' . get_string('import_success_flavours', 'local_homepage_config', $stats['flavours']);
    }
    if ($stats['blocks'] > 0) {
        $msg .= ' ' . get_string('import_success_blocks', 'local_homepage_config', $stats['blocks']);
    }
    if (!empty($options['reset_dashboards'])) {
        $msg .= ' ' . get_string('import_success_dashboards_reset', 'local_homepage_config');
    }
    if (!empty($stats['errors'])) {
        $msg .= ' ' . get_string('import_errors', 'local_homepage_config', count($stats['errors']));
        $SESSION->homepage_config_import_errors = $stats['errors'];
    }

    $now = time();
    set_config('last_imported', $now, 'local_homepage_config');

    // Persist an audit record so admins can review the import history.
    $DB->insert_record('local_homepage_config_import', (object)[
        'userid'         => $USER->id,
        'timecreated'    => $now,
        'themecomponent' => get_config('local_homepage_config', 'themecomponent') ?: 'theme_boost_union',
        'settings'       => $stats['settings'],
        'coresettings'   => $stats['coresettings'],
        'files'          => $stats['files'],
        'menus'          => $stats['menus'],
        'flavours'       => $stats['flavours'],
        'blocks'         => $stats['blocks'],
        'errors'         => count($stats['errors']),
        'restoreblocks'  => (int)(!empty($options['blocks'])),
        'snapshotfileid' => $stats['snapshotfileid'],
    ]);

    $type = empty($stats['errors'])
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_WARNING;

    redirect(new moodle_url('/local/homepage_config/index.php'), $msg, null, $type);
}

// =============================================================================
// ACTION: ROLLBACK  (restore from a pre-import snapshot)
// =============================================================================
if ($action === 'rollback') {
    require_sesskey();

    $fileid = required_param('snapshotfileid', PARAM_INT);
    if ($fileid <= 0) {
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('snapshotnotfound', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    // Verify the snapshot was taken within the last 24 hours.
    $fs   = get_file_storage();
    $file = $fs->get_file_by_id($fileid);
    if (!$file || (time() - $file->get_timecreated()) > 86400) {
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('snapshot_expired', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_ERROR);
    }

    try {
        $stats = \local_homepage_config\manager::rollback_to_snapshot($fileid);
        set_config('last_imported', time(), 'local_homepage_config');
        redirect(new moodle_url('/local/homepage_config/index.php'),
            get_string('rollback_success', 'local_homepage_config'), null,
            \core\output\notification::NOTIFY_SUCCESS);
    } catch (\moodle_exception $e) {
        redirect(new moodle_url('/local/homepage_config/index.php'), $e->getMessage(), null,
            \core\output\notification::NOTIFY_ERROR);
    }
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

// ── Import history ───────────────────────────────────────────────────────────
$history = $DB->get_records('local_homepage_config_import', null, 'timecreated DESC', '*', 0, 10);
if (!empty($history)) {
    echo html_writer::start_div('card mb-4');
    echo html_writer::start_div('card-body');
    echo html_writer::tag('h5', get_string('history_title', 'local_homepage_config'), ['class' => 'card-title']);

    $thead = html_writer::tag('tr',
        html_writer::tag('th', get_string('history_date',     'local_homepage_config')) .
        html_writer::tag('th', get_string('history_user',     'local_homepage_config')) .
        html_writer::tag('th', get_string('history_theme',    'local_homepage_config')) .
        html_writer::tag('th', get_string('history_settings', 'local_homepage_config')) .
        html_writer::tag('th', get_string('history_files',    'local_homepage_config')) .
        html_writer::tag('th', get_string('history_extras',   'local_homepage_config')) .
        html_writer::tag('th', get_string('history_errors',   'local_homepage_config')) .
        html_writer::tag('th', get_string('history_actions',  'local_homepage_config'))
    );

    $rows = '';
    $now  = time();
    foreach ($history as $row) {
        $user     = core_user::get_user($row->userid);
        $username = $user ? fullname($user) : '—';
        $extras   = array_filter([
            $row->menus    > 0 ? $row->menus    . ' menus'    : null,
            $row->flavours > 0 ? $row->flavours . ' flavours' : null,
            $row->blocks   > 0 ? $row->blocks   . ' blocks'   : null,
        ]);

        // Rollback button: only when a snapshot exists and is less than 24 h old.
        $snapshotfileid = (int)($row->snapshotfileid ?? 0);
        $snapshot_age   = $now - (int)$row->timecreated;
        if ($snapshotfileid > 0 && $snapshot_age < 86400) {
            $rollback_url = new moodle_url('/local/homepage_config/index.php', [
                'action'         => 'rollback',
                'snapshotfileid' => $snapshotfileid,
                'sesskey'        => sesskey(),
            ]);
            $action_cell = html_writer::link(
                $rollback_url,
                html_writer::tag('i', '', ['class' => 'fa fa-undo mr-1']) .
                get_string('rollback_btn', 'local_homepage_config'),
                [
                    'class'        => 'btn btn-sm btn-outline-warning',
                    'data-confirm' => get_string('rollback_confirm', 'local_homepage_config'),
                    'onclick'      => "return confirm(this.dataset.confirm);",
                ]
            );
        } else {
            $action_cell = html_writer::tag('span', '—', ['class' => 'text-muted']);
        }

        $rows .= html_writer::tag('tr',
            html_writer::tag('td', userdate($row->timecreated)) .
            html_writer::tag('td', s($username)) .
            html_writer::tag('td', html_writer::tag('code', s($row->themecomponent))) .
            html_writer::tag('td', ($row->settings + $row->coresettings)) .
            html_writer::tag('td', $row->files) .
            html_writer::tag('td', $extras ? implode(', ', $extras) : '—') .
            html_writer::tag('td', $row->errors > 0
                ? html_writer::tag('span', $row->errors, ['class' => 'badge badge-warning'])
                : html_writer::tag('span', '✓', ['class' => 'text-success'])) .
            html_writer::tag('td', $action_cell)
        );
    }

    echo html_writer::tag('table',
        html_writer::tag('thead', $thead) . html_writer::tag('tbody', $rows),
        ['class' => 'table table-sm table-hover mb-0']
    );

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
