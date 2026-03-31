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
 * into the <div id="hpc-tiles"> placeholder placed by the admin in any
 * section summary.
 *
 * The tiles HTML is rendered server-side (Mustache template) and placed in
 * #hpc-tiles-content with display:none.  This module transfers the child nodes
 * into #hpc-tiles using DOM node moves — no innerHTML, no re-parsing, no HTML
 * string in JavaScript.
 *
 * @module     local_homepage_config/tiles_init
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        /**
         * Move tiles from the hidden prerender container into #hpc-tiles.
         */
        init: function() {
            var target = document.getElementById('hpc-tiles');
            var source = document.getElementById('hpc-tiles-content');
            if (!target || !source) {
                return;
            }
            // Transfer child nodes directly — avoids HTML re-parsing and innerHTML.
            while (source.firstChild) {
                target.appendChild(source.firstChild);
            }
            source.parentNode.removeChild(source);
        }
    };
});
