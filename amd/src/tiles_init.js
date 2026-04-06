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
 * Moves the pre-rendered tiles HTML from the hidden server-side container
 * into the top of the main content area.
 *
 * The tiles HTML is rendered server-side (Mustache template) and placed in
 * #hpc-tiles-content with display:none.  This module extracts the inner
 * .block-homepage-tiles node and prepends it to #region-main (Boost/Boost Union)
 * or #page-content (Classic), falling back to document.body.
 *
 * This approach avoids requiring an admin-placed <div id="hpc-tiles"> placeholder
 * whose id attribute is stripped by HTMLPurifier when saving section summaries.
 *
 * @module     local_homepage_config/tiles_init
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        /**
         * Move tiles from the hidden prerender container into the main content area.
         */
        init: function() {
            var source = document.getElementById('hpc-tiles-content');
            if (!source) {
                return;
            }

            // Find the best insertion point for the current theme.
            var insertInto = document.getElementById('region-main')
                          || document.getElementById('page-content')
                          || document.body;

            // Extract the tiles wrapper and prepend it.
            var tiles = source.querySelector('.block-homepage-tiles');
            if (!tiles) {
                return;
            }
            insertInto.insertBefore(tiles, insertInto.firstChild);
            source.parentNode.removeChild(source);
        }
    };
});
