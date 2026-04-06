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
 * Admin settings and menu registration for local_homepage_config.
 *
 * Adds a category under "Local plugins" with two children:
 *   - Export / Import  (admin_externalpage → index.php)
 *   - Settings         (admin_settingpage  — theme component, tables, etc.)
 *
 * Also adds a shortcut under Appearance for quick access to Export/Import.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    // Pull defaults from the single source of truth in manager::DEFAULTS.
    // The autoloader is active by the time settings.php runs, so this is safe.
    $d = \local_homepage_config\manager::get_defaults();
    $d['tilescfg'] = $d['tilescfg'] ?? '[]';

    // ── Category under "Local plugins" ───────────────────────────────────────
    $ADMIN->add('localplugins', new admin_category(
        'local_homepage_config_cat',
        get_string('pluginname', 'local_homepage_config')
    ));

    // ── 1. Export / Import page ───────────────────────────────────────────────
    $ADMIN->add('local_homepage_config_cat', new admin_externalpage(
        'local_homepage_config',
        get_string('exportimport', 'local_homepage_config'),
        new moodle_url('/local/homepage_config/index.php'),
        'local/homepage_config:manage'
    ));

    // Shortcut also visible under Appearance.
    $ADMIN->add('appearance', new admin_externalpage(
        'local_homepage_config_appearance',
        get_string('exportimport', 'local_homepage_config'),
        new moodle_url('/local/homepage_config/index.php'),
        'local/homepage_config:manage'
    ));

    // ── 2. Plugin settings page ───────────────────────────────────────────────
    $settingspage = new admin_settingpage(
        'local_homepage_config_settings',
        get_string('pluginsettings', 'local_homepage_config'),
        'local/homepage_config:manage'
    );

    if ($ADMIN->fulltree) {

        // ── Section: Theme component ──────────────────────────────────────────
        $settingspage->add(new admin_setting_heading(
            'local_homepage_config/heading_theme',
            get_string('heading_theme', 'local_homepage_config'),
            get_string('heading_theme_desc', 'local_homepage_config')
        ));

        $settingspage->add(new admin_setting_configtext(
            'local_homepage_config/themecomponent',
            get_string('themecomponent', 'local_homepage_config'),
            get_string('themecomponent_desc', 'local_homepage_config'),
            $d['themecomponent'],
            PARAM_ALPHANUMEXT
        ));

        // ── Section: Dedicated DB tables ──────────────────────────────────────
        $settingspage->add(new admin_setting_heading(
            'local_homepage_config/heading_tables',
            get_string('heading_tables', 'local_homepage_config'),
            get_string('heading_tables_desc', 'local_homepage_config')
        ));

        $settingspage->add(new admin_setting_configtext(
            'local_homepage_config/tablemenus',
            get_string('tablemenus', 'local_homepage_config'),
            get_string('tablemenus_desc', 'local_homepage_config'),
            $d['tablemenus'],
            PARAM_ALPHANUMEXT
        ));

        $settingspage->add(new admin_setting_configtext(
            'local_homepage_config/tablemenuitems',
            get_string('tablemenuitems', 'local_homepage_config'),
            get_string('tablemenuitems_desc', 'local_homepage_config'),
            $d['tablemenuitems'],
            PARAM_ALPHANUMEXT
        ));

        $settingspage->add(new admin_setting_configtext(
            'local_homepage_config/tableflavours',
            get_string('tableflavours', 'local_homepage_config'),
            get_string('tableflavours_desc', 'local_homepage_config'),
            $d['tableflavours'],
            PARAM_ALPHANUMEXT
        ));

        // ── Section: Advanced ─────────────────────────────────────────────────
        $settingspage->add(new admin_setting_heading(
            'local_homepage_config/heading_advanced',
            get_string('heading_advanced', 'local_homepage_config'),
            get_string('heading_advanced_desc', 'local_homepage_config')
        ));

        $settingspage->add(new admin_setting_configtextarea(
            'local_homepage_config/flavourfileareas',
            get_string('flavourfileareas', 'local_homepage_config'),
            get_string('flavourfileareas_desc', 'local_homepage_config'),
            $d['flavourfileareas'],
            PARAM_TEXT,
            60, 4
        ));

        $settingspage->add(new admin_setting_configtextarea(
            'local_homepage_config/blockpagetypes',
            get_string('blockpagetypes', 'local_homepage_config'),
            get_string('blockpagetypes_desc', 'local_homepage_config'),
            $d['blockpagetypes'],
            PARAM_TEXT,
            60, 4
        ));

        $settingspage->add(new admin_setting_configtextarea(
            'local_homepage_config/coreconfigkeys',
            get_string('coreconfigkeys', 'local_homepage_config'),
            get_string('coreconfigkeys_desc', 'local_homepage_config'),
            $d['coreconfigkeys'],
            PARAM_TEXT,
            60, 4
        ));

        // ── Section: Dynamic tiles ────────────────────────────────────────────
        $settingspage->add(new admin_setting_heading(
            'local_homepage_config/heading_tiles',
            get_string('heading_tiles', 'local_homepage_config'),
            get_string('heading_tiles_desc', 'local_homepage_config')
        ));

        $tilescfg_setting = new admin_setting_configtextarea(
            'local_homepage_config/tilescfg',
            get_string('tilescfg', 'local_homepage_config'),
            get_string('tilescfg_desc', 'local_homepage_config'),
            $d['tilescfg'],
            PARAM_RAW,  // JSON must not be transformed — PARAM_CLEANHTML would corrupt quotes and angle brackets.
            80, 20
        );
        // Purge the tile-counts cache immediately when the JSON config changes so
        // that updated category IDs or types are reflected on the next page load
        // without waiting for the 5-minute TTL.
        $tilescfg_setting->set_updatedcallback('local_homepage_config_invalidate_tile_cache');
        $settingspage->add($tilescfg_setting);

        // ── Section: Advertising banner ───────────────────────────────────────
        $settingspage->add(new admin_setting_heading(
            'local_homepage_config/heading_banner',
            get_string('heading_banner', 'local_homepage_config'),
            get_string('heading_banner_desc', 'local_homepage_config')
        ));

        $bannercfg_setting = new admin_setting_configtextarea(
            'local_homepage_config/bannercfg',
            get_string('bannercfg', 'local_homepage_config'),
            get_string('bannercfg_desc', 'local_homepage_config'),
            '',
            PARAM_RAW,  // JSON must not be transformed — PARAM_CLEANHTML would corrupt quotes and angle brackets.
            80, 15
        );
        $bannercfg_setting->set_updatedcallback('local_homepage_config_invalidate_banner_cache');
        $settingspage->add($bannercfg_setting);

        $bannerinterval_setting = new admin_setting_configtext(
            'local_homepage_config/bannerinterval',
            get_string('bannerinterval', 'local_homepage_config'),
            get_string('bannerinterval_desc', 'local_homepage_config'),
            '5',
            PARAM_INT
        );
        $bannerinterval_setting->set_updatedcallback('local_homepage_config_invalidate_banner_cache');
        $settingspage->add($bannerinterval_setting);

        $bannerheight_setting = new \local_homepage_config\admin\setting_css_length(
            'local_homepage_config/bannerheight',
            get_string('bannerheight', 'local_homepage_config'),
            get_string('bannerheight_desc', 'local_homepage_config'),
            '',
            PARAM_TEXT
        );
        $bannerheight_setting->set_updatedcallback('local_homepage_config_invalidate_banner_cache');
        $settingspage->add($bannerheight_setting);

        $bannermaxwidth_setting = new \local_homepage_config\admin\setting_css_length(
            'local_homepage_config/bannermaxwidth',
            get_string('bannermaxwidth', 'local_homepage_config'),
            get_string('bannermaxwidth_desc', 'local_homepage_config'),
            '',
            PARAM_TEXT
        );
        $bannermaxwidth_setting->set_updatedcallback('local_homepage_config_invalidate_banner_cache');
        $settingspage->add($bannermaxwidth_setting);
    }

    $ADMIN->add('local_homepage_config_cat', $settingspage);
}
