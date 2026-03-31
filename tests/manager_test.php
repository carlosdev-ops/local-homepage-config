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
 * PHPUnit tests for local_homepage_config\manager.
 *
 * Covers the public API — export_to_zip(), import_from_zip(), get_summary() —
 * which exercises all private helpers indirectly.
 *
 * Run with:
 *   vendor/bin/phpunit local/homepage_config/tests/manager_test.php
 *
 * @package    local_homepage_config
 * @category   test
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_homepage_config\manager
 */

namespace local_homepage_config;

defined('MOODLE_INTERNAL') || die();

class manager_test extends \advanced_testcase {

    // =========================================================================
    // Setup
    // =========================================================================

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Build a minimal ZIP file in the temp directory and return its path.
     *
     * @param array $files  Map of filename => string content.
     * @return string  Absolute path to the created ZIP.
     */
    private function make_zip(array $files): string {
        global $CFG;
        $path = $CFG->tempdir . '/test_hpc_' . uniqid() . '.zip';
        $zip  = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        return $path;
    }

    /**
     * Return a JSON-encoded manifest with the given format version.
     */
    private function make_manifest(string $version = '2.0', string $component = 'theme_boost_union'): string {
        global $CFG;
        return json_encode([
            'format_version'  => $version,
            'exported_at'     => date('c'),
            'moodle_version'  => $CFG->version,
            'moodle_release'  => $CFG->release,
            'theme_component' => $component,
            'plugin_config'   => [],
        ]);
    }

    // =========================================================================
    // Export — structure
    // =========================================================================

    /**
     * export_to_zip() must return a path to an existing ZIP file.
     */
    public function test_export_returns_existing_zip_path(): void {
        $path = manager::export_to_zip();
        $this->assertFileExists($path);
        $this->assertStringEndsWith('.zip', $path);
    }

    /**
     * The exported ZIP must contain all expected top-level JSON files.
     */
    public function test_export_zip_contains_required_entries(): void {
        $path = manager::export_to_zip();

        $zip = new \ZipArchive();
        $this->assertSame(\ZipArchive::ER_OK, $zip->open($path), 'Cannot open exported ZIP');

        foreach (['manifest.json', 'settings.json', 'plugin_settings.json',
                  'core_settings.json', 'blocks.json'] as $entry) {
            $this->assertNotFalse($zip->getFromName($entry), "$entry missing from export ZIP");
        }
        $zip->close();
    }

    /**
     * manifest.json must declare the current format version and contain expected keys.
     */
    public function test_export_manifest_structure(): void {
        $path = manager::export_to_zip();

        $zip = new \ZipArchive();
        $zip->open($path);
        $manifest = json_decode($zip->getFromName('manifest.json'), true);
        $zip->close();

        foreach (['format_version', 'exported_at', 'moodle_version', 'moodle_release',
                  'theme_component', 'plugin_config'] as $key) {
            $this->assertArrayHasKey($key, $manifest, "manifest.json missing key: $key");
        }
        $this->assertSame(manager::EXPORT_FORMAT_VERSION, $manifest['format_version']);
    }

    /**
     * Theme settings seeded into config_plugins must appear in settings.json.
     */
    public function test_export_includes_seeded_theme_settings(): void {
        set_config('phpunit_testkey', 'phpunit_value', 'theme_boost_union');

        $path = manager::export_to_zip();
        $zip  = new \ZipArchive();
        $zip->open($path);
        $settings = json_decode($zip->getFromName('settings.json'), true);
        $zip->close();

        $this->assertArrayHasKey('phpunit_testkey', $settings);
        $this->assertSame('phpunit_value', $settings['phpunit_testkey']);
    }

    /**
     * core_settings.json must never contain non-whitelisted sensitive keys.
     */
    public function test_export_core_settings_respects_whitelist(): void {
        $path = manager::export_to_zip();
        $zip  = new \ZipArchive();
        $zip->open($path);
        $core = json_decode($zip->getFromName('core_settings.json'), true);
        $zip->close();

        foreach (['smtphost', 'smtpuser', 'smtppass', 'passwordsaltmain', 'dbhost', 'dbpass'] as $key) {
            $this->assertArrayNotHasKey($key, $core,
                "Sensitive key '$key' must not appear in core_settings.json");
        }
    }

    // =========================================================================
    // Import — happy path
    // =========================================================================

    /**
     * Theme settings must survive a full export → wipe → import round-trip.
     */
    public function test_import_restores_theme_settings_roundtrip(): void {
        global $DB;

        set_config('phpunit_roundtrip', 'round_trip_value', 'theme_boost_union');
        $path = manager::export_to_zip();

        // Wipe the seeded value.
        $DB->delete_records('config_plugins', [
            'plugin' => 'theme_boost_union',
            'name'   => 'phpunit_roundtrip',
        ]);
        $this->assertFalse(get_config('theme_boost_union', 'phpunit_roundtrip'));

        $stats = manager::import_from_zip($path);

        $this->assertSame('round_trip_value', get_config('theme_boost_union', 'phpunit_roundtrip'));
        $this->assertGreaterThan(0, $stats['settings']);
        $this->assertEmpty($stats['errors']);
    }

    /**
     * import_from_zip() must return an array with all expected stat keys.
     */
    public function test_import_returns_all_stat_keys(): void {
        $path  = manager::export_to_zip();
        $stats = manager::import_from_zip($path);

        foreach (['settings', 'core_settings', 'files', 'menus', 'flavours', 'blocks', 'errors'] as $key) {
            $this->assertArrayHasKey($key, $stats, "Stats array missing key: $key");
        }
    }

    // =========================================================================
    // Import — error / edge cases
    // =========================================================================

    /**
     * A ZIP with a format_version newer than EXPORT_FORMAT_VERSION must be rejected.
     */
    public function test_import_rejects_zip_with_newer_format_version(): void {
        $path = $this->make_zip([
            'manifest.json' => $this->make_manifest('99.0'),
            'settings.json' => '{}',
        ]);

        $this->expectException(\moodle_exception::class);
        manager::import_from_zip($path);
    }

    /**
     * Passing a file that is not a valid ZIP must throw a moodle_exception.
     */
    public function test_import_rejects_non_zip_file(): void {
        global $CFG;
        $path = $CFG->tempdir . '/not_a_zip_' . uniqid() . '.zip';
        file_put_contents($path, 'this is plaintext, not a ZIP archive');

        $this->expectException(\moodle_exception::class);
        manager::import_from_zip($path);
    }

    /**
     * A ZIP without manifest.json must be tolerated (legacy format support).
     */
    public function test_import_tolerates_missing_manifest(): void {
        $path = $this->make_zip([
            'settings.json'      => '{}',
            'core_settings.json' => '{}',
            'blocks.json'        => '[]',
        ]);

        // Must not throw — legacy ZIPs are imported with a debugging notice only.
        $stats = manager::import_from_zip($path);
        $this->assertIsArray($stats);
        $this->assertDebuggingCalled(); // Expects the "absent from ZIP" debugging message.
    }

    /**
     * Whitelisted core config keys must be restored; non-whitelisted keys must be ignored
     * even if present in core_settings.json.
     */
    public function test_import_core_settings_whitelist_enforced(): void {
        // Build a ZIP that tries to set both a whitelisted and a non-whitelisted key.
        $core = json_encode([
            'defaulthomepage' => '1',       // Whitelisted default.
            'smtphost'        => 'evil.host', // NOT whitelisted — must be ignored.
        ]);
        $path = $this->make_zip([
            'manifest.json'      => $this->make_manifest(),
            'settings.json'      => '{}',
            'core_settings.json' => $core,
            'blocks.json'        => '[]',
        ]);

        manager::import_from_zip($path);

        // The non-whitelisted key must not have been written.
        $this->assertNotSame('evil.host', get_config('core', 'smtphost'));
    }

    // =========================================================================
    // Security — block configdata sanitisation (#2)
    // =========================================================================

    /**
     * When blocks.json contains raw configdata (no _configdata_json), the import
     * must sanitize it through unserialize/serialize before storing, so that
     * any PHP object class information is stripped.
     */
    public function test_import_sanitizes_raw_block_configdata(): void {
        global $DB;

        // Serialize a simple stdClass — represents the "raw" fallback path.
        $raw_configdata = base64_encode(serialize((object)['title' => 'TestBlock', 'text' => 'Hello']));

        $blocks = [[
            'blockname'         => 'html',
            'pagetypepattern'   => 'site-index',
            'defaultregion'     => 'side-pre',
            'defaultweight'     => 0,
            'showinsubcontexts' => 0,
            'requiredbytheme'   => 0,
            'subpagepattern'    => null,
            'configdata'        => $raw_configdata,
            // Deliberately no _configdata_json — forces the sanitisation fallback.
        ]];

        $path = $this->make_zip([
            'manifest.json'      => $this->make_manifest(),
            'settings.json'      => '{}',
            'core_settings.json' => '{}',
            'blocks.json'        => json_encode($blocks),
        ]);

        $stats = manager::import_from_zip($path, true);

        // The block must have been created.
        $this->assertGreaterThan(0, $stats['blocks']);

        // The stored configdata must be safely deserializable.
        $stored = $DB->get_record('block_instances', [
            'blockname'       => 'html',
            'pagetypepattern' => 'site-index',
        ]);
        $this->assertNotFalse($stored, 'Block instance not found after import');

        $decoded = @unserialize(base64_decode($stored->configdata), ['allowed_classes' => false]);
        // Must decode without error (not return false).
        $this->assertNotFalse($decoded, 'Stored configdata could not be unserialized after import');
    }

    // =========================================================================
    // Summary
    // =========================================================================

    /**
     * get_summary() must return an array containing all expected keys.
     */
    public function test_get_summary_returns_all_expected_keys(): void {
        $summary = manager::get_summary();

        foreach (['settings_count', 'files_count', 'menus_count', 'flavours_count',
                  'blocks_count', 'last_exported', 'last_imported'] as $key) {
            $this->assertArrayHasKey($key, $summary, "Summary missing key: $key");
        }
    }

    /**
     * Timestamps must be null when no export/import has ever occurred.
     */
    public function test_get_summary_timestamps_null_before_any_operation(): void {
        unset_config('last_exported', 'local_homepage_config');
        unset_config('last_imported', 'local_homepage_config');

        $summary = manager::get_summary();
        $this->assertNull($summary['last_exported'], 'last_exported should be null before first export');
        $this->assertNull($summary['last_imported'], 'last_imported should be null before first import');
    }

    /**
     * Timestamps stored in config_plugins must be returned correctly by get_summary().
     */
    public function test_get_summary_reflects_stored_timestamps(): void {
        $exported_at = time() - 7200; // 2 hours ago.
        $imported_at = time() - 3600; // 1 hour ago.
        set_config('last_exported', $exported_at, 'local_homepage_config');
        set_config('last_imported', $imported_at, 'local_homepage_config');

        $summary = manager::get_summary();
        $this->assertSame($exported_at, $summary['last_exported']);
        $this->assertSame($imported_at, $summary['last_imported']);
    }

    /**
     * settings_count must reflect the number of config_plugins entries for the theme component.
     */
    public function test_get_summary_settings_count_is_accurate(): void {
        // Remove any existing entries for the test component to get a clean baseline.
        $before = manager::get_summary()['settings_count'];

        set_config('phpunit_count_test_1', 'a', 'theme_boost_union');
        set_config('phpunit_count_test_2', 'b', 'theme_boost_union');

        $after = manager::get_summary()['settings_count'];
        $this->assertSame($before + 2, $after);
    }
}
