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
 * CLI script — import a homepage/theme configuration ZIP.
 *
 * Usage:
 *   php local/homepage_config/cli/import.php --file=PATH [options]
 *
 * Options:
 *   -f, --file=PATH         Path to the ZIP file to import (required).
 *   --skip=LIST             Comma-separated list of sections to skip.
 *                           Available: settings, plugin_settings, core_settings,
 *                                      files, menus, flavours
 *   --blocks                Also restore block instances (destructive — replaces
 *                           existing blocks on tracked page types).
 *   --reset-dashboards      Reset all user-customised dashboards to the new default
 *                           after import (requires --blocks).
 *   --no-snapshot           Skip the automatic pre-import snapshot.
 *                           Use when you manage backups externally.
 *   --dry-run               Show what would change without writing anything.
 *   -h, --help              Show this help message.
 *
 * Exit codes:
 *   0  Success (or dry-run completed).
 *   1  Error (file not found, incompatible format, etc.).
 *   2  Import completed with non-fatal errors.
 *
 * Examples:
 *   # Full import (all sections except blocks):
 *   php local/homepage_config/cli/import.php --file=/backups/moodle_theme.zip
 *
 *   # Import only settings and files, skip menus and flavours:
 *   php local/homepage_config/cli/import.php --file=config.zip --skip=menus,flavours
 *
 *   # Import including blocks, reset all user dashboards:
 *   php local/homepage_config/cli/import.php --file=config.zip --blocks --reset-dashboards
 *
 *   # Preview what would change without touching the database:
 *   php local/homepage_config/cli/import.php --file=config.zip --dry-run
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    [
        'file'             => false,
        'skip'             => false,
        'blocks'           => false,
        'reset-dashboards' => false,
        'no-snapshot'      => false,
        'dry-run'          => false,
        'help'             => false,
    ],
    ['f' => 'file', 'h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error("Unrecognised options:\n  $unrecognised\nRun with --help for usage.");
}

if ($options['help']) {
    cli_writeln(
        "Import a homepage/theme configuration ZIP.

Usage:
  php local/homepage_config/cli/import.php --file=PATH [options]

Options:
  -f, --file=PATH         Path to the ZIP file (required)
  --skip=LIST             Sections to skip, comma-separated:
                          settings, plugin_settings, core_settings, files, menus, flavours
  --blocks                Restore block instances (destructive)
  --reset-dashboards      Reset all user dashboards after import (requires --blocks)
  --no-snapshot           Skip pre-import snapshot
  --dry-run               Preview changes without writing to the database
  -h, --help              Show this help

Examples:
  php local/homepage_config/cli/import.php --file=config.zip
  php local/homepage_config/cli/import.php --file=config.zip --skip=menus,flavours
  php local/homepage_config/cli/import.php --file=config.zip --blocks --reset-dashboards
  php local/homepage_config/cli/import.php --file=config.zip --dry-run"
    );
    exit(0);
}

// ── Validate --file ────────────────────────────────────────────────────────────
if (!$options['file']) {
    cli_error("--file is required. Run with --help for usage.");
}

$zippath = (string)$options['file'];
if (!file_exists($zippath)) {
    cli_error("File not found: $zippath");
}
if (!is_readable($zippath)) {
    cli_error("File is not readable: $zippath");
}

// ── Build options array ────────────────────────────────────────────────────────
$skip_sections = [];
if ($options['skip']) {
    $valid_sections = ['settings', 'plugin_settings', 'core_settings', 'files', 'menus', 'flavours'];
    foreach (explode(',', (string)$options['skip']) as $section) {
        $section = trim($section);
        if (!in_array($section, $valid_sections, true)) {
            cli_error("Unknown section '$section'. Valid sections: " . implode(', ', $valid_sections));
        }
        $skip_sections[] = $section;
    }
}

$import_options = array_merge(
    \local_homepage_config\manager::IMPORT_DEFAULTS,
    ['blocks' => (bool)$options['blocks'], 'reset_dashboards' => (bool)$options['reset-dashboards']]
);
foreach ($skip_sections as $s) {
    $import_options[$s] = false;
}
if ($import_options['reset_dashboards'] && !$import_options['blocks']) {
    cli_writeln('Warning: --reset-dashboards has no effect without --blocks.');
    $import_options['reset_dashboards'] = false;
}

$take_snapshot = !$options['no-snapshot'];
$dry_run       = (bool)$options['dry-run'];

// ── DRY RUN ───────────────────────────────────────────────────────────────────
if ($dry_run) {
    cli_writeln('DRY RUN — no changes will be made.');
    cli_separator();

    $preview = \local_homepage_config\manager::diff_zip($zippath);

    if (!$preview['valid']) {
        cli_error('ZIP validation failed: ' . $preview['error']);
    }

    cli_writeln(sprintf('Theme component : %s', $preview['theme_component']));
    cli_writeln(sprintf('Format version  : %s', $preview['format_version']));
    cli_writeln(sprintf('Moodle version  : %s', $preview['moodle_version']));
    if ($preview['exported_at']) {
        cli_writeln(sprintf('Exported at     : %s', date('Y-m-d H:i:s', $preview['exported_at'])));
    }
    cli_separator();

    // Summary counts.
    cli_writeln(sprintf('Settings        : %d  (%d changed, %d added, %d unchanged)',
        $preview['settings_count'],
        $preview['diff_changed'],
        $preview['diff_added'],
        $preview['diff_unchanged']
    ));
    cli_writeln(sprintf('Files           : %d', $preview['files_count']));
    cli_writeln(sprintf('Smart Menus     : %d', $preview['menus_count']));
    cli_writeln(sprintf('Flavours        : %d', $preview['flavours_count']));
    cli_writeln(sprintf('Blocks          : %d', $preview['blocks_count']));

    // Cohort warnings.
    if ($preview['cohort_warn_menus'] > 0 || $preview['cohort_warn_flavours'] > 0) {
        cli_separator();
        cli_writeln('COHORT REFERENCES DETECTED — manual reconfiguration required after import:');
        if ($preview['cohort_warn_menus'] > 0) {
            cli_writeln(sprintf('  Smart Menu items with cohort conditions : %d', $preview['cohort_warn_menus']));
        }
        if ($preview['cohort_warn_flavours'] > 0) {
            cli_writeln(sprintf('  Flavours with cohort scope rules        : %d', $preview['cohort_warn_flavours']));
        }
    }

    // Changed settings detail.
    $changed = array_filter($preview['diff'], fn($e) => $e['status'] !== 'unchanged');
    if (!empty($changed)) {
        cli_separator();
        cli_writeln(sprintf('Changed / added settings (%d):', count($changed)));
        foreach ($changed as $entry) {
            $label  = sprintf('  [%-6s] %-50s', $entry['source'], $entry['name']);
            $status = $entry['status'] === 'added' ? '(new)' : sprintf('"%s" → "%s"',
                mb_strimwidth($entry['current'],  0, 40, '…'),
                mb_strimwidth($entry['incoming'], 0, 40, '…')
            );
            cli_writeln($label . $status);
        }
    }

    cli_separator();
    cli_writeln('Dry run complete. Use without --dry-run to apply changes.');
    exit(0);
}

// ── REAL IMPORT ───────────────────────────────────────────────────────────────
// Quick pre-flight check (version compatibility, manifest presence).
$preview = \local_homepage_config\manager::peek_zip($zippath);
if (!$preview['valid']) {
    cli_error('ZIP validation failed: ' . $preview['error']);
}

// Cohort warning — print but do not block.
if ($preview['cohort_warn_menus'] > 0 || $preview['cohort_warn_flavours'] > 0) {
    cli_writeln('Warning: cohort references detected — verify conditions manually after import.');
}

$skipped = array_keys(array_filter($import_options,
    fn($v, $k) => !$v && in_array($k, ['settings','plugin_settings','core_settings','files','menus','flavours'], true),
    ARRAY_FILTER_USE_BOTH
));

cli_writeln('Importing…');
if (!empty($skipped)) {
    cli_writeln('  Skipping: ' . implode(', ', $skipped));
}
if ($import_options['blocks']) {
    cli_writeln('  Blocks: will be restored');
}
if (!$take_snapshot) {
    cli_writeln('  Snapshot: disabled');
}

try {
    $stats = \local_homepage_config\manager::import_from_zip($zippath, $import_options, $take_snapshot);
} catch (\moodle_exception $e) {
    cli_error('Import failed: ' . $e->getMessage());
}

set_config('last_imported', time(), 'local_homepage_config');

cli_separator();
cli_writeln(sprintf('Theme settings  : %d', $stats['settings']));
cli_writeln(sprintf('Core settings   : %d', $stats['coresettings']));
cli_writeln(sprintf('Files           : %d', $stats['files']));
cli_writeln(sprintf('Smart Menus     : %d', $stats['menus']));
cli_writeln(sprintf('Flavours        : %d', $stats['flavours']));
cli_writeln(sprintf('Blocks          : %d', $stats['blocks']));
if ($stats['snapshotfileid'] > 0) {
    cli_writeln(sprintf('Snapshot file ID: %d', $stats['snapshotfileid']));
}

if (!empty($stats['errors'])) {
    cli_separator();
    cli_writeln(sprintf('Non-fatal errors (%d):', count($stats['errors'])));
    foreach ($stats['errors'] as $err) {
        cli_writeln("  $err");
    }
    cli_separator();
    cli_writeln('Import completed with errors.');
    exit(2);
}

cli_separator();
cli_writeln('Import successful.');
exit(0);
