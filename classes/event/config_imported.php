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
 * Event fired when a visual configuration is imported.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an admin imports a homepage visual configuration ZIP.
 *
 * Recorded in the Moodle event log. The 'other' data contains the import
 * statistics (settings, files, menus, flavours, blocks, errors count) so
 * that the scope of the change is visible in the audit trail.
 */
class config_imported extends \core\event\base {

    protected function init(): void {
        $this->data['crud']     = 'u'; // Update operation (import overwrites site config).
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context          = \context_system::instance();
    }

    public static function get_name(): string {
        return get_string('event_config_imported', 'local_homepage_config');
    }

    public function get_description(): string {
        $component = $this->other['themecomponent'] ?? 'unknown';
        $stats     = $this->other['stats'] ?? [];
        $errors    = (int)($stats['error_count'] ?? 0);
        return "User {$this->userid} imported a visual configuration for theme component '{$component}'. " .
               "Settings: {$stats['settings']}, files: {$stats['files']}, menus: {$stats['menus']}, " .
               "flavours: {$stats['flavours']}, blocks: {$stats['blocks']}, errors: {$errors}.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/homepage_config/index.php');
    }
}
