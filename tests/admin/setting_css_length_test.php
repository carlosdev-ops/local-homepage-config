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
 * PHPUnit tests for setting_css_length::validate().
 *
 * Run with:
 *   vendor/bin/phpunit local/homepage_config/tests/admin/setting_css_length_test.php
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\tests\admin;

use local_homepage_config\admin\setting_css_length;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/adminlib.php');

/**
 * Tests for {@see setting_css_length}.
 *
 * @covers \local_homepage_config\admin\setting_css_length
 */
class setting_css_length_test extends \advanced_testcase {

    /** @var setting_css_length */
    private setting_css_length $setting;

    protected function setUp(): void {
        parent::setUp();
        $this->setting = new setting_css_length(
            'local_homepage_config/bannerheight',
            'Banner height',
            '',
            '',
            PARAM_TEXT
        );
    }

    /**
     * Empty string is always valid (disables the feature).
     */
    public function test_empty_string_is_valid(): void {
        $this->assertTrue($this->setting->validate(''));
        $this->assertTrue($this->setting->validate('   '));
    }

    /**
     * @dataProvider valid_css_lengths_provider
     */
    public function test_valid_css_lengths(string $value): void {
        $this->assertTrue($this->setting->validate($value));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function valid_css_lengths_provider(): array {
        return [
            'pixels integer'    => ['200px'],
            'pixels decimal'    => ['1.5px'],
            'em integer'        => ['10em'],
            'em decimal'        => ['1.25em'],
            'rem integer'       => ['2rem'],
            'rem decimal'       => ['0.75rem'],
            'viewport height'   => ['30vh'],
            'viewport width'    => ['50vw'],
            'percentage'        => ['80%'],
            'percentage 100'    => ['100%'],
            'single digit px'   => ['1px'],
            // validate() trims input before matching — leading/trailing spaces are valid.
            'leading space'     => [' 10px'],
            'trailing space'    => ['200px '],
        ];
    }

    /**
     * @dataProvider invalid_css_lengths_provider
     */
    public function test_invalid_css_lengths_return_error_string(string $value): void {
        $result = $this->setting->validate($value);
        $this->assertIsString($result, "Expected error string for input: $value");
        $this->assertNotEmpty($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalid_css_lengths_provider(): array {
        return [
            'unknown unit'        => ['200pixels'],
            'plain text'          => ['invalid'],
            'letters only'        => ['abc'],
            'number without unit' => ['12'],
            'negative value'      => ['-10px'],
            'unit only'           => ['px'],
            'pt unit'             => ['12pt'],
            'cm unit'             => ['5cm'],
        ];
    }
}
