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
 * Moves the pre-rendered banner HTML from the hidden server-side container
 * into the <div id="hpc-banner"> placeholder placed by the admin in any
 * section summary, then drives the auto-rotating carousel.
 *
 * The banner HTML is rendered server-side (Mustache template) and placed in
 * #hpc-banner-content with display:none.  This module transfers the child
 * nodes into #hpc-banner using DOM node moves, then starts the auto-play
 * timer and wires dot-navigation clicks and keyboard navigation.
 *
 * Keyboard support (when the carousel has focus):
 *   ArrowRight / ArrowDown — next slide
 *   ArrowLeft  / ArrowUp   — previous slide
 *   Home                   — first slide
 *   End                    — last slide
 *
 * @module     local_homepage_config/banner_init
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {
    'use strict';

    return {
        /**
         * Move the pre-rendered banner into the top of the main content area,
         * then start the carousel.
         *
         * The banner HTML is stored in #hpc-banner-inject (display:none) by
         * before_footer().  We extract the inner .hpc-banner node and prepend
         * it to #region-main (Boost/Boost Union) or #page-content (Classic),
         * falling back to document.body if neither selector matches.
         * This avoids requiring a user-placed <div id="hpc-banner"> placeholder
         * whose id attribute would be stripped by HTMLPurifier / rich-text editors.
         */
        init: function() {
            var inject = document.getElementById('hpc-banner-inject');
            if (!inject) {
                return;
            }

            // Find the best insertion point for the current theme.
            var insertInto = document.getElementById('region-main')
                          || document.getElementById('page-content')
                          || document.body;

            // Extract the .hpc-banner node from the hidden wrapper and prepend it.
            var banner = inject.querySelector('.hpc-banner');
            if (!banner) {
                return;
            }
            insertInto.insertBefore(banner, insertInto.firstChild);
            inject.parentNode.removeChild(inject);

            var slides = banner.querySelectorAll('.hpc-banner__slide');
            var dots   = banner.querySelectorAll('.hpc-banner__dot');

            // Single slide — no rotation needed.
            if (slides.length <= 1) {
                return;
            }

            var ms         = parseInt(banner.getAttribute('data-interval'), 10) || 5000;
            var slidecount = parseInt(banner.getAttribute('data-slidecount'), 10) || slides.length;
            var current    = 0;
            var statusEl   = banner.querySelector('.hpc-banner__status');

            /**
             * Activate slide n (wraps around), sync dot indicators, and update
             * the aria-live status region so screen readers announce the change.
             * @param {number} n Target slide index.
             */
            function goTo(n) {
                slides[current].classList.remove('is-active');
                if (dots[current]) {
                    dots[current].classList.remove('is-active');
                    dots[current].setAttribute('aria-selected', 'false');
                }
                current = ((n % slides.length) + slides.length) % slides.length;
                slides[current].classList.add('is-active');
                if (dots[current]) {
                    dots[current].classList.add('is-active');
                    dots[current].setAttribute('aria-selected', 'true');
                }
                // Update the live region — screen readers announce this text
                // politely (waits for user to finish before reading aloud).
                if (statusEl) {
                    statusEl.textContent = (current + 1) + ' / ' + slidecount;
                }
            }

            /**
             * Restart the auto-play timer, then navigate to slide n.
             * Used by both dot clicks and keyboard events so the user always
             * gets a full interval after a manual interaction.
             * @param {number} n Target slide index.
             */
            function navigate(n) {
                clearInterval(timer);
                goTo(n);
                timer = setInterval(function() {
                    goTo(current + 1);
                }, ms);
            }

            // Auto-play timer — null when paused.
            var timer = setInterval(function() {
                goTo(current + 1);
            }, ms);

            /**
             * Stop the auto-play timer without changing the current slide.
             * Called on mouseenter and focusin so users can read at their own pace.
             * WCAG 2.1 SC 2.2.2 — Pause, Stop, Hide.
             */
            function pause() {
                clearInterval(timer);
                timer = null;
            }

            /**
             * Restart the auto-play timer after a pause.
             * Called on mouseleave and focusout (when focus leaves the carousel entirely).
             */
            function resume() {
                if (timer === null) {
                    timer = setInterval(function() {
                        goTo(current + 1);
                    }, ms);
                }
            }

            // Pause while the pointer is over the banner.
            banner.addEventListener('mouseenter', pause);
            banner.addEventListener('mouseleave', resume);

            // Pause while keyboard focus is anywhere inside the banner.
            // focusin/focusout bubble, unlike focus/blur — no capture needed.
            banner.addEventListener('focusin',  pause);
            banner.addEventListener('focusout', function(e) {
                // Only resume when focus moves outside the banner entirely.
                if (!banner.contains(e.relatedTarget)) {
                    resume();
                }
            });

            // Dot clicks.
            Array.prototype.forEach.call(dots, function(dot, idx) {
                dot.setAttribute('aria-selected', idx === 0 ? 'true' : 'false');
                dot.addEventListener('click', function() {
                    navigate(idx);
                });
            });

            // Keyboard navigation — active when the carousel or any child has focus.
            // Keys follow the ARIA carousel pattern recommended by W3C:
            // https://www.w3.org/WAI/ARIA/apg/patterns/carousel/
            banner.addEventListener('keydown', function(e) {
                var key = e.key;
                if (key === 'ArrowRight' || key === 'ArrowDown') {
                    e.preventDefault();
                    navigate(current + 1);
                } else if (key === 'ArrowLeft' || key === 'ArrowUp') {
                    e.preventDefault();
                    navigate(current - 1);
                } else if (key === 'Home') {
                    e.preventDefault();
                    navigate(0);
                } else if (key === 'End') {
                    e.preventDefault();
                    navigate(slides.length - 1);
                }
            });
        }
    };
});
