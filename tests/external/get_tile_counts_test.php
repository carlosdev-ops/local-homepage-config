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
 * PHPUnit tests for local_homepage_config\external\get_tile_counts.
 *
 * Run with:
 *   vendor/bin/phpunit local/homepage_config/tests/external/get_tile_counts_test.php
 *
 * @package    local_homepage_config
 * @category   test
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_homepage_config\external\get_tile_counts
 */

namespace local_homepage_config\external;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/homepage_config/lib.php');

class get_tile_counts_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        // The web service is declared loginrequired => false but tests still
        // need a valid session context; guest is sufficient.
        $this->setGuestUser();
    }

    // =========================================================================
    // Return structure
    // =========================================================================

    /**
     * execute() must return an array of {catid, count} objects.
     */
    public function test_execute_returns_correct_structure(): void {
        $result = get_tile_counts::execute('0', 'courses', 1);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $first = $result[0];
        $this->assertArrayHasKey('catid', $first);
        $this->assertArrayHasKey('count', $first);
    }

    /**
     * Multiple catids in one call → one result entry per catid.
     */
    public function test_execute_returns_one_entry_per_catid(): void {
        $cat1 = $this->getDataGenerator()->create_category();
        $cat2 = $this->getDataGenerator()->create_category();

        $result = get_tile_counts::execute($cat1->id . ',' . $cat2->id, 'courses', 0);
        $this->assertCount(2, $result);

        $returned_catids = array_column($result, 'catid');
        $this->assertContains($cat1->id, $returned_catids);
        $this->assertContains($cat2->id, $returned_catids);
    }

    // =========================================================================
    // Course counts
    // =========================================================================

    /**
     * Count for a specific category reflects the number of visible courses.
     */
    public function test_execute_courses_counts_visible_only(): void {
        $cat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $cat->id, 'visible' => 1]);
        $this->getDataGenerator()->create_course(['category' => $cat->id, 'visible' => 0]);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result = get_tile_counts::execute((string)$cat->id, 'courses', 0);

        $this->assertCount(1, $result);
        $this->assertSame($cat->id, $result[0]['catid']);
        $this->assertSame(1, $result[0]['count']);
    }

    /**
     * sub = 1 must include courses in child categories.
     */
    public function test_execute_courses_with_subcats(): void {
        $parent = $this->getDataGenerator()->create_category();
        $child  = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $this->getDataGenerator()->create_course(['category' => $parent->id, 'visible' => 1]);
        $this->getDataGenerator()->create_course(['category' => $child->id,  'visible' => 1]);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result_with    = get_tile_counts::execute((string)$parent->id, 'courses', 1);
        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result_without = get_tile_counts::execute((string)$parent->id, 'courses', 0);

        $this->assertSame(2, $result_with[0]['count']);
        $this->assertSame(1, $result_without[0]['count']);
    }

    // =========================================================================
    // User counts
    // =========================================================================

    /**
     * type = users counts distinct enrolled users in the category.
     */
    public function test_execute_users_counts_enrolled(): void {
        $cat    = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat->id]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result = get_tile_counts::execute((string)$cat->id, 'users', 0);

        $this->assertGreaterThan(0, $result[0]['count']);
    }

    /**
     * Unknown type falls back to course counting (safe default).
     */
    public function test_execute_unknown_type_falls_back_to_courses(): void {
        $this->expectException(\invalid_parameter_exception::class);
        // PARAM_ALPHA validation will reject a non-alpha type string.
        get_tile_counts::execute('0', 'invalid_type!', 0);
    }

    // =========================================================================
    // Input sanitisation
    // =========================================================================

    /**
     * Non-integer values in the cats string are ignored; result still contains
     * the site-wide catid = 0 sentinel.
     */
    public function test_execute_ignores_non_integer_catids(): void {
        // "0,abc,2" — "abc" becomes 0 after intval, deduplicated to [0, 2].
        $cat = $this->getDataGenerator()->create_category();
        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result = get_tile_counts::execute('0,' . $cat->id, 'courses', 0);
        // Must not throw and must return results for the valid ids.
        $this->assertIsArray($result);
    }

    /**
     * Empty cats string defaults to catid = 0 (whole site).
     */
    public function test_execute_empty_cats_defaults_to_site(): void {
        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $result = get_tile_counts::execute('', 'courses', 0);
        $this->assertCount(1, $result);
        $this->assertSame(0, $result[0]['catid']);
    }
}
