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
 * The main landingocractivity configuration form.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Module instance settings form.
 *
 * @package    mod_landingocractivity
 * @copyright  2024, LandingAI OCR Submission
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_landingocractivity_mod_form extends moodleform_mod {

    /**
     * Defines forms elements.
     */
    public function definition(): void {
        global $CFG;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are shown.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('landingocractivityname', 'mod_landingocractivity'), ['size' => '64']);
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'landingocractivityname', 'mod_landingocractivity');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }

        // LandingAI API settings.
        $mform->addElement('header', 'landingaisettings', get_string('landingaisettings', 'mod_landingocractivity'));

        $mform->addElement(
            'passwordunmask',
            'apikey',
            get_string('apikey', 'mod_landingocractivity'),
            ['size' => '64']
        );
        $mform->setType('apikey', PARAM_RAW_TRIMMED);
        $mform->addRule('apikey', null, 'required', null, 'client');
        $mform->addHelpButton('apikey', 'apikey', 'mod_landingocractivity');

        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }

    /**
     * Enforce validation rules here.
     *
     * @param array $data Array of ("fieldname"=>value) of submitted data.
     * @param array $files Array of uploaded files "element_name"=>tmp_file_path.
     * @return array Array of "element_name"=>"error_description" if there are errors, empty array otherwise.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        if (empty($data['apikey'])) {
            $errors['apikey'] = get_string('apikeyerror', 'mod_landingocractivity');
        }

        return $errors;
    }
}
