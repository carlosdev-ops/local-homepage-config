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
 * Event fired when a visual configuration is exported.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\event;

defined('MOODLE_INTERNAL') || die();

/**
 * Event triggered when an admin exports the homepage visual configuration.
 *
 * Recorded in the Moodle event log so that configuration changes can be audited.
 */
class config_exported extends \core\event\base {

    protected function init(): void {
        $this->data['crud']     = 'r'; // Read operation (export = no data destruction).
        $this->data['edulevel'] = self::LEVEL_OTHER;
        $this->context          = \context_system::instance();
    }

    public static function get_name(): string {
        return get_string('event_config_exported', 'local_homepage_config');
    }

    public function get_description(): string {
        $component = $this->other['themecomponent'] ?? 'unknown';
        return "User {$this->userid} exported the visual configuration for theme component '{$component}'.";
    }

    public function get_url(): \moodle_url {
        return new \moodle_url('/local/homepage_config/index.php');
    }
}
