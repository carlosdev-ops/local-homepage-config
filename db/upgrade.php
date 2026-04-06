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
 * Upgrade steps for local_homepage_config.
 *
 * Each savepoint corresponds to a version number declared in version.php.
 * Add a new block for every schema or data migration; never remove old blocks.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion  The version the site is currently at.
 * @return bool
 */
function xmldb_local_homepage_config_upgrade(int $oldversion): bool {
    global $DB;

    // -------------------------------------------------------------------------
    // 3.2.0 — 2026033100
    //
    // Introduced upgrade.php (no schema changes).
    // Migrates any legacy raw plugin setting 'theme_component' stored under
    // the old key 'themecomponent_legacy' to the canonical 'themecomponent'
    // key, in case the setting was written by a very early pre-release build.
    // -------------------------------------------------------------------------
    if ($oldversion < 2026033100) {

        $legacy = get_config('local_homepage_config', 'themecomponent_legacy');
        if ($legacy !== false && get_config('local_homepage_config', 'themecomponent') === false) {
            set_config('themecomponent', $legacy, 'local_homepage_config');
            unset_config('themecomponent_legacy', 'local_homepage_config');
        }

        upgrade_plugin_savepoint(true, 2026033100, 'local', 'homepage_config');
    }

    // -------------------------------------------------------------------------
    // 3.2.1 — 2026033101
    //
    // Replaced the three separate banner message settings (bannermsg1/2/3)
    // with a single JSON array setting (bannercfg), allowing an unlimited
    // number of slides.  Migrate any existing messages into the new format
    // and remove the old keys.
    // -------------------------------------------------------------------------
    if ($oldversion < 2026033101) {

        $existing = get_config('local_homepage_config', 'bannercfg');
        if ($existing === false || trim($existing) === '' || trim($existing) === '[]') {
            // Build bannercfg from legacy individual message keys.
            $slides = [];
            foreach (['bannermsg1', 'bannermsg2', 'bannermsg3'] as $key) {
                $msg = get_config('local_homepage_config', $key);
                if (is_string($msg) && trim($msg) !== '') {
                    $slides[] = ['html' => $msg];
                }
            }
            if (!empty($slides)) {
                set_config('bannercfg', json_encode($slides, JSON_UNESCAPED_UNICODE), 'local_homepage_config');
            }
        }

        // Always remove the legacy keys regardless of whether migration ran.
        unset_config('bannermsg1', 'local_homepage_config');
        unset_config('bannermsg2', 'local_homepage_config');
        unset_config('bannermsg3', 'local_homepage_config');

        upgrade_plugin_savepoint(true, 2026033101, 'local', 'homepage_config');
    }

    // -------------------------------------------------------------------------
    // 3.2.2 — 2026033102
    //
    // Added local_homepage_config_imports table to log every import operation
    // with the user ID, timestamp, theme component, and per-type counts.
    // install.xml covers fresh installs; this step handles existing ones.
    // -------------------------------------------------------------------------
    if ($oldversion < 2026033102) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_homepage_config_import');

        $table->add_field('id',             XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated',    XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('themecomponent', XMLDB_TYPE_CHAR,   '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('settings',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('coresettings',   XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('files',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('menus',          XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('flavours',       XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('blocks',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('errors',         XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('restoreblocks',  XMLDB_TYPE_INTEGER,  '1', null, XMLDB_NOTNULL, null, '0');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('fk_userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026033102, 'local', 'homepage_config');
    }

    // -------------------------------------------------------------------------
    // 3.2.4 — 2026040502
    //
    // Add a non-unique index on userid in local_homepage_config_import so that
    // GDPR privacy API queries (get_contexts_for_userid, export_user_data,
    // delete_data_for_user) run efficiently without a full table scan.
    // -------------------------------------------------------------------------
    if ($oldversion < 2026040502) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_homepage_config_import');
        $index = new xmldb_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);

        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        upgrade_plugin_savepoint(true, 2026040502, 'local', 'homepage_config');
    }

    // -------------------------------------------------------------------------
    // 3.2.5 — 2026040503
    //
    // Add snapshotfileid column to local_homepage_config_import.
    // Stores the stored_file ID of the automatic pre-import snapshot ZIP so
    // that admins can roll back a configuration import within 24 hours.
    // 0 means no snapshot was taken (imports prior to this version).
    // -------------------------------------------------------------------------
    if ($oldversion < 2026040503) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('local_homepage_config_import');
        $field = new xmldb_field('snapshotfileid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0',
            'restoreblocks');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026040503, 'local', 'homepage_config');
    }

    return true;
}
