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
 * Import form for local_homepage_config.
 *
 * Provides sesskey protection, file type validation and max-upload-size
 * enforcement through the standard moodleform API.
 *
 * @package    local_homepage_config
 * @copyright  2026 Carlos Costa
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_homepage_config\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class import_form extends \moodleform {

    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        // File picker — accepts only .zip.
        $mform->addElement('filepicker', 'configfile',
            get_string('import_file', 'local_homepage_config'),
            null,
            ['accepted_types' => ['.zip']]
        );
        $mform->addRule('configfile', null, 'required', null, 'client');

        // ── Sections to import ───────────────────────────────────────────────
        $mform->addElement('html',
            \html_writer::tag('h6',
                get_string('import_sections_heading', 'local_homepage_config'),
                ['class' => 'mt-3 mb-1']
            )
        );

        // All-on by default.
        $default_sections = [
            ['import_settings',        'import_settings_label'],
            ['import_plugin_settings', 'import_plugin_settings_label'],
            ['import_core_settings',   'import_core_settings_label'],
            ['import_files',           'import_files_label'],
            ['import_menus',           'import_menus_label'],
            ['import_flavours',        'import_flavours_label'],
        ];
        foreach ($default_sections as [$name, $strkey]) {
            $mform->addElement('checkbox', $name, get_string($strkey, 'local_homepage_config'));
            $mform->setDefault($name, 1);
        }

        // Blocks — opt-in (destructive).
        $mform->addElement('checkbox', 'restore_blocks',
            get_string('import_blocks', 'local_homepage_config')
        );
        $mform->addElement('html',
            \html_writer::tag('p',
                \html_writer::tag('i', '', ['class' => 'fa fa-exclamation-triangle mr-1 text-warning']) .
                get_string('import_blocks_warn', 'local_homepage_config'),
                ['class' => 'text-muted small mt-n2 mb-3']
            )
        );

        // Reset dashboards — only relevant when blocks are being restored.
        $mform->addElement('checkbox', 'reset_dashboards',
            get_string('import_reset_dashboards', 'local_homepage_config')
        );
        $mform->addElement('html',
            \html_writer::tag('p',
                get_string('import_reset_dashboards_desc', 'local_homepage_config'),
                ['class' => 'text-muted small mt-n2 mb-3']
            )
        );
        $mform->hideIf('reset_dashboards', 'restore_blocks', 'notchecked');

        $this->add_action_buttons(false, get_string('import_btn', 'local_homepage_config'));
    }
}
