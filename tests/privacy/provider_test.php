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
 * PHPUnit tests for local_homepage_config\privacy\provider.
 *
 * Covers the full GDPR surface:
 *   - get_metadata()              — describes what is stored and why
 *   - get_contexts_for_userid()   — locates personal data for a user
 *   - get_users_in_context()      — lists users with data in a context
 *   - export_user_data()          — serialises data for a GDPR export request
 *   - delete_data_for_all_users_in_context() — wipes the whole table
 *   - delete_data_for_user()      — removes one user's rows
 *   - delete_data_for_users()     — removes a set of users' rows
 *
 * Run with:
 *   vendor/bin/phpunit local/homepage_config/tests/privacy/provider_test.php
 *
 * @package    local_homepage_config
 * @category   test
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_homepage_config\privacy\provider
 */

namespace local_homepage_config\privacy;

defined('MOODLE_INTERNAL') || die();

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use core_privacy\tests\provider_testcase;

class provider_test extends provider_testcase {

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Insert a fake import audit record for $userid and return its id.
     */
    private function insert_import(int $userid, string $theme = 'theme_boost_union'): int {
        global $DB;
        return $DB->insert_record('local_homepage_config_import', (object)[
            'userid'         => $userid,
            'timecreated'    => time(),
            'themecomponent' => $theme,
            'settings'       => 10,
            'coresettings'   => 2,
            'files'          => 5,
            'menus'          => 1,
            'flavours'       => 0,
            'blocks'         => 3,
            'errors'         => 0,
            'restoreblocks'  => 0,
        ]);
    }

    // =========================================================================
    // Metadata
    // =========================================================================

    /**
     * The provider must declare the import table and all its personal-data fields.
     */
    public function test_get_metadata_declares_import_table(): void {
        $collection = new \core_privacy\local\metadata\collection('local_homepage_config');
        $result     = provider::get_metadata($collection);

        // get_metadata() must return the same collection it received.
        $this->assertSame($collection, $result);

        // The import table must be listed.
        $items = $collection->get_collection();
        $names = array_map(fn($item) => $item->get_name(), $items);
        $this->assertContains('local_homepage_config_import', $names,
            'get_metadata() must declare the local_homepage_config_import table');
    }

    // =========================================================================
    // Context discovery
    // =========================================================================

    /**
     * get_contexts_for_userid() must return the system context when the user
     * has at least one import record, and an empty list otherwise.
     */
    public function test_get_contexts_for_userid_with_and_without_records(): void {
        $this->resetAfterTest();

        $user_with    = $this->getDataGenerator()->create_user();
        $user_without = $this->getDataGenerator()->create_user();

        $this->insert_import((int)$user_with->id);

        // User with record — must find system context.
        $ctxlist = provider::get_contexts_for_userid((int)$user_with->id);
        $this->assertCount(1, $ctxlist->get_contextids());
        $this->assertSame(
            \context_system::instance()->id,
            (int)$ctxlist->get_contextids()[0]
        );

        // User without record — must return empty list.
        $ctxlist_empty = provider::get_contexts_for_userid((int)$user_without->id);
        $this->assertCount(0, $ctxlist_empty->get_contextids());
    }

    // =========================================================================
    // User discovery
    // =========================================================================

    /**
     * get_users_in_context() must return every user that has at least one
     * import record; non-system contexts must be silently ignored.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $user_c = $this->getDataGenerator()->create_user(); // No record.

        $this->insert_import((int)$user_a->id);
        $this->insert_import((int)$user_b->id);

        $syscontext = \context_system::instance();
        $userlist   = new userlist($syscontext, 'local_homepage_config');
        provider::get_users_in_context($userlist);

        $ids = $userlist->get_userids();
        $this->assertContains((int)$user_a->id, $ids);
        $this->assertContains((int)$user_b->id, $ids);
        $this->assertNotContains((int)$user_c->id, $ids);

        // Non-system context — must return nothing.
        $coursecontext = \context_course::instance(SITEID);
        $userlist2     = new userlist($coursecontext, 'local_homepage_config');
        provider::get_users_in_context($userlist2);
        $this->assertCount(0, $userlist2->get_userids());
    }

    // =========================================================================
    // Export
    // =========================================================================

    /**
     * export_user_data() must write the user's import rows to the writer,
     * including all expected fields.
     */
    public function test_export_user_data_writes_import_rows(): void {
        $this->resetAfterTest();

        $user = $this->getDataGenerator()->create_user();
        $this->insert_import((int)$user->id, 'theme_boost_union');
        $this->insert_import((int)$user->id, 'theme_boost_union');

        $syscontext = \context_system::instance();
        $contextids = [$syscontext->id];
        $contextlist = new approved_contextlist($user, 'local_homepage_config', $contextids);

        provider::export_user_data($contextlist);

        $writer = writer::with_context($syscontext);
        $data   = $writer->get_data([
            get_string('pluginname', 'local_homepage_config'),
            get_string('history_title', 'local_homepage_config'),
        ]);

        $this->assertNotEmpty($data, 'writer must have received data for this context');
        $this->assertObjectHasProperty('imports', $data);
        $this->assertCount(2, $data->imports, 'Both import rows must be exported');

        // Spot-check field names.
        $row = $data->imports[0];
        foreach (['timecreated', 'themecomponent', 'settings', 'files', 'errors'] as $field) {
            $this->assertArrayHasKey($field, (array)$row, "Exported row must contain field '$field'");
        }
    }

    /**
     * export_user_data() must write nothing when the user has no import rows.
     */
    public function test_export_user_data_empty_when_no_records(): void {
        $this->resetAfterTest();

        $user       = $this->getDataGenerator()->create_user();
        $syscontext = \context_system::instance();
        $contextlist = new approved_contextlist($user, 'local_homepage_config', [$syscontext->id]);

        provider::export_user_data($contextlist);

        $writer = writer::with_context($syscontext);
        $data   = $writer->get_data([
            get_string('pluginname', 'local_homepage_config'),
            get_string('history_title', 'local_homepage_config'),
        ]);
        $this->assertEmpty($data);
    }

    // =========================================================================
    // Deletion — all users in context
    // =========================================================================

    /**
     * delete_data_for_all_users_in_context() must truncate the import table
     * when called with the system context, and do nothing for other contexts.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $this->insert_import((int)$user_a->id);
        $this->insert_import((int)$user_b->id);

        $this->assertSame(2, (int)$DB->count_records('local_homepage_config_import'));

        // Non-system context — must be a no-op.
        provider::delete_data_for_all_users_in_context(\context_course::instance(SITEID));
        $this->assertSame(2, (int)$DB->count_records('local_homepage_config_import'));

        // System context — must wipe everything.
        provider::delete_data_for_all_users_in_context(\context_system::instance());
        $this->assertSame(0, (int)$DB->count_records('local_homepage_config_import'));
    }

    // =========================================================================
    // Deletion — single user
    // =========================================================================

    /**
     * delete_data_for_user() must remove only the rows belonging to that user.
     */
    public function test_delete_data_for_user_removes_only_that_user(): void {
        global $DB;
        $this->resetAfterTest();

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $this->insert_import((int)$user_a->id);
        $this->insert_import((int)$user_b->id);

        $syscontext  = \context_system::instance();
        $contextlist = new approved_contextlist($user_a, 'local_homepage_config', [$syscontext->id]);
        provider::delete_data_for_user($contextlist);

        $this->assertFalse(
            $DB->record_exists('local_homepage_config_import', ['userid' => $user_a->id]),
            'User A records must be deleted'
        );
        $this->assertTrue(
            $DB->record_exists('local_homepage_config_import', ['userid' => $user_b->id]),
            'User B records must be untouched'
        );
    }

    // =========================================================================
    // Deletion — multiple users
    // =========================================================================

    /**
     * delete_data_for_users() must remove rows for every user in the list.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();

        $user_a = $this->getDataGenerator()->create_user();
        $user_b = $this->getDataGenerator()->create_user();
        $user_c = $this->getDataGenerator()->create_user(); // Not in the list.
        $this->insert_import((int)$user_a->id);
        $this->insert_import((int)$user_b->id);
        $this->insert_import((int)$user_c->id);

        $syscontext = \context_system::instance();
        $userlist   = new approved_userlist($syscontext, 'local_homepage_config',
                                            [(int)$user_a->id, (int)$user_b->id]);
        provider::delete_data_for_users($userlist);

        $this->assertFalse($DB->record_exists('local_homepage_config_import', ['userid' => $user_a->id]));
        $this->assertFalse($DB->record_exists('local_homepage_config_import', ['userid' => $user_b->id]));
        $this->assertTrue($DB->record_exists('local_homepage_config_import',  ['userid' => $user_c->id]),
            'User C was not in the approved list and must be untouched');
    }
}
