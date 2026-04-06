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
 * PHPUnit tests for local_homepage_config lib.php helpers.
 *
 * Covers:
 *   - local_homepage_config_resolve_catids()
 *   - local_homepage_config_count_courses()
 *   - local_homepage_config_count_users()
 *
 * Run with:
 *   vendor/bin/phpunit local/homepage_config/tests/lib_test.php
 *
 * @package    local_homepage_config
 * @category   test
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/homepage_config/lib.php');

class lib_test extends \advanced_testcase {

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    // =========================================================================
    // local_homepage_config_resolve_catids
    // =========================================================================

    /**
     * catid <= 0 returns an empty array (whole-site sentinel).
     */
    public function test_resolve_catids_zero_returns_empty(): void {
        global $DB;
        $this->assertSame([], local_homepage_config_resolve_catids(0, true, $DB));
        $this->assertSame([], local_homepage_config_resolve_catids(-1, false, $DB));
    }

    /**
     * When subcats = false only the requested category id is returned.
     */
    public function test_resolve_catids_no_subcats_returns_self(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        $this->assertSame([$cat->id], local_homepage_config_resolve_catids($cat->id, false, $DB));
    }

    /**
     * When subcats = true, child category ids are included.
     */
    public function test_resolve_catids_with_subcats_includes_children(): void {
        global $DB;
        $parent = $this->getDataGenerator()->create_category();
        $child1 = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $child2 = $this->getDataGenerator()->create_category(['parent' => $parent->id]);

        $ids = local_homepage_config_resolve_catids($parent->id, true, $DB);
        sort($ids);
        $expected = [$parent->id, $child1->id, $child2->id];
        sort($expected);
        $this->assertSame($expected, $ids);
    }

    /**
     * Requesting a non-existent category returns just that id (safe fallback).
     */
    public function test_resolve_catids_nonexistent_returns_self(): void {
        global $DB;
        $this->assertSame([999999], local_homepage_config_resolve_catids(999999, true, $DB));
    }

    // =========================================================================
    // local_homepage_config_count_courses
    // =========================================================================

    /**
     * catid = 0 counts all visible courses site-wide (excluding the site course).
     */
    public function test_count_courses_whole_site(): void {
        global $DB;
        $before = local_homepage_config_count_courses(0, false, $DB);
        $this->getDataGenerator()->create_course(['visible' => 1]);
        $this->getDataGenerator()->create_course(['visible' => 1]);

        // Invalidate the tile count cache so the fresh count is returned.
        \cache::make('local_homepage_config', 'tilecounts')->purge();

        $after = local_homepage_config_count_courses(0, false, $DB);
        $this->assertSame($before + 2, $after);
    }

    /**
     * Invisible courses must not be counted.
     */
    public function test_count_courses_excludes_hidden_courses(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $cat->id, 'visible' => 0]);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $count = local_homepage_config_count_courses($cat->id, false, $DB);
        $this->assertSame(0, $count);
    }

    /**
     * catid pointing to a specific category only counts courses in that category.
     */
    public function test_count_courses_specific_category(): void {
        global $DB;
        $cat  = $this->getDataGenerator()->create_category();
        $other = $this->getDataGenerator()->create_category();
        $this->getDataGenerator()->create_course(['category' => $cat->id,   'visible' => 1]);
        $this->getDataGenerator()->create_course(['category' => $cat->id,   'visible' => 1]);
        $this->getDataGenerator()->create_course(['category' => $other->id, 'visible' => 1]);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $this->assertSame(2, local_homepage_config_count_courses($cat->id, false, $DB));
    }

    /**
     * subcats = true must include courses nested in child categories.
     */
    public function test_count_courses_includes_subcategory_courses(): void {
        global $DB;
        $parent = $this->getDataGenerator()->create_category();
        $child  = $this->getDataGenerator()->create_category(['parent' => $parent->id]);
        $this->getDataGenerator()->create_course(['category' => $parent->id, 'visible' => 1]);
        $this->getDataGenerator()->create_course(['category' => $child->id,  'visible' => 1]);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $this->assertSame(2, local_homepage_config_count_courses($parent->id, true, $DB));
        $this->assertSame(1, local_homepage_config_count_courses($parent->id, false, $DB));
    }

    /**
     * Results are cached: a second call must return the same value without a DB hit.
     */
    public function test_count_courses_is_cached(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        \cache::make('local_homepage_config', 'tilecounts')->purge();

        $first  = local_homepage_config_count_courses($cat->id, false, $DB);
        // Create a course after the first call — the cached value must not change.
        $this->getDataGenerator()->create_course(['category' => $cat->id, 'visible' => 1]);
        $second = local_homepage_config_count_courses($cat->id, false, $DB);
        $this->assertSame($first, $second);
    }

    // =========================================================================
    // local_homepage_config_count_users
    // =========================================================================

    /**
     * catid = 0 counts distinct enrolled users site-wide.
     */
    public function test_count_users_whole_site_increases_on_enrolment(): void {
        global $DB;
        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $before = local_homepage_config_count_users(0, false, $DB);

        $course = $this->getDataGenerator()->create_course();
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $after = local_homepage_config_count_users(0, false, $DB);
        $this->assertGreaterThan($before, $after);
    }

    /**
     * Users enrolled in courses outside the target category are not counted.
     */
    public function test_count_users_scoped_to_category(): void {
        global $DB;
        $cat1   = $this->getDataGenerator()->create_category();
        $cat2   = $this->getDataGenerator()->create_category();
        $course = $this->getDataGenerator()->create_course(['category' => $cat1->id]);
        $user   = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        \cache::make('local_homepage_config', 'tilecounts')->purge();
        $this->assertGreaterThan(0, local_homepage_config_count_users($cat1->id, false, $DB));
        $this->assertSame(0,        local_homepage_config_count_users($cat2->id, false, $DB));
    }

    // =========================================================================
    // local_homepage_config_render_banner
    // =========================================================================

    /**
     * No bannercfg stored → returns empty string.
     */
    public function test_render_banner_returns_empty_when_no_config(): void {
        unset_config('bannercfg', 'local_homepage_config');
        $this->assertSame('', local_homepage_config_render_banner());
    }

    /**
     * Invalid JSON in bannercfg → returns empty string without fatal error.
     */
    public function test_render_banner_returns_empty_on_invalid_json(): void {
        set_config('bannercfg', '{not valid json}', 'local_homepage_config');
        $this->assertSame('', local_homepage_config_render_banner());
    }

    /**
     * Empty JSON array in bannercfg → returns empty string.
     */
    public function test_render_banner_returns_empty_for_empty_array(): void {
        set_config('bannercfg', '[]', 'local_homepage_config');
        $this->assertSame('', local_homepage_config_render_banner());
    }

    /**
     * Slides whose html field is empty or whitespace-only are skipped;
     * if all slides are empty the function returns ''.
     */
    public function test_render_banner_skips_slides_with_empty_html(): void {
        $cfg = json_encode([['html' => ''], ['html' => '   ']]);
        set_config('bannercfg', $cfg, 'local_homepage_config');
        $this->assertSame('', local_homepage_config_render_banner());
    }

    /**
     * Valid bannercfg → returns non-empty HTML containing the slide content.
     */
    public function test_render_banner_returns_html_with_valid_config(): void {
        $cfg = json_encode([
            ['html' => '<p>Hello world</p>'],
            ['html' => '<p>Slide two</p>'],
        ]);
        set_config('bannercfg', $cfg, 'local_homepage_config');
        $html = local_homepage_config_render_banner();
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('Hello world', $html);
    }

    // =========================================================================
    // local_homepage_config_render_tiles
    // =========================================================================

    /**
     * Empty config array → returns empty string.
     */
    public function test_render_tiles_returns_empty_for_empty_array(): void {
        global $DB;
        $this->assertSame('', local_homepage_config_render_tiles([], $DB));
    }

    /**
     * Tiles without a title are skipped; if all are titleless the function returns ''.
     */
    public function test_render_tiles_skips_tiles_without_title(): void {
        global $DB;
        $cfg = [
            ['title' => '',   'type' => 'none', 'icon' => '', 'color' => 'blue'],
            ['title' => '  ', 'type' => 'none', 'icon' => '', 'color' => 'blue'],
        ];
        $this->assertSame('', local_homepage_config_render_tiles($cfg, $DB));
    }

    /**
     * A tile with type "custom" renders and displays the configured value.
     */
    public function test_render_tiles_custom_type_displays_value(): void {
        global $DB;
        $cfg = [[
            'title' => 'My Tile',
            'type'  => 'custom',
            'value' => '42',
            'icon'  => 'users',
            'color' => 'blue',
        ]];
        $html = local_homepage_config_render_tiles($cfg, $DB);
        $this->assertNotEmpty($html);
        $this->assertStringContainsString('42', $html);
        $this->assertStringContainsString('My Tile', $html);
    }

    // =========================================================================
    // local_homepage_config_invalidate_tile_cache
    // =========================================================================

    /**
     * After calling invalidate_tile_cache() a newly-created course is counted.
     */
    public function test_invalidate_tile_cache_forces_fresh_count(): void {
        global $DB;
        $cat = $this->getDataGenerator()->create_category();
        \cache::make('local_homepage_config', 'tilecounts')->purge();

        $before = local_homepage_config_count_courses($cat->id, false, $DB);
        // Create a course — still cached, so count must not change yet.
        $this->getDataGenerator()->create_course(['category' => $cat->id, 'visible' => 1]);
        $cached = local_homepage_config_count_courses($cat->id, false, $DB);
        $this->assertSame($before, $cached, 'Count should be served from cache before invalidation');

        // Invalidate — the fresh count must now be returned.
        local_homepage_config_invalidate_tile_cache();
        $after = local_homepage_config_count_courses($cat->id, false, $DB);
        $this->assertSame($before + 1, $after, 'Count should reflect new course after cache invalidation');
    }
}
