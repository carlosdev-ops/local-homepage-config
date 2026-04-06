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
 * Cache definitions for local_homepage_config.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$definitions = [
    // Stores computed course/user counts for the homepage tiles.
    // Application-level cache shared across all users; TTL of 5 minutes avoids
    // stale counts while keeping DB queries rare on high-traffic front pages.
    'tilecounts' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'simplekeys'         => true,
        'simpledata'         => true,
        'staticacceleration' => true,
        'ttl'                => 300,
    ],

    // Stores the fully rendered banner HTML (after format_text / HTMLPurifier).
    // Shared across all users — the banner is identical for everyone.
    // TTL of 5 minutes is the fallback; the cache is purged immediately when any
    // banner setting (bannercfg, bannerinterval, bannerheight, bannermaxwidth) is
    // saved via the admin settings page.
    'banner' => [
        'mode'               => cache_store::MODE_APPLICATION,
        'simplekeys'         => true,
        'simpledata'         => false, // HTML strings can exceed the simple-data limit.
        'staticacceleration' => true,
        'ttl'                => 300,
    ],
];
