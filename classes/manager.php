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
        'blockpagetypes'   => "site-index\ncourse-index",

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

        // 3. Plugin own settings (e.g. tilescfg) — keys that belong to local_homepage_config itself.
        $plugin_settings = [];
        foreach (['tilescfg'] as $key) {
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
     * Import a visual configuration from a ZIP produced by export_to_zip().
     *
     * @param string $zippath        Path to the uploaded ZIP file.
     * @param bool   $restore_blocks Replace existing blocks for tracked page types.
     * @return array Stats: settings, core_settings, files, menus, flavours, blocks, errors[].
     * @throws \moodle_exception
     */
    public static function import_from_zip(string $zippath, bool $restore_blocks = false): array {
        global $DB;

        self::require_zip();

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

        $stats = ['settings' => 0, 'core_settings' => 0, 'files' => 0,
                  'menus' => 0, 'flavours' => 0, 'blocks' => 0, 'errors' => []];

        $context = \context_system::instance();
        $fs      = get_file_storage();

        // 1. Theme settings.
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

        // 2. Plugin own settings (tilescfg, etc.).
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

        // 3. Core settings (whitelist from current plugin config).
        $raw = $zip->getFromName('core_settings.json');
        if ($raw !== false) {
            $whitelist = self::cfg_lines('coreconfigkeys');
            foreach ((json_decode($raw, true) ?? []) as $name => $value) {
                if (!in_array($name, $whitelist, true)) {
                    continue;
                }
                try {
                    set_config($name, $value);
                    $stats['core_settings']++;
                } catch (\Exception $e) {
                    self::log_error($stats, "Core setting '$name'", $e);
                }
            }
        }

        // 4. Files — skip flavour-specific areas (handled in step 5 after ID remapping).
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
            $existing = $fs->get_file($context->id, $themecomponent, $rec['filearea'], $rec['itemid'], $rec['filepath'], $rec['filename']);
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

        // 5. Smart Menus.
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

        // 6. Flavours (with old→new ID remapping for per-flavour files).
        $table_flavours = self::cfg('tableflavours');
        if (self::table_exists($table_flavours)) {
            $flavours_raw = $zip->getFromName('flavours.json');
            if ($flavours_raw !== false) {
                $result = self::restore_flavours(
                    json_decode($flavours_raw, true) ?? [],
                    $zip, $fs, $context, $themecomponent
                );
                $stats['flavours'] = $result['flavours'];
                $stats['files']   += $result['files'];
                $stats['errors']   = array_merge($stats['errors'], $result['errors']);
            }
        }

        // 7. Blocks.
        if ($restore_blocks) {
            $blocks_raw = $zip->getFromName('blocks.json');
            if ($blocks_raw !== false) {
                $blocks_data = json_decode($blocks_raw, true) ?? [];
                if (!empty($blocks_data)) {
                    $stats['blocks'] = self::restore_blocks($blocks_data, $context);
                }
            }
        }

        $zip->close();

        // Always ensure the imported theme component is set as the active theme.
        // The core 'theme' config expects the short name (e.g. 'boost_union'),
        // not the component name (e.g. 'theme_boost_union').
        $theme_shortname = preg_replace('/^theme_/', '', $themecomponent);
        if (get_config('core', 'theme') !== $theme_shortname) {
            set_config('theme', $theme_shortname);
        }

        theme_reset_all_caches();
        return $stats;
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

    private static function restore_flavours(array $flavours, \ZipArchive $zip, $fs, $context, string $themecomponent): array {
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
                $existing = $fs->get_file($context->id, $themecomponent, $rec['filearea'], $new_id, $rec['filepath'], $rec['filename']);
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

    private static function restore_blocks(array $blocks_data, \context_system $context): int {
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

        return $count;
    }

    // =========================================================================
    // SUMMARY
    // =========================================================================

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
