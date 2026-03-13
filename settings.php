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
 * Plugin administration settings.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading(
        'mod_ocrsubmission/landingaisettingsheading',
        get_string('landingaisettings', 'mod_ocrsubmission'),
        get_string('landingaisettings_desc', 'mod_ocrsubmission')
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'mod_ocrsubmission/apikey',
        get_string('apikey', 'mod_ocrsubmission'),
        get_string('apikey_help', 'mod_ocrsubmission'),
        ''
    ));
}
