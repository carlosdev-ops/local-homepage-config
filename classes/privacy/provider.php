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
 * Privacy provider for local_homepage_config.
 *
 * The plugin stores one category of personal data: rows in the
 * local_homepage_config_import table that record which administrator
 * performed each configuration import (userid + timestamp + counts).
 * All data lives in the system context.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\writer;

class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    // =========================================================================
    // Metadata
    // =========================================================================

    /**
     * Describe what personal data this plugin stores and why.
     *
     * @param collection $collection  Metadata collection to populate.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_homepage_config_import',
            [
                'userid'         => 'privacy:metadata:import:userid',
                'timecreated'    => 'privacy:metadata:import:timecreated',
                'themecomponent' => 'privacy:metadata:import:themecomponent',
                'settings'       => 'privacy:metadata:import:settings',
                'coresettings'   => 'privacy:metadata:import:coresettings',
                'files'          => 'privacy:metadata:import:files',
                'menus'          => 'privacy:metadata:import:menus',
                'flavours'       => 'privacy:metadata:import:flavours',
                'blocks'         => 'privacy:metadata:import:blocks',
                'errors'         => 'privacy:metadata:import:errors',
                'restoreblocks'  => 'privacy:metadata:import:restoreblocks',
                'snapshotfileid' => 'privacy:metadata:import:snapshotfileid',
            ],
            'privacy:metadata:import'
        );

        return $collection;
    }

    // =========================================================================
    // Context discovery
    // =========================================================================

    /**
     * Return the list of contexts that contain personal data for the given user.
     *
     * Import logs are system-level records, so we return the system context
     * when at least one import row exists for the user.
     *
     * @param int $userid
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();

        if ($DB->record_exists('local_homepage_config_import', ['userid' => $userid])) {
            $contextlist->add_system_context();
        }

        return $contextlist;
    }

    /**
     * Return the list of users who have data within the given context.
     *
     * Only meaningful for the system context — all other contexts are ignored.
     *
     * @param userlist $userlist
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        $sql = 'SELECT DISTINCT userid FROM {local_homepage_config_import}';
        $userlist->add_from_sql('userid', $sql, []);
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * Export all personal data for the user within the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }

            $rows = $DB->get_records(
                'local_homepage_config_import',
                ['userid' => $userid],
                'timecreated ASC'
            );

            if (empty($rows)) {
                continue;
            }

            $data = array_map(static function(\stdClass $row): array {
                return [
                    'timecreated'    => transform::datetime($row->timecreated),
                    'themecomponent' => $row->themecomponent,
                    'settings'       => (int)$row->settings,
                    'coresettings'   => (int)$row->coresettings,
                    'files'          => (int)$row->files,
                    'menus'          => (int)$row->menus,
                    'flavours'       => (int)$row->flavours,
                    'blocks'         => (int)$row->blocks,
                    'errors'         => (int)$row->errors,
                    'restoreblocks'  => (bool)$row->restoreblocks,
                ];
            }, $rows);

            writer::with_context($context)->export_data(
                [get_string('pluginname', 'local_homepage_config'), get_string('history_title', 'local_homepage_config')],
                (object)['imports' => array_values($data)]
            );
        }
    }

    // =========================================================================
    // Deletion
    // =========================================================================

    /**
     * Delete all personal data for all users within the given context.
     *
     * Only acts on the system context — that is where all import logs live.
     *
     * @param \context $context
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_system) {
            return;
        }

        $DB->delete_records('local_homepage_config_import');
    }

    /**
     * Delete all personal data for the given user within the approved contexts.
     *
     * @param approved_contextlist $contextlist
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int)$contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_system) {
                continue;
            }
            $DB->delete_records('local_homepage_config_import', ['userid' => $userid]);
        }
    }

    /**
     * Delete personal data for multiple users within a context.
     *
     * @param approved_userlist $userlist
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_system) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userlist->get_userids(), SQL_PARAMS_NAMED);
        $DB->delete_records_select('local_homepage_config_import', "userid $insql", $params);
    }
}
