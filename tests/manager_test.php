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

        foreach (['settings', 'coresettings', 'files', 'menus', 'flavours', 'blocks', 'errors', 'snapshotfileid'] as $key) {
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

        $stats = manager::import_from_zip($path, ['blocks' => true]);

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

    // =========================================================================
    // peek_zip — dry-run preview
    // =========================================================================

    /**
     * peek_zip() on a well-formed ZIP returns valid=true and the correct counts.
     */
    public function test_peek_zip_returns_valid_info_for_well_formed_zip(): void {
        $path = $this->make_zip([
            'manifest.json'        => $this->make_manifest(),
            'settings.json'        => json_encode(['key1' => 'val1', 'key2' => 'val2']),
            'plugin_settings.json' => json_encode(['tilescfg' => '[]']),
            'core_settings.json'   => '{}',
            'blocks.json'          => json_encode([
                ['blockname' => 'html', 'pagetypepattern' => 'site-index'],
                ['blockname' => 'html', 'pagetypepattern' => 'site-index'],
            ]),
        ]);

        $info = manager::peek_zip($path);

        $this->assertTrue($info['valid']);
        $this->assertNull($info['error']);
        $this->assertSame('theme_boost_union', $info['theme_component']);
        // 2 from settings.json + 1 from plugin_settings.json + 0 from core_settings.json
        $this->assertSame(3, $info['settings_count']);
        $this->assertSame(2, $info['blocks_count']);
        $this->assertSame(0, $info['files_count']);
    }

    /**
     * peek_zip() on a ZIP without manifest.json returns valid=false with an error message.
     */
    public function test_peek_zip_rejects_missing_manifest(): void {
        $path = $this->make_zip([
            'settings.json' => '{}',
            'blocks.json'   => '[]',
        ]);

        $info = manager::peek_zip($path);

        $this->assertFalse($info['valid']);
        $this->assertIsString($info['error']);
        $this->assertNotEmpty($info['error']);
    }

    /**
     * peek_zip() on a ZIP declaring a newer, incompatible format version returns valid=false.
     */
    public function test_peek_zip_rejects_incompatible_format_version(): void {
        $path = $this->make_zip([
            'manifest.json' => $this->make_manifest('99.0'),
            'settings.json' => '{}',
        ]);

        $info = manager::peek_zip($path);

        $this->assertFalse($info['valid']);
        $this->assertIsString($info['error']);
        $this->assertNotEmpty($info['error']);
    }

    // =========================================================================
    // Plugin settings (bannercfg) — round-trip fidelity
    // =========================================================================

    /**
     * bannercfg (JSON array of slides) must survive a full export → wipe → import round-trip.
     *
     * This exercises the EXPORT_PLUGIN_KEYS path in manager — the same code path
     * used for tilescfg — so it catches any regression when new plugin keys are added.
     */
    public function test_import_restores_bannercfg_roundtrip(): void {
        global $DB;

        $slides = json_encode([
            ['html' => '<p>Slide one</p>'],
            ['html' => '<p>Slide two</p>'],
        ]);
        set_config('bannercfg', $slides, 'local_homepage_config');

        $path = manager::export_to_zip();

        // Verify the value was included in plugin_settings.json.
        $zip = new \ZipArchive();
        $zip->open($path);
        $plugin_settings = json_decode($zip->getFromName('plugin_settings.json'), true);
        $zip->close();
        $this->assertArrayHasKey('bannercfg', $plugin_settings, 'bannercfg must be present in plugin_settings.json');
        $this->assertSame($slides, $plugin_settings['bannercfg']);

        // Wipe the seeded value.
        unset_config('bannercfg', 'local_homepage_config');
        $this->assertFalse(get_config('local_homepage_config', 'bannercfg'));

        // Import and verify restoration.
        $stats = manager::import_from_zip($path);
        $this->assertSame($slides, get_config('local_homepage_config', 'bannercfg'));
        $this->assertEmpty($stats['errors']);
    }

    // =========================================================================
    // Selective import (IMPORT_DEFAULTS / $options array)
    // =========================================================================

    /**
     * IMPORT_DEFAULTS must define all expected section keys with correct types.
     */
    public function test_import_defaults_structure(): void {
        $defaults = manager::IMPORT_DEFAULTS;
        $expected = ['settings', 'plugin_settings', 'core_settings', 'files',
                     'menus', 'flavours', 'blocks', 'reset_dashboards'];
        foreach ($expected as $key) {
            $this->assertArrayHasKey($key, $defaults, "IMPORT_DEFAULTS missing key: $key");
            $this->assertIsBool($defaults[$key], "IMPORT_DEFAULTS[$key] must be bool");
        }
        // Blocks and reset_dashboards must default to false (opt-in only).
        $this->assertFalse($defaults['blocks']);
        $this->assertFalse($defaults['reset_dashboards']);
    }

    /**
     * Passing ['settings' => false] must leave theme settings untouched.
     */
    public function test_import_skip_settings_leaves_existing_values(): void {
        global $DB;

        set_config('phpunit_selective', 'original', 'theme_boost_union');
        $path = manager::export_to_zip();

        // Change the value so we can verify it's NOT overwritten.
        set_config('phpunit_selective', 'changed_locally', 'theme_boost_union');

        manager::import_from_zip($path, ['settings' => false]);

        // The value must remain as locally changed (import skipped settings).
        $this->assertSame('changed_locally', get_config('theme_boost_union', 'phpunit_selective'));
    }

    /**
     * Passing ['files' => false] must not modify existing files.
     */
    public function test_import_skip_files_does_not_restore_files(): void {
        $path  = manager::export_to_zip();
        $stats = manager::import_from_zip($path, ['files' => false]);
        $this->assertSame(0, $stats['files'], 'No files should be restored when files option is false');
    }

    // =========================================================================
    // Snapshot / rollback
    // =========================================================================

    /**
     * take_snapshot() must return a non-zero file ID for the stored snapshot.
     */
    public function test_take_snapshot_returns_nonzero_fileid(): void {
        $fileid = manager::take_snapshot();
        $this->assertGreaterThan(0, $fileid, 'take_snapshot() must return a stored_file ID > 0');

        // The file must exist in Moodle file storage under the snapshots filearea.
        $fs   = get_file_storage();
        $file = $fs->get_file_by_id($fileid);
        $this->assertNotFalse($file, 'Snapshot file not found in file storage');
        $this->assertSame('local_homepage_config', $file->get_component());
        $this->assertSame('snapshots', $file->get_filearea());
    }

    /**
     * rollback_to_snapshot() must restore settings from a previously taken snapshot.
     */
    public function test_rollback_to_snapshot_restores_settings(): void {
        // Seed a value and take a snapshot.
        set_config('phpunit_rollback', 'before_import', 'theme_boost_union');
        $fileid = manager::take_snapshot();
        $this->assertGreaterThan(0, $fileid);

        // Now overwrite the value (simulating a bad import).
        set_config('phpunit_rollback', 'after_bad_import', 'theme_boost_union');
        $this->assertSame('after_bad_import', get_config('theme_boost_union', 'phpunit_rollback'));

        // Roll back.
        manager::rollback_to_snapshot($fileid);

        // Value must be restored to the pre-import state.
        $this->assertSame('before_import', get_config('theme_boost_union', 'phpunit_rollback'));
    }

    /**
     * rollback_to_snapshot() with an invalid file ID must throw a moodle_exception.
     */
    public function test_rollback_to_snapshot_throws_on_invalid_fileid(): void {
        $this->expectException(\moodle_exception::class);
        manager::rollback_to_snapshot(999999999);
    }

    /**
     * import_from_zip() with take_snapshot=true must store a snapshot file ID in stats.
     */
    public function test_import_records_snapshot_fileid_in_stats(): void {
        $path  = manager::export_to_zip();
        $stats = manager::import_from_zip($path, [], true);
        $this->assertArrayHasKey('snapshotfileid', $stats);
        $this->assertGreaterThan(0, $stats['snapshotfileid'],
            'snapshotfileid must be > 0 when take_snapshot is true');
    }

    /**
     * import_from_zip() with take_snapshot=false must set snapshotfileid to 0.
     */
    public function test_import_no_snapshot_sets_fileid_zero(): void {
        $path  = manager::export_to_zip();
        $stats = manager::import_from_zip($path, [], false);
        $this->assertSame(0, $stats['snapshotfileid'],
            'snapshotfileid must be 0 when take_snapshot is false');
    }

    // =========================================================================
    // diff_zip — settings comparison
    // =========================================================================

    /**
     * diff_zip() on a well-formed ZIP must return all peek_zip() fields plus diff fields.
     */
    public function test_diff_zip_returns_required_fields(): void {
        $path   = manager::export_to_zip();
        $result = manager::diff_zip($path);

        $this->assertTrue($result['valid']);
        foreach (['diff', 'diff_changed', 'diff_added', 'diff_unchanged'] as $key) {
            $this->assertArrayHasKey($key, $result, "diff_zip() missing key: $key");
        }
        $this->assertIsArray($result['diff']);
    }

    /**
     * Each entry in the diff array must have the four required fields.
     */
    public function test_diff_zip_entries_have_required_structure(): void {
        set_config('phpunit_diff_key', 'some_value', 'theme_boost_union');
        $path   = manager::export_to_zip();
        $result = manager::diff_zip($path);

        foreach ($result['diff'] as $entry) {
            foreach (['name', 'source', 'current', 'incoming', 'status'] as $field) {
                $this->assertArrayHasKey($field, $entry, "Diff entry missing field: $field");
            }
            $this->assertContains($entry['status'], ['changed', 'added', 'unchanged'],
                "Diff entry status must be changed|added|unchanged");
            $this->assertContains($entry['source'], ['theme', 'plugin', 'core'],
                "Diff entry source must be theme|plugin|core");
        }
    }

    /**
     * A newly added setting must appear as 'added' in the diff.
     */
    public function test_diff_zip_detects_added_setting(): void {
        // Export without the setting, then add it and diff — the ZIP has it, DB doesn't.
        $path = manager::export_to_zip();

        // Remove the key from DB so the ZIP value will appear as 'added'.
        set_config('phpunit_diff_new', 'hello', 'theme_boost_union');
        $path2  = manager::export_to_zip();
        unset_config('phpunit_diff_new', 'theme_boost_union');

        $result = manager::diff_zip($path2);
        $added  = array_filter($result['diff'], fn($e) => $e['name'] === 'phpunit_diff_new');
        $this->assertNotEmpty($added, 'Added setting not found in diff');
        $this->assertSame('added', array_values($added)[0]['status']);
        $this->assertGreaterThan(0, $result['diff_added']);
    }

    // =========================================================================
    // Cohort reference detection (peek_zip)
    // =========================================================================

    /**
     * peek_zip() must detect cohort references in flavours.json and return counts.
     */
    public function test_peek_zip_detects_cohort_refs_in_flavours(): void {
        $flavours = json_encode([
            ['id' => 1, 'title' => 'Staff',    'cohorts' => '3,7'],
            ['id' => 2, 'title' => 'Students', 'cohorts' => ''],    // empty — not a ref
            ['id' => 3, 'title' => 'Guests',   'cohortid' => 5],
        ]);

        $path = $this->make_zip([
            'manifest.json' => $this->make_manifest(),
            'settings.json' => '{}',
            'flavours.json' => $flavours,
        ]);

        $info = manager::peek_zip($path);
        $this->assertTrue($info['valid']);
        // 2 flavours have non-empty cohort fields (index 0 and 2).
        $this->assertSame(2, $info['cohort_warn_flavours']);
        $this->assertSame(0, $info['cohort_warn_menus']);
    }

    /**
     * peek_zip() must detect cohort references in smartmenu_items.json.
     */
    public function test_peek_zip_detects_cohort_refs_in_menu_items(): void {
        $items = json_encode([
            ['id' => 1, 'title' => 'Teachers menu', 'cohort' => 4],
            ['id' => 2, 'title' => 'Public item',   'cohort' => 0], // 0 = empty — not counted
        ]);

        $path = $this->make_zip([
            'manifest.json'      => $this->make_manifest(),
            'settings.json'      => '{}',
            'smartmenus.json'    => '[{"id":1,"title":"Test"}]',
            'smartmenu_items.json' => $items,
        ]);

        $info = manager::peek_zip($path);
        $this->assertTrue($info['valid']);
        $this->assertSame(1, $info['cohort_warn_menus']);
    }

    /**
     * peek_zip() must return zero cohort warnings when no cohort fields are present.
     */
    public function test_peek_zip_no_cohort_refs_returns_zero(): void {
        $path = $this->make_zip([
            'manifest.json'  => $this->make_manifest(),
            'settings.json'  => '{}',
            'flavours.json'  => json_encode([['id' => 1, 'title' => 'Plain', 'logo' => '']]),
            'blocks.json'    => '[]',
        ]);

        $info = manager::peek_zip($path);
        $this->assertSame(0, $info['cohort_warn_menus']);
        $this->assertSame(0, $info['cohort_warn_flavours']);
    }

    // =========================================================================
    // Block HTML content — round-trip fidelity
    // =========================================================================

    /**
     * An HTML block's intro text in block_html must survive a full export → import round-trip.
     */
    public function test_import_restores_block_html_content(): void {
        global $DB;

        // Insert a minimal block_instances row for a front-page HTML block.
        $context   = \context_system::instance();
        $cfg       = new \stdClass();
        $cfg->text = 'Unit test block content';
        $bi                    = new \stdClass();
        $bi->blockname         = 'html';
        $bi->parentcontextid   = $context->id;
        $bi->showinsubcontexts = 0;
        $bi->requiredbytheme   = 0;
        $bi->pagetypepattern   = 'site-index';
        $bi->subpagepattern    = null;
        $bi->defaultregion     = 'side-pre';
        $bi->defaultweight     = 0;
        $bi->timecreated       = time();
        $bi->timemodified      = time();
        $bi->configdata        = base64_encode(serialize($cfg));
        $biid = $DB->insert_record('block_instances', $bi);

        // Seed the block_html table — this is what a real HTML block writes.
        $bh                  = new \stdClass();
        $bh->blockinstanceid = $biid;
        $bh->intro           = '<p>Fixture intro text for PHPUnit</p>';
        $bh->introformat     = FORMAT_HTML;
        $DB->insert_record('block_html', $bh);

        // Export.
        $path = manager::export_to_zip();

        // Wipe both the block instance and its HTML row.
        $DB->delete_records('block_html',      ['blockinstanceid' => $biid]);
        $DB->delete_records('block_instances', ['id'              => $biid]);

        // Import with block restore enabled.
        $stats = manager::import_from_zip($path, ['blocks' => true]);
        $this->assertGreaterThan(0, $stats['blocks']);

        // The block instance must have been re-created.
        $restored_bi = $DB->get_record('block_instances', [
            'blockname'       => 'html',
            'pagetypepattern' => 'site-index',
        ]);
        $this->assertNotFalse($restored_bi, 'block_instances row not re-created after import');

        // The block_html row must carry the original intro text.
        $restored_bh = $DB->get_record('block_html', ['blockinstanceid' => $restored_bi->id]);
        $this->assertNotFalse($restored_bh, 'block_html row not re-created after import');
        $this->assertSame('<p>Fixture intro text for PHPUnit</p>', $restored_bh->intro);
    }
}
