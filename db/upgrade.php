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
 * Upgrade steps for local_homepage_config.
 *
 * Each savepoint corresponds to a version number declared in version.php.
 * Add a new block for every schema or data migration; never remove old blocks.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion  The version the site is currently at.
 * @return bool
 */
function xmldb_local_homepage_config_upgrade(int $oldversion): bool {

    // -------------------------------------------------------------------------
    // 3.2.0 — 2026033100
    //
    // Introduced upgrade.php (no schema changes).
    // Migrates any legacy raw plugin setting 'theme_component' stored under
    // the old key 'themecomponent_legacy' to the canonical 'themecomponent'
    // key, in case the setting was written by a very early pre-release build.
    // -------------------------------------------------------------------------
    if ($oldversion < 2026033100) {

        $legacy = get_config('local_homepage_config', 'themecomponent_legacy');
        if ($legacy !== false && get_config('local_homepage_config', 'themecomponent') === false) {
            set_config('themecomponent', $legacy, 'local_homepage_config');
            unset_config('themecomponent_legacy', 'local_homepage_config');
        }

        upgrade_plugin_savepoint(true, 2026033100, 'local', 'homepage_config');
    }

    return true;
}
