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
 * Admin setting: text field that validates CSS length values.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\admin;

defined('MOODLE_INTERNAL') || die();

// admin_setting_configtext is defined in lib/adminlib.php which is loaded
// before settings.php runs, but not necessarily before autoloading kicks in.
// The global require_once ensures the parent class is always available.
global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Text field that validates its value is either empty or a valid CSS length.
 *
 * Accepts: <number>[.<decimals>]<unit>  where unit ∈ px|em|rem|vh|vw|%
 * Rejects: any other string — shows an inline error on the settings page
 * so the admin knows immediately why the style is not being applied.
 *
 * The same regex is used in lib.php when building the inline style, so
 * this class is the UI counterpart of that server-side guard.
 */
class setting_css_length extends \admin_setting_configtext {

    /**
     * Validate the submitted value.
     *
     * @param  string $data  Value submitted by the admin.
     * @return string|bool   Error string on failure, true on success.
     */
    public function validate($data) {
        $data = trim((string)$data);
        if ($data === '') {
            return true; // Empty = disabled, always valid.
        }
        if (preg_match('/^\d+(\.\d+)?(px|em|rem|vh|vw|%)$/', $data)) {
            return true;
        }
        return get_string('banner_css_length_invalid', 'local_homepage_config');
    }
}
