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
 * Core manager class for local_homepage_config plugin.
 *
 * All previously hard-coded values (theme component, table names, file areas,
 * page types, core config keys) are now read from the plugin's admin settings
 * via cfg() / cfg_lines(), falling back to DEFAULTS when not yet configured.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config;

defined('MOODLE_INTERNAL') || die();

class manager {

    /** ZIP format version — bump when the schema changes incompatibly. */
    const EXPORT_FORMAT_VERSION = '2.0';

    /**
     * Plugin settings to include in the export ZIP (plugin_settings.json).
     *
     * Add any new local_homepage_config setting key here so it is
     * automatically exported and imported — no other file needs editing.
     */
    const EXPORT_PLUGIN_KEYS = [
        'tilescfg',
        'bannercfg',
        'bannerinterval',
        'bannerheight',
        'bannermaxwidth',
    ];

    /**
     * Default values for every configurable setting.
     * Used as fallback when the admin has not yet saved the settings page.
     */
    const DEFAULTS = [
        // Moodle component that owns the visual configuration.
        'themecomponent'   => 'theme_boost_union',

        // Dedicated DB tables created by the theme (Smart Menus + Flavours).
        'tablemenus'       => 'theme_boost_union_menus',
        'tablemenuitems'   => 'theme_boost_union_menuitems',
        'tableflavours'    => 'theme_boost_union_flavours',

        // File areas whose itemid equals a flavour row id (one per line).
        'flavourfileareas' => "flavours_look_logo\nflavours_look_logocompact\nflavours_look_favicon\nflavours_look_backgroundimage",

        // pagetypepattern prefixes for block export (one per line, LIKE match).
        'blockpagetypes'   => "site-index\ncourse-index\nmy-index",

        // Keys in the core `config` table to include in the export (one per line).
        'coreconfigkeys'   => "frontpage\nfrontpageloggedin\ndefaulthomepage\ntheme",
    ];

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Return the default values for every configurable plugin setting.
     *
     * Consumed by settings.php to avoid duplicating default literals.
     * Also used internally by cfg() / cfg_lines() as the fallback source.
     *
     * @return array<string,string>
     */
    public static function get_defaults(): array {
        return self::DEFAULTS;
    }

    /**
     * Export the complete visual configuration to a temporary ZIP file.
     *
     * @return string Absolute path to the temporary ZIP file.
     * @throws \moodle_exception
     */
    public static function export_to_zip(): string {
        global $DB, $CFG;

        self::require_zip();

        // Invalidate only the config cache so get_config() returns fresh values.
        // purge_all_caches() was intentionally avoided: it flushes sessions, theme,
        // language and course caches — a severe performance hit with no benefit here,
        // since all other reads (files, menus, flavours, blocks) go directly to the DB.
        \cache_helper::purge_by_definition('core', 'config');

        $tempdir = make_temp_directory('local_homepage_config');
        $zippath = $tempdir . '/homepage_config_' . date('Ymd_His') . '.zip';
        if (file_exists($zippath)) {
            unlink($zippath);
        }

        $zip = new \ZipArchive();
        if ($zip->open($zippath, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('cannotopenzip', 'local_homepage_config');
        }

        $context        = \context_system::instance();
        $fs             = get_file_storage();
        $themecomponent = self::cfg('themecomponent');

        // 1. Manifest (includes current config so the target knows what was used).
        $zip->addFromString('manifest.json', self::json([
            'format_version'  => self::EXPORT_FORMAT_VERSION,
            'exported_at'     => date('c'),
            'moodle_version'  => $CFG->version,
            'moodle_release'  => $CFG->release,
            'theme_component' => $themecomponent,
            'plugin_config'   => self::current_config_snapshot(),
        ]));

        // 2. Theme settings (config_plugins).
        $settings = $DB->get_records_menu('config_plugins', ['plugin' => $themecomponent], 'name ASC', 'name, value');
        $zip->addFromString('settings.json', self::json((object)$settings));

        // 3. Plugin own settings — keys that belong to local_homepage_config itself.
        $plugin_settings = [];
        foreach (self::EXPORT_PLUGIN_KEYS as $key) {
            $val = get_config('local_homepage_config', $key);
            if ($val !== false) {
                $plugin_settings[$key] = $val;
            }
        }
        $zip->addFromString('plugin_settings.json', self::json((object)$plugin_settings));

        // 4. Core Moodle config keys.
        $core = [];
        foreach (self::cfg_lines('coreconfigkeys') as $key) {
            $val = get_config('core', $key);
            if ($val !== false) {
                $core[$key] = $val;
            }
        }
        $zip->addFromString('core_settings.json', self::json((object)$core));

        // 5. All files in the theme component (global + per-flavour).
        $files = $fs->get_area_files($context->id, $themecomponent, false, 'filearea, filepath, filename', false);
        foreach ($files as $file) {
            if ($file->get_filename() !== '.') {
                $zip->addFromString(self::file_to_zipkey($file), $file->get_content());
            }
        }

        // 6. Smart Menus.
        if (self::table_exists(self::cfg('tablemenus'))) {
            $zip->addFromString('smartmenus.json',
                self::json(array_values($DB->get_records(self::cfg('tablemenus'), [], 'sortorder ASC'))));
            $zip->addFromString('smartmenu_items.json',
                self::json(array_values($DB->get_records(self::cfg('tablemenuitems'), [], 'menu ASC, sortorder ASC'))));
        }

        // 7. Flavours.
        if (self::table_exists(self::cfg('tableflavours'))) {
            $zip->addFromString('flavours.json',
                self::json(array_values($DB->get_records(self::cfg('tableflavours'), [], 'sort ASC'))));
            // Flavour files are already captured in step 5.
        }

        // 8. Blocks.
        $zip->addFromString('blocks.json', self::json(self::collect_blocks($context)));

        $zip->close();
        return $zippath;
    }

    /**
     * Default options for import_from_zip().
     * All sections are imported by default except blocks (opt-in: destructive).
     */
    const IMPORT_DEFAULTS = [
        'settings'         => true,  // Theme settings  (settings.json)
        'plugin_settings'  => true,  // Plugin settings (plugin_settings.json)
        'core_settings'    => true,  // Core config     (core_settings.json)
        'files'            => true,  // Uploaded files  (files/)
        'menus'            => true,  // Smart Menus
        'flavours'         => true,  // Flavours
        'blocks'           => false, // Block instances (opt-in — replaces existing blocks)
        'reset_dashboards' => false, // Reset all user dashboard customisations
    ];

    /**
     * Import a visual configuration from a ZIP produced by export_to_zip().
     *
     * @param string $zippath       Path to the uploaded ZIP file.
     * @param array  $options       Which sections to import — keys from IMPORT_DEFAULTS.
     *                              Unspecified keys fall back to IMPORT_DEFAULTS values.
     * @param bool   $take_snapshot If true (default), take a pre-import snapshot for rollback.
     * @return array Stats: settings, coresettings, files, menus, flavours, blocks, errors[], snapshotfileid.
     * @throws \moodle_exception
     */
    public static function import_from_zip(string $zippath, array $options = [], bool $take_snapshot = true): array {
        global $DB;

        $opt = array_merge(self::IMPORT_DEFAULTS, $options);

        self::require_zip();

        // Take a pre-import snapshot so the admin can roll back within 24 hours.
        $snapshotfileid = $take_snapshot ? self::take_snapshot() : 0;

        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            throw new \moodle_exception('cannotopenzip', 'local_homepage_config');
        }

        // Read the manifest if present. A missing manifest is tolerated for
        // compatibility with ZIPs produced by older versions of this plugin
        // (or by a manual export) — we just fall back to the current settings.
        $manifest_raw = $zip->getFromName('manifest.json');
        if ($manifest_raw !== false) {
            $manifest = json_decode($manifest_raw, true) ?? [];
        } else {
            $manifest = [];
            debugging('local_homepage_config: manifest.json absent from ZIP — importing as legacy format.', DEBUG_DEVELOPER);
        }

        // Use the theme component declared in the export manifest (not necessarily the
        // currently configured one — ensures the ZIP is self-contained).
        $themecomponent = $manifest['theme_component'] ?? self::cfg('themecomponent');

        // Reject ZIPs produced by a newer, incompatible version of the plugin.
        $format_version = $manifest['format_version'] ?? '1.0';
        if (version_compare($format_version, self::EXPORT_FORMAT_VERSION, '>')) {
            $zip->close();
            throw new \moodle_exception('incompatibleformatversion', 'local_homepage_config', '', (object)[
                'got' => $format_version,
                'max' => self::EXPORT_FORMAT_VERSION,
            ]);
        }

        $stats = ['settings' => 0, 'coresettings' => 0, 'files' => 0,
                  'menus' => 0, 'flavours' => 0, 'blocks' => 0, 'errors' => [],
                  'snapshotfileid' => $snapshotfileid];

        $context = \context_system::instance();
        $fs      = get_file_storage();

        // 1. Theme settings.
        if ($opt['settings']) {
            $raw = $zip->getFromName('settings.json');
            if ($raw !== false) {
                foreach ((json_decode($raw, true) ?? []) as $name => $value) {
                    try {
                        set_config($name, $value, $themecomponent);
                        $stats['settings']++;
                    } catch (\Exception $e) {
                        self::log_error($stats, "Setting '$name'", $e);
                    }
                }
            }
        }

        // 2. Plugin own settings (tilescfg, bannercfg, banner dimensions, etc.).
        if ($opt['plugin_settings']) {
            $raw = $zip->getFromName('plugin_settings.json');
            if ($raw !== false) {
                foreach ((json_decode($raw, true) ?? []) as $name => $value) {
                    try {
                        set_config($name, $value, 'local_homepage_config');
                        $stats['settings']++;
                    } catch (\Exception $e) {
                        self::log_error($stats, "Plugin setting '$name'", $e);
                    }
                }
            }
        }

        // 3. Core settings (whitelist from current plugin config).
        if ($opt['core_settings']) {
            $raw = $zip->getFromName('core_settings.json');
            if ($raw !== false) {
                $whitelist = self::cfg_lines('coreconfigkeys');
                foreach ((json_decode($raw, true) ?? []) as $name => $value) {
                    if (!in_array($name, $whitelist, true)) {
                        continue;
                    }
                    try {
                        set_config($name, $value);
                        $stats['coresettings']++;
                    } catch (\Exception $e) {
                        self::log_error($stats, "Core setting '$name'", $e);
                    }
                }
            }
        }

        // 4. Files — skip flavour-specific areas (handled in step 5 after ID remapping).
        if ($opt['files']) {
            $flavour_areas = self::cfg_lines('flavourfileareas');
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $zipname = $zip->getNameIndex($i);
                if (strpos($zipname, 'files/') !== 0 || substr($zipname, -1) === '/') {
                    continue;
                }
                $rec = self::zipkey_to_filerecord($zipname, $context->id, $themecomponent);
                if (!$rec || in_array($rec['filearea'], $flavour_areas, true)) {
                    continue;
                }
                $existing = $fs->get_file(
                    $context->id, $themecomponent,
                    $rec['filearea'], $rec['itemid'], $rec['filepath'], $rec['filename']
                );
                if ($existing) {
                    $existing->delete();
                }
                try {
                    $fs->create_file_from_string($rec, $zip->getFromIndex($i));
                    $stats['files']++;
                } catch (\Exception $e) {
                    self::log_error($stats, "File '{$rec['filename']}' ({$rec['filearea']})", $e);
                }
            }
        }

        // 5. Smart Menus.
        if ($opt['menus']) {
            $table_menus = self::cfg('tablemenus');
            if (self::table_exists($table_menus)) {
                $menus_raw = $zip->getFromName('smartmenus.json');
                $items_raw = $zip->getFromName('smartmenu_items.json');
                if ($menus_raw !== false) {
                    $result = self::restore_smart_menus(
                        json_decode($menus_raw, true) ?? [],
                        json_decode($items_raw ?: '[]', true) ?? []
                    );
                    $stats['menus']  = $result['menus'];
                    $stats['errors'] = array_merge($stats['errors'], $result['errors']);
                }
            }
        }

        // 6. Flavours (with old→new ID remapping for per-flavour files).
        if ($opt['flavours']) {
            $table_flavours = self::cfg('tableflavours');
            if (self::table_exists($table_flavours)) {
                $flavours_raw = $zip->getFromName('flavours.json');
                if ($flavours_raw !== false) {
                    $result = self::restore_flavours(
                        json_decode($flavours_raw, true) ?? [],
                        $zip, $fs, $context, $themecomponent
                    );
                    $stats['flavours'] = $result['flavours'];
                    // Flavour files are counted in 'files' even when $opt['files'] is false,
                    // because they are inseparable from their flavour rows.
                    $stats['files']   += $result['files'];
                    $stats['errors']   = array_merge($stats['errors'], $result['errors']);
                }
            }
        }

        // 7. Blocks.
        if ($opt['blocks']) {
            $blocks_raw = $zip->getFromName('blocks.json');
            if ($blocks_raw !== false) {
                $blocks_data = json_decode($blocks_raw, true) ?? [];
                if (!empty($blocks_data)) {
                    $stats['blocks'] = self::restore_blocks($blocks_data, $context, $opt['reset_dashboards']);
                }
            }
        }

        $zip->close();

        // Ensure the imported theme is active — only when settings were actually written.
        if ($opt['settings'] || $opt['core_settings']) {
            $theme_shortname = preg_replace('/^theme_/', '', $themecomponent);
            if (get_config('core', 'theme') !== $theme_shortname) {
                set_config('theme', $theme_shortname);
            }
        }

        theme_reset_all_caches();
        return $stats;
    }

    // =========================================================================
    // SNAPSHOT / ROLLBACK
    // =========================================================================

    /**
     * Export the current configuration to a snapshot file stored in moodledata.
     *
     * Called automatically before every import so that the admin can roll back
     * to the previous state via rollback_to_snapshot().  Snapshots older than
     * 24 hours are pruned on each call to keep storage lean.
     *
     * @return int The stored_file ID of the new snapshot, or 0 on failure.
     */
    public static function take_snapshot(): int {
        try {
            $zippath = self::export_to_zip();
            $context = \context_system::instance();
            $fs      = get_file_storage();

            $filename = 'snapshot_' . date('Ymd_His') . '.zip';
            $rec = [
                'contextid'    => $context->id,
                'component'    => 'local_homepage_config',
                'filearea'     => 'snapshots',
                'itemid'       => 0,
                'filepath'     => '/',
                'filename'     => $filename,
                'timecreated'  => time(),
                'timemodified' => time(),
            ];

            $file = $fs->create_file_from_pathname($rec, $zippath);
            @unlink($zippath);

            // Prune old snapshots while we're here.
            self::prune_snapshots();

            return (int)$file->get_id();
        } catch (\Exception $e) {
            debugging('local_homepage_config: snapshot failed — ' . $e->getMessage(), DEBUG_DEVELOPER);
            return 0;
        }
    }

    /**
     * Restore a previously taken snapshot.
     *
     * A rollback is always a full restore (all sections including blocks).
     * Dashboard reset is intentionally skipped — rolling back should not
     * wipe user customisations that pre-date the original import.
     *
     * @param int $fileid  The stored_file ID returned by take_snapshot().
     * @return array Same stats array as import_from_zip().
     * @throws \moodle_exception If the file does not exist or cannot be opened.
     */
    public static function rollback_to_snapshot(int $fileid): array {
        $context = \context_system::instance();
        $fs      = get_file_storage();

        $file = $fs->get_file_by_id($fileid);
        if (!$file
            || $file->get_component() !== 'local_homepage_config'
            || $file->get_filearea()   !== 'snapshots'
            || $file->get_contextid()  !== $context->id) {
            throw new \moodle_exception('snapshotnotfound', 'local_homepage_config');
        }

        // Copy the snapshot to a temp path so import_from_zip() can open it.
        $tempdir = make_temp_directory('local_homepage_config');
        $zippath = $tempdir . '/rollback_' . $fileid . '.zip';
        $file->copy_content_to($zippath);

        // Full restore — all sections, no dashboard reset, no new snapshot.
        $stats = self::import_from_zip($zippath, ['blocks' => true, 'reset_dashboards' => false], false);
        @unlink($zippath);
        return $stats;
    }

    /**
     * Delete snapshots older than the given retention period.
     *
     * @param int $older_than_seconds  Age threshold in seconds (default 24 h).
     */
    public static function prune_snapshots(int $older_than_seconds = 86400): void {
        $context = \context_system::instance();
        $fs      = get_file_storage();
        $cutoff  = time() - $older_than_seconds;

        $files = $fs->get_area_files($context->id, 'local_homepage_config', 'snapshots', false, 'id', false);
        foreach ($files as $file) {
            if ($file->get_timecreated() < $cutoff) {
                $file->delete();
            }
        }
    }

    // =========================================================================
    // PREVIEW (dry-run)
    // =========================================================================

    /**
     * Inspect a ZIP file and return what it contains — without touching the DB.
     *
     * Used by the two-step import flow to show the admin a summary before
     * committing any changes.  Safe to call multiple times on the same file.
     *
     * @param  string $zippath  Absolute path to the ZIP file.
     * @return array {
     *   bool        $valid           False if the ZIP is unreadable or incompatible.
     *   string|null $error           Human-readable error string when valid=false.
     *   string      $format_version  ZIP schema version declared in manifest.
     *   string      $theme_component Frankenstyle component name from manifest.
     *   int|null    $exported_at     Unix timestamp of export, or null if absent.
     *   string      $moodle_version  Moodle version string from manifest.
     *   int         $settings_count  Total keys across settings + plugin + core JSON files.
     *   int         $files_count     Number of file entries inside the files/ directory.
     *   int         $menus_count     Number of Smart Menu rows in smartmenus.json.
     *   int         $flavours_count  Number of Flavour rows in flavours.json.
     *   int         $blocks_count    Number of block instances in blocks.json.
     * }
     */
    public static function peek_zip(string $zippath): array {
        $info = [
            'valid'                => false,
            'error'                => null,
            'format_version'       => '?',
            'theme_component'      => '?',
            'exported_at'          => null,
            'moodle_version'       => '?',
            'settings_count'       => 0,
            'files_count'          => 0,
            'menus_count'          => 0,
            'flavours_count'       => 0,
            'blocks_count'         => 0,
            // Cohort-reference warnings (number of records that reference a cohort ID).
            'cohort_warn_menus'    => 0,
            'cohort_warn_flavours' => 0,
        ];

        if (!class_exists('ZipArchive')) {
            $info['error'] = get_string('ziparchive_missing', 'local_homepage_config');
            return $info;
        }

        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            $info['error'] = get_string('cannotopenzip', 'local_homepage_config');
            return $info;
        }

        // Manifest — required.
        $manifest_raw = $zip->getFromName('manifest.json');
        if ($manifest_raw === false) {
            $zip->close();
            $info['error'] = get_string('invalidexportfile', 'local_homepage_config');
            return $info;
        }
        $manifest = json_decode($manifest_raw, true) ?? [];

        // Version compatibility check.
        $format_version = $manifest['format_version'] ?? '1.0';
        if (version_compare($format_version, self::EXPORT_FORMAT_VERSION, '>')) {
            $zip->close();
            $info['error'] = get_string('incompatibleformatversion', 'local_homepage_config', (object)[
                'got' => $format_version,
                'max' => self::EXPORT_FORMAT_VERSION,
            ]);
            return $info;
        }

        $info['format_version']  = $format_version;
        $info['theme_component'] = $manifest['theme_component'] ?? '?';
        $info['exported_at']     = isset($manifest['exported_at']) ? (int)$manifest['exported_at'] : null;
        $info['moodle_version']  = $manifest['moodle_version']    ?? '?';

        // Count settings across all three JSON config files.
        foreach (['settings.json', 'plugin_settings.json', 'core_settings.json'] as $f) {
            $raw = $zip->getFromName($f);
            if ($raw !== false) {
                $info['settings_count'] += count(json_decode($raw, true) ?? []);
            }
        }

        // Count files (entries under files/ that are not directory markers).
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (strpos($name, 'files/') === 0 && substr($name, -1) !== '/') {
                $info['files_count']++;
            }
        }

        // Count Smart Menus and detect cohort references in menu items.
        $menus_raw = $zip->getFromName('smartmenus.json');
        if ($menus_raw !== false) {
            $info['menus_count'] = count(json_decode($menus_raw, true) ?? []);
        }
        $items_raw = $zip->getFromName('smartmenu_items.json');
        if ($items_raw !== false) {
            $info['cohort_warn_menus'] = self::count_cohort_refs(json_decode($items_raw, true) ?? []);
        }

        // Count Flavours and detect cohort references.
        $flavours_raw = $zip->getFromName('flavours.json');
        if ($flavours_raw !== false) {
            $decoded = json_decode($flavours_raw, true) ?? [];
            $info['flavours_count']       = count($decoded);
            $info['cohort_warn_flavours'] = self::count_cohort_refs($decoded);
        }

        // Count Blocks.
        $blocks_raw = $zip->getFromName('blocks.json');
        if ($blocks_raw !== false) {
            $info['blocks_count'] = count(json_decode($blocks_raw, true) ?? []);
        }

        $zip->close();
        $info['valid'] = true;
        return $info;
    }

    /**
     * Compare a ZIP against the current configuration and return a line-by-line diff.
     *
     * Extends peek_zip() with per-setting comparison so the admin can see exactly
     * what will change before confirming the import.  Read-only — does not touch the DB.
     *
     * Each entry in the returned 'diff' array has:
     *   string $name     Setting key.
     *   string $source   'theme' | 'plugin' | 'core'.
     *   string $current  Current value (empty string when not set).
     *   string $incoming Incoming value from the ZIP.
     *   string $status   'changed' | 'added' | 'unchanged'.
     *
     * @param  string $zippath  Absolute path to the ZIP file.
     * @return array  All peek_zip() fields plus: diff[], diff_changed, diff_added, diff_unchanged.
     */
    public static function diff_zip(string $zippath): array {
        global $DB;

        $info = self::peek_zip($zippath);
        if (!$info['valid']) {
            return $info + ['diff' => [], 'diff_changed' => 0, 'diff_added' => 0, 'diff_unchanged' => 0];
        }

        $zip = new \ZipArchive();
        if ($zip->open($zippath) !== true) {
            return $info + ['diff' => [], 'diff_changed' => 0, 'diff_added' => 0, 'diff_unchanged' => 0];
        }

        $themecomponent = $info['theme_component'];
        $whitelist_core = self::cfg_lines('coreconfigkeys');

        // Pre-load all current theme settings into a name→value map (one DB query).
        $current_theme  = $DB->get_records_menu('config_plugins', ['plugin' => $themecomponent], '', 'name, value');

        $diff            = [];
        $diff_changed    = 0;
        $diff_added      = 0;
        $diff_unchanged  = 0;

        // Helper: truncate very long values for display without losing meaning.
        $truncate = static function (string $v): string {
            return mb_strlen($v) > 120 ? mb_substr($v, 0, 117) . '…' : $v;
        };

        // ── Theme settings (settings.json) ────────────────────────────────────
        $raw = $zip->getFromName('settings.json');
        foreach ((json_decode($raw ?: '{}', true) ?? []) as $name => $incoming) {
            $current = array_key_exists($name, $current_theme) ? (string)$current_theme[$name] : null;
            $incoming = (string)$incoming;
            if ($current === null) {
                $status = 'added';
                $diff_added++;
            } elseif ($current !== $incoming) {
                $status = 'changed';
                $diff_changed++;
            } else {
                $status = 'unchanged';
                $diff_unchanged++;
            }
            $diff[] = [
                'name'     => $name,
                'source'   => 'theme',
                'current'  => $current !== null ? $truncate($current) : '',
                'incoming' => $truncate($incoming),
                'status'   => $status,
            ];
        }

        // ── Plugin settings (plugin_settings.json) ────────────────────────────
        $raw = $zip->getFromName('plugin_settings.json');
        foreach ((json_decode($raw ?: '{}', true) ?? []) as $name => $incoming) {
            $cur_val = get_config('local_homepage_config', $name);
            $current = ($cur_val !== false) ? (string)$cur_val : null;
            $incoming = (string)$incoming;
            if ($current === null) {
                $status = 'added';
                $diff_added++;
            } elseif ($current !== $incoming) {
                $status = 'changed';
                $diff_changed++;
            } else {
                $status = 'unchanged';
                $diff_unchanged++;
            }
            $diff[] = [
                'name'     => $name,
                'source'   => 'plugin',
                'current'  => $current !== null ? $truncate($current) : '',
                'incoming' => $truncate($incoming),
                'status'   => $status,
            ];
        }

        // ── Core settings (core_settings.json) ───────────────────────────────
        $raw = $zip->getFromName('core_settings.json');
        foreach ((json_decode($raw ?: '{}', true) ?? []) as $name => $incoming) {
            if (!in_array($name, $whitelist_core, true)) {
                continue; // Skip keys that would be ignored on import anyway.
            }
            $cur_val = get_config('core', $name);
            $current = ($cur_val !== false) ? (string)$cur_val : null;
            $incoming = (string)$incoming;
            if ($current === null) {
                $status = 'added';
                $diff_added++;
            } elseif ($current !== $incoming) {
                $status = 'changed';
                $diff_changed++;
            } else {
                $status = 'unchanged';
                $diff_unchanged++;
            }
            $diff[] = [
                'name'     => $name,
                'source'   => 'core',
                'current'  => $current !== null ? $truncate($current) : '',
                'incoming' => $truncate($incoming),
                'status'   => $status,
            ];
        }

        $zip->close();

        // Sort: changed first, then added, then unchanged — within each group: alphabetical.
        usort($diff, static function (array $a, array $b): int {
            $order = ['changed' => 0, 'added' => 1, 'unchanged' => 2];
            $cmp   = ($order[$a['status']] ?? 3) <=> ($order[$b['status']] ?? 3);
            return $cmp !== 0 ? $cmp : strcmp($a['name'], $b['name']);
        });

        return $info + [
            'diff'           => $diff,
            'diff_changed'   => $diff_changed,
            'diff_added'     => $diff_added,
            'diff_unchanged' => $diff_unchanged,
        ];
    }

    // =========================================================================
    // SMART MENUS
    // =========================================================================

    private static function restore_smart_menus(array $menus, array $items): array {
        global $DB;

        $result      = ['menus' => 0, 'errors' => []];
        $table_menus = self::cfg('tablemenus');
        $table_items = self::cfg('tablemenuitems');

        // Delete existing.
        $existing_ids = $DB->get_fieldset_select($table_menus, 'id', '1=1');
        if (!empty($existing_ids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($existing_ids);
            $DB->delete_records_select($table_items, "menu $insql", $inparams);
            $DB->delete_records($table_menus);
        }

        // Re-insert menus, build old→new ID map.
        $id_map = [];
        foreach ($menus as $menu) {
            $old_id = (int)$menu['id'];
            unset($menu['id']);
            try {
                $id_map[$old_id] = $DB->insert_record($table_menus, (object)$menu);
                $result['menus']++;
            } catch (\Exception $e) {
                self::log_error($result, "Smart menu '{$menu['title']}'", $e);
            }
        }

        // Re-insert items with remapped menu IDs.
        foreach ($items as $item) {
            unset($item['id']);
            $item['menu'] = $id_map[(int)$item['menu']] ?? null;
            if ($item['menu'] === null) {
                continue;
            }
            try {
                $DB->insert_record($table_items, (object)$item);
            } catch (\Exception $e) {
                self::log_error($result, "Smart menu item '{$item['title']}'", $e);
            }
        }

        return $result;
    }

    // =========================================================================
    // FLAVOURS
    // =========================================================================

    private static function restore_flavours(
        array $flavours, \ZipArchive $zip, \file_storage $fs,
        \context_system $context, string $themecomponent
    ): array {
        global $DB;

        $result         = ['flavours' => 0, 'files' => 0, 'errors' => []];
        $table          = self::cfg('tableflavours');
        $flavour_areas  = self::cfg_lines('flavourfileareas');

        // Delete existing flavours and their files.
        foreach ($DB->get_records($table, [], '', 'id') as $old) {
            foreach ($flavour_areas as $fa) {
                foreach ($fs->get_area_files($context->id, $themecomponent, $fa, $old->id, 'id', false) as $ff) {
                    if ($ff->get_filename() !== '.') {
                        $ff->delete();
                    }
                }
            }
        }
        $DB->delete_records($table);

        // Pre-index flavour files from the ZIP once (O(m)) so the per-flavour
        // loop can look them up in O(1) instead of re-scanning all ZIP entries
        // for every flavour — reduces O(n×m) to O(n+m).
        // Structure: $flavour_files[old_itemid] = [[zip_index, file_record], ...]
        $flavour_files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipname = $zip->getNameIndex($i);
            if (strpos($zipname, 'files/') !== 0 || substr($zipname, -1) === '/') {
                continue;
            }
            $rec = self::zipkey_to_filerecord($zipname, $context->id, $themecomponent);
            if (!$rec || !in_array($rec['filearea'], $flavour_areas, true)) {
                continue;
            }
            $flavour_files[$rec['itemid']][] = [$i, $rec];
        }

        foreach ($flavours as $flavour) {
            $old_id = (int)$flavour['id'];
            unset($flavour['id']);
            try {
                $new_id = $DB->insert_record($table, (object)$flavour);
                $result['flavours']++;
            } catch (\Exception $e) {
                self::log_error($result, "Flavour '{$flavour['title']}'", $e);
                continue;
            }

            // Import files that belonged to this flavour (old itemid → new itemid).
            foreach ($flavour_files[$old_id] ?? [] as [$zip_index, $rec]) {
                $rec['itemid'] = $new_id;
                $existing = $fs->get_file(
                    $context->id, $themecomponent,
                    $rec['filearea'], $new_id, $rec['filepath'], $rec['filename']
                );
                if ($existing) {
                    $existing->delete();
                }
                try {
                    $fs->create_file_from_string($rec, $zip->getFromIndex($zip_index));
                    $result['files']++;
                } catch (\Exception $e) {
                    self::log_error($result, "Flavour file '{$rec['filename']}'", $e);
                }
            }
        }

        return $result;
    }

    // =========================================================================
    // BLOCKS
    // =========================================================================

    private static function collect_blocks(\context_system $context): array {
        global $DB;

        // Limitation: only the "html" block type receives full content export via
        // its dedicated block_html table.  Other block types that store content in
        // separate tables (e.g. block_myoverview, block_timeline) are exported with
        // their configdata only — their auxiliary table rows are NOT migrated.
        // This is sufficient for the common front-page use case (HTML blocks) but
        // should be extended if other block types need full fidelity.
        $data = [];
        foreach (self::cfg_lines('blockpagetypes') as $pagetype) {
            $blocks = $DB->get_records_sql(
                "SELECT * FROM {block_instances} WHERE parentcontextid = ? AND pagetypepattern LIKE ?",
                [$context->id, $pagetype . '%']
            );
            foreach ($blocks as $block) {
                $entry = (array)$block;
                if ($block->blockname === 'html') {
                    $html = $DB->get_record('block_html', ['blockinstanceid' => $block->id]);
                    if ($html) {
                        $entry['_html_intro']       = $html->intro;
                        $entry['_html_introformat'] = $html->introformat;
                    }
                }
                if (!empty($block->configdata)) {
                    $decoded = unserialize(base64_decode($block->configdata), ['allowed_classes' => false]);
                    if ($decoded !== false) {
                        $entry['_configdata_json'] = (array)$decoded;
                    }
                }
                $data[] = $entry;
            }
        }
        return $data;
    }

    private static function restore_blocks(array $blocks_data, \context_system $context, bool $reset_dashboards = false): int {
        global $DB;

        foreach (self::cfg_lines('blockpagetypes') as $pagetype) {
            $existing = $DB->get_records_sql(
                "SELECT * FROM {block_instances} WHERE parentcontextid = ? AND pagetypepattern LIKE ?",
                [$context->id, $pagetype . '%']
            );
            foreach ($existing as $b) {
                blocks_delete_instance($b);
            }
        }

        $count = 0;
        foreach ($blocks_data as $entry) {
            $new                    = new \stdClass();
            $new->blockname         = $entry['blockname'];
            $new->parentcontextid   = $context->id;
            $new->showinsubcontexts = (int)($entry['showinsubcontexts'] ?? 0);
            $new->requiredbytheme   = (int)($entry['requiredbytheme'] ?? 0);
            $new->pagetypepattern   = $entry['pagetypepattern'];
            $new->subpagepattern    = $entry['subpagepattern'] ?? null;
            $new->defaultregion     = $entry['defaultregion'];
            $new->defaultweight     = (int)($entry['defaultweight'] ?? 0);
            $new->timecreated       = time();
            $new->timemodified      = time();

            if (!empty($entry['_configdata_json'])) {
                // Primary path: data was round-tripped through JSON on export,
                // so all values are plain scalars/arrays — safe to re-serialize.
                $cfg = new \stdClass();
                foreach ($entry['_configdata_json'] as $k => $v) {
                    $cfg->$k = $v;
                }
                $new->configdata = base64_encode(serialize($cfg));
            } else {
                // Fallback path (legacy ZIP or block whose configdata could not be
                // decoded at export time).  The raw value is a base64-encoded PHP
                // serialized string originating from an external file, so we MUST
                // NOT store it as-is: when Moodle later loads the block it would
                // unserialize arbitrary PHP objects (gadget-chain risk).
                // Sanitize by deserializing with allowed_classes => false (converts
                // any object to a plain stdClass) then re-serializing.
                $raw = $entry['configdata'] ?? '';
                if ($raw !== '') {
                    $decoded = @unserialize(base64_decode($raw), ['allowed_classes' => false]);
                    $new->configdata = ($decoded !== false)
                        ? base64_encode(serialize((object)(array)$decoded))
                        : '';
                } else {
                    $new->configdata = '';
                }
            }

            $newid = $DB->insert_record('block_instances', $new);

            if ($new->blockname === 'html' && !empty($entry['_html_intro'])) {
                $html                  = new \stdClass();
                $html->blockinstanceid = $newid;
                $html->intro           = $entry['_html_intro'];
                $html->introformat     = (int)($entry['_html_introformat'] ?? FORMAT_HTML);
                $DB->insert_record('block_html', $html);
            }

            \context_block::instance($newid);
            $count++;
        }

        // Reset user-customised dashboards so they inherit the new my-index default.
        // Deletes records from my_pages where userid != 0 (user overrides), leaving
        // the site-wide default (userid = 0) intact.  This is a no-op when my_pages
        // does not exist (Moodle < 4.0) or when no my-index blocks were exported.
        if ($reset_dashboards && self::table_exists('my_pages')) {
            $DB->delete_records_select('my_pages', "userid != 0 AND name = '__default'");
        }

        return $count;
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

    /**
     * Return a summary of the current visual configuration counts and timestamps.
     *
     * Used by the main admin page to display the "Current configuration" panel.
     *
     * @return array {
     *   int       $settings_count  Number of config_plugins entries for the theme component.
     *   int       $files_count     Number of files stored under the theme component.
     *   int       $menus_count     Number of Smart Menu rows (0 if table absent).
     *   int       $flavours_count  Number of Flavour rows (0 if table absent).
     *   int       $blocks_count    Number of block instances on tracked page types.
     *   int|null  $last_exported   Unix timestamp of last export, or null if never.
     *   int|null  $last_imported   Unix timestamp of last import, or null if never.
     * }
     */
    public static function get_summary(): array {
        global $DB;

        $context        = \context_system::instance();
        $themecomponent = self::cfg('themecomponent');

        $settings_count = $DB->count_records('config_plugins', ['plugin' => $themecomponent]);

        $fs = get_file_storage();
        $files_count = 0;
        foreach ($fs->get_area_files($context->id, $themecomponent, false, 'id', false) as $f) {
            if ($f->get_filename() !== '.') {
                $files_count++;
            }
        }

        $menus_count    = self::table_exists(self::cfg('tablemenus'))    ? $DB->count_records(self::cfg('tablemenus'))    : 0;
        $flavours_count = self::table_exists(self::cfg('tableflavours')) ? $DB->count_records(self::cfg('tableflavours')) : 0;

        $blocks_count = 0;
        foreach (self::cfg_lines('blockpagetypes') as $pagetype) {
            $blocks_count += (int)$DB->count_records_sql(
                "SELECT COUNT(*) FROM {block_instances} WHERE parentcontextid = ? AND pagetypepattern LIKE ?",
                [$context->id, $pagetype . '%']
            );
        }

        $last_exported = get_config('local_homepage_config', 'last_exported');
        $last_imported = get_config('local_homepage_config', 'last_imported');

        return [
            'settings_count' => $settings_count,
            'files_count'    => $files_count,
            'menus_count'    => $menus_count,
            'flavours_count' => $flavours_count,
            'blocks_count'   => $blocks_count,
            'last_exported'  => $last_exported !== false ? (int)$last_exported : null,
            'last_imported'  => $last_imported !== false ? (int)$last_imported : null,
        ];
    }

    // =========================================================================
    // CONFIG HELPERS
    // =========================================================================

    /**
     * Read a plugin setting, falling back to DEFAULTS when not yet saved.
     */
    private static function cfg(string $key): string {
        $val = get_config('local_homepage_config', $key);
        return ($val !== false && $val !== '') ? (string)$val : self::DEFAULTS[$key];
    }

    /**
     * Read a multi-line plugin setting, returning a clean array of non-empty lines.
     */
    private static function cfg_lines(string $key): array {
        return array_values(array_filter(array_map('trim', explode("\n", self::cfg($key)))));
    }

    /**
     * Return a snapshot of all current plugin config values (stored in the manifest).
     */
    private static function current_config_snapshot(): array {
        $snapshot = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $snapshot[$key] = self::cfg($key);
        }
        return $snapshot;
    }

    // =========================================================================
    // FILE KEY HELPERS
    // =========================================================================

    private static function file_to_zipkey(\stored_file $file): string {
        $filepath = trim($file->get_filepath(), '/');
        return 'files/'
            . $file->get_filearea() . '/'
            . $file->get_itemid()   . '/'
            . ($filepath ? $filepath . '/' : '')
            . $file->get_filename();
    }

    private static function zipkey_to_filerecord(string $zipname, int $contextid, string $component): ?array {
        $relpath  = substr($zipname, strlen('files/'));
        $segments = explode('/', $relpath);
        if (count($segments) < 3) {
            return null;
        }
        $filearea = array_shift($segments);
        $itemid   = (int)array_shift($segments);
        $fname    = array_pop($segments);
        $filepath = empty($segments) ? '/' : '/' . implode('/', $segments) . '/';
        if (empty($filearea) || empty($fname)) {
            return null;
        }
        // Reject path traversal attempts in any segment.
        foreach (array_merge([$filearea, $fname], $segments) as $segment) {
            if ($segment === '..' || $segment === '.' || strpos($segment, "\0") !== false) {
                return null;
            }
        }
        return [
            'contextid'    => $contextid,
            'component'    => $component,
            'filearea'     => $filearea,
            'itemid'       => $itemid,
            'filepath'     => $filepath,
            'filename'     => $fname,
            'timecreated'  => time(),
            'timemodified' => time(),
        ];
    }

    // =========================================================================
    // MISC HELPERS
    // =========================================================================

    /**
     * Count how many records in a decoded JSON array reference a cohort ID.
     *
     * Scans every field name for the substring "cohort" (case-insensitive).
     * A record is counted at most once, even if it has several cohort fields.
     * This is intentionally schema-agnostic: it works regardless of how Boost Union
     * stores cohort conditions (direct integer field, JSON blob, CSV string, etc.).
     *
     * @param  array $records  Decoded array of DB row objects/arrays from the ZIP.
     * @return int  Number of records that have at least one non-empty cohort field.
     */
    private static function count_cohort_refs(array $records): int {
        $count = 0;
        foreach ($records as $record) {
            foreach ((array)$record as $key => $value) {
                if (stripos((string)$key, 'cohort') !== false && !empty($value)) {
                    $count++;
                    break; // Count this record once even if multiple cohort fields.
                }
            }
        }
        return $count;
    }

    private static function require_zip(): void {
        if (!class_exists('ZipArchive')) {
            throw new \moodle_exception('ziparchive_missing', 'local_homepage_config');
        }
    }

    private static function table_exists(string $table): bool {
        global $DB;
        return $DB->get_manager()->table_exists($table);
    }

    private static function json($value): string {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function log_error(array &$stats, string $label, \Exception $e): void {
        $msg = "$label: " . $e->getMessage();
        $stats['errors'][] = $msg;
        debugging("local_homepage_config — $msg", DEBUG_DEVELOPER);
    }
}
