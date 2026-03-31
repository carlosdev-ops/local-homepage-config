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
 * Moodle hooks for local_homepage_config.
 *
 * Injects dynamic tile HTML into the front page (site-index) by filling a
 * placeholder <div id="hpc-tiles"> that the admin places in any section
 * summary.  Rendering happens server-side — no AJAX, no auth issues.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject dynamic tiles HTML before the page footer.
 *
 * Moodle calls this function automatically on every page if it exists in
 * a local plugin's lib.php.  We bail out early on non–front-page requests.
 *
 * @return string  HTML injected just before </body>.
 */
function local_homepage_config_before_footer(): string {
    global $DB, $PAGE;

    // Only act on the site front page.
    if ($PAGE->pagetype !== 'site-index') {
        return '';
    }

    // Read tile configuration JSON from plugin settings.
    $json = get_config('local_homepage_config', 'tilescfg');
    if (!$json || trim($json) === '' || trim($json) === '[]') {
        return '';
    }

    $tiles_cfg = json_decode($json, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($tiles_cfg) || empty($tiles_cfg)) {
        return '';
    }

    // Build tile HTML.
    $tiles_html = local_homepage_config_render_tiles($tiles_cfg, $DB);
    if ($tiles_html === '') {
        return '';
    }

    // Render tiles into a hidden server-side container.  The AMD module
    // local_homepage_config/tiles_init transfers child nodes into the
    // <div id="hpc-tiles"> placeholder placed by the admin in any section
    // summary.  No HTML string is embedded in JavaScript, and no inline
    // <script> tag is produced by this plugin (avoids CSP issues).
    $PAGE->requires->js_call_amd('local_homepage_config/tiles_init', 'init');
    return html_writer::div($tiles_html, '', [
        'id'         => 'hpc-tiles-content',
        'style'      => 'display:none',
        'aria-hidden' => 'true',
    ]);
}

// ─────────────────────────────────────────────────────────────────────────────

/**
 * Render the full tiles grid HTML from a config array.
 *
 * Each entry in $tiles_cfg is an object with keys:
 *   title    string   Tile title (required)
 *   subtitle string   Optional sub-title
 *   type     string   "courses" | "users" | "custom" | "none"
 *   catid    int      Category ID (0 = all site)
 *   subcats  bool     Include sub-categories (default true)
 *   value    string   Custom value when type = "custom"
 *   icon     string   Font Awesome icon name without "fa-" (e.g. "sitemap")
 *   color    string   "blue"|"green"|"orange"|"purple"|"red"|"teal"
 *   link     string   URL — tile becomes a link (optional)
 *   newtab   bool     Open link in new tab
 *
 * @param array  $cfg   Decoded JSON config.
 * @param moodle_database $DB
 * @return string  Rendered HTML (empty if nothing to show).
 */
function local_homepage_config_render_tiles(array $cfg, $DB): string {
    global $OUTPUT;

    $count  = count($cfg);
    $colmap = [1 => 'col-12', 2 => 'col-12 col-sm-6', 3 => 'col-12 col-sm-6 col-md-4', 4 => 'col-12 col-sm-6 col-md-3'];
    $colcls = $colmap[min($count, 4)] ?? 'col-12 col-sm-6 col-md-3';

    $tiles = [];
    foreach ($cfg as $t) {
        // format_string() processes Moodle filters and escapes HTML — use {{{...}}} in the template.
        $title   = format_string($t['title']    ?? '');
        $sub     = format_string($t['subtitle'] ?? '');
        $type    = $t['type']    ?? 'none';
        $catid   = (int)($t['catid']   ?? 0);
        $subcats = isset($t['subcats']) ? (bool)$t['subcats'] : true;
        $custom  = $t['value']   ?? '';
        $icon    = preg_replace('/[^a-z0-9\-]/', '', strtolower($t['icon']  ?? ''));
        $color   = preg_replace('/[^a-z]/',      '', strtolower($t['color'] ?? 'blue'));
        $link    = clean_param($t['link'] ?? '', PARAM_URL);
        $newtab  = !empty($t['newtab']);

        if ($title === '') {
            continue;
        }

        // Compute live value. s() on custom ensures safe output via {{{value}}} in template.
        $display_value = null;
        if ($type === 'courses') {
            $display_value = local_homepage_config_count_courses($catid, $subcats, $DB);
        } else if ($type === 'users') {
            $display_value = local_homepage_config_count_users($catid, $subcats, $DB);
        } else if ($type === 'custom' && $custom !== '') {
            $display_value = s($custom);
        }

        $tiles[] = [
            'colcls'       => $colcls,
            'has_link'     => $link !== '',
            'link'         => $link,
            'newtab'       => $newtab,
            'color'        => $color,
            'has_icon'     => $icon !== '',
            'icon'         => $icon,
            'has_value'    => $display_value !== null,
            'value'        => $display_value,
            'title'        => $title,
            'has_subtitle' => $sub !== '',
            'subtitle'     => $sub,
        ];
    }

    if (empty($tiles)) {
        return '';
    }

    return $OUTPUT->render_from_template('local_homepage_config/tiles', ['tiles' => $tiles]);
}

// ── DB helpers ────────────────────────────────────────────────────────────────

function local_homepage_config_resolve_catids(int $catid, bool $subcats, $DB): array {
    if ($catid <= 0) {
        return [];
    }
    if (!$subcats) {
        return [$catid];
    }
    // Use core_course_category which leverages Moodle's built-in category cache,
    // avoiding one SQL query per tree level.
    $cat = core_course_category::get($catid, IGNORE_MISSING, true);
    if (!$cat) {
        return [$catid];
    }
    $ids = [$catid];
    foreach ($cat->get_all_children_ids() as $childid) {
        $ids[] = (int)$childid;
    }
    return $ids;
}

function local_homepage_config_count_courses(int $catid, bool $subcats, $DB): int {
    $cache    = \cache::make('local_homepage_config', 'tilecounts');
    $cachekey = 'courses_' . $catid . '_' . ($subcats ? '1' : '0');
    $cached   = $cache->get($cachekey);
    if ($cached !== false) {
        return (int)$cached;
    }

    if ($catid <= 0) {
        $result = max(0, (int)$DB->count_records('course', ['visible' => 1]) - 1);
    } else {
        $ids = local_homepage_config_resolve_catids($catid, $subcats, $DB);
        if (empty($ids)) {
            $result = 0;
        } else {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $params['vis']  = 1;
            $result = (int)$DB->count_records_select('course', "visible = :vis AND category $sql", $params);
        }
    }

    $cache->set($cachekey, $result);
    return $result;
}

function local_homepage_config_count_users(int $catid, bool $subcats, $DB): int {
    $cache    = \cache::make('local_homepage_config', 'tilecounts');
    $cachekey = 'users_' . $catid . '_' . ($subcats ? '1' : '0');
    $cached   = $cache->get($cachekey);
    if ($cached !== false) {
        return (int)$cached;
    }

    if ($catid <= 0) {
        $sql    = "SELECT COUNT(DISTINCT ue.userid)
                     FROM {user_enrolments} ue
                     JOIN {enrol} e ON e.id = ue.enrolid
                     JOIN {course} c ON c.id = e.courseid
                    WHERE ue.status = 0 AND e.status = 0 AND c.id <> :site";
        $result = (int)$DB->count_records_sql($sql, ['site' => SITEID]);
    } else {
        $ids = local_homepage_config_resolve_catids($catid, $subcats, $DB);
        if (empty($ids)) {
            $result = 0;
        } else {
            [$sql, $params] = $DB->get_in_or_equal($ids, SQL_PARAMS_NAMED);
            $q = "SELECT COUNT(DISTINCT ue.userid)
                    FROM {user_enrolments} ue
                    JOIN {enrol} e ON e.id = ue.enrolid
                    JOIN {course} c ON c.id = e.courseid
                   WHERE ue.status = 0 AND e.status = 0 AND c.visible = 1
                     AND c.category $sql";
            $result = (int)$DB->count_records_sql($q, $params);
        }
    }

    $cache->set($cachekey, $result);
    return $result;
}
