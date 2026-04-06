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
 * CLI script — export the homepage/theme configuration to a ZIP file.
 *
 * Usage:
 *   php local/homepage_config/cli/export.php [--output=/path/to/file.zip]
 *
 * Options:
 *   -o, --output=PATH   Destination path for the ZIP file.
 *                       Default: ./homepage_config_YYYYMMDD_HHMMSS.zip
 *   -h, --help          Show this help message.
 *
 * Example:
 *   php local/homepage_config/cli/export.php --output=/backups/moodle_theme.zip
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognised] = cli_get_params(
    ['output' => false, 'help' => false],
    ['o' => 'output', 'h' => 'help']
);

if ($unrecognised) {
    $unrecognised = implode(PHP_EOL . '  ', $unrecognised);
    cli_error("Unrecognised options:\n  $unrecognised\nRun with --help for usage.");
}

if ($options['help']) {
    cli_writeln(
        "Export the homepage/theme configuration to a ZIP file.

Usage:
  php local/homepage_config/cli/export.php [--output=PATH]

Options:
  -o, --output=PATH   Destination path for the ZIP (default: ./homepage_config_DATE.zip)
  -h, --help          Show this help message

Example:
  php local/homepage_config/cli/export.php --output=/backups/moodle_theme.zip"
    );
    exit(0);
}

// Resolve output path.
$output = $options['output'] ?: (getcwd() . '/homepage_config_' . date('Ymd_His') . '.zip');
$output = (string)$output;

// Ensure parent directory exists.
$parent = dirname($output);
if (!is_dir($parent)) {
    cli_error("Output directory does not exist: $parent");
}
if (!is_writable($parent)) {
    cli_error("Output directory is not writable: $parent");
}

cli_writeln('Exporting configuration…');

try {
    $tmppath = \local_homepage_config\manager::export_to_zip();
} catch (\moodle_exception $e) {
    cli_error('Export failed: ' . $e->getMessage());
}

if (!rename($tmppath, $output)) {
    // rename() may fail across filesystems — fall back to copy + unlink.
    if (!copy($tmppath, $output)) {
        @unlink($tmppath);
        cli_error("Could not write ZIP to: $output");
    }
    @unlink($tmppath);
}

set_config('last_exported', time(), 'local_homepage_config');

$size = round(filesize($output) / 1024, 1);
cli_writeln("Export successful ({$size} KB): $output");
exit(0);
