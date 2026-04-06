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
 * External function: get_tile_counts
 *
 * Returns live course/user counts per category. Replaces the former raw PHP
 * endpoint tiles_data.php, gaining Moodle rate-limiting, token auth, and
 * API logging at no cost.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/externallib.php');
// Load the plugin's lib.php for the shared count/resolve helpers.
require_once($CFG->dirroot . '/local/homepage_config/lib.php');

use external_api;
use external_function_parameters;
use external_multiple_structure;
use external_single_structure;
use external_value;

class get_tile_counts extends external_api {

    /**
     * Parameter definition.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cats' => new external_value(PARAM_TEXT, 'Comma-separated category IDs (0 = whole site)',
                VALUE_DEFAULT, '0'),
            'type' => new external_value(PARAM_ALPHA, 'Count type: courses or users',
                VALUE_DEFAULT, 'courses'),
            'sub'  => new external_value(PARAM_INT,  'Include subcategories: 1 = yes, 0 = no',
                VALUE_DEFAULT, 1),
        ]);
    }

    /**
     * Return live counts for the requested categories.
     *
     * @param  string $cats  Comma-separated category IDs.
     * @param  string $type  "courses" or "users".
     * @param  int    $sub   1 = include subcategories.
     * @return array         Array of {catid, count} objects.
     */
    public static function execute(string $cats, string $type, int $sub): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'cats' => $cats,
            'type' => $type,
            'sub'  => $sub,
        ]);

        // Public read — system context is always accessible.
        self::validate_context(\context_system::instance());

        $catids = array_values(array_filter(array_map('intval', explode(',', $params['cats']))));
        if (empty($catids)) {
            $catids = [0];
        }
        $type    = in_array($params['type'], ['courses', 'users'], true) ? $params['type'] : 'courses';
        $subcats = (bool)$params['sub'];

        $result = [];
        foreach ($catids as $catid) {
            $count = ($type === 'courses')
                ? local_homepage_config_count_courses($catid, $subcats, $DB)
                : local_homepage_config_count_users($catid, $subcats, $DB);

            $result[] = ['catid' => $catid, 'count' => $count];
        }

        return $result;
    }

    /**
     * Return value definition.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'catid' => new external_value(PARAM_INT, 'Category ID (0 = whole site)'),
                'count' => new external_value(PARAM_INT, 'Live count'),
            ])
        );
    }
}
