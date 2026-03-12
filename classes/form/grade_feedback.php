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
 * Grade feedback form for ocrsubmission.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ocrsubmission\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Grade feedback form.
 *
 * @package    mod_ocrsubmission
 * @copyright  2024, LandingAI OCR Submission
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_feedback extends \moodleform {

    /**
     * Form definition.
     */
    public function definition(): void {
        $mform = $this->_form;
        $maxgrade = $this->_customdata['maxgrade'] ?? 100;

        // Grade field.
        $mform->addElement('text', 'grade', get_string('grade', 'mod_ocrsubmission'));
        $mform->setType('grade', PARAM_RAW_TRIMMED);
        $mform->addHelpButton('grade', 'grade', 'grades');

        // Feedback editor (TinyMCE).
        $mform->addElement(
            'editor',
            'feedback_editor',
            get_string('feedback', 'mod_ocrsubmission'),
            null,
            [
                'maxfiles'  => 0,
                'maxbytes'  => 0,
                'trusttext' => false,
                'context'   => $this->_customdata['context'] ?? null,
            ]
        );
        $mform->setType('feedback_editor', PARAM_RAW);
        $mform->addHelpButton('feedback_editor', 'feedback', 'mod_ocrsubmission');

        // Hidden fields.
        $mform->addElement('hidden', 'cmid', $this->_customdata['cmid'] ?? 0);
        $mform->setType('cmid', PARAM_INT);

        $mform->addElement('hidden', 'userid', $this->_customdata['userid'] ?? 0);
        $mform->setType('userid', PARAM_INT);

        $mform->addElement('hidden', 'maxgrade', $maxgrade);
        $mform->setType('maxgrade', PARAM_INT);

        $this->add_action_buttons(true, get_string('savefeedback', 'mod_ocrsubmission'));
    }

    /**
     * Validate form data.
     *
     * @param array $data Submitted data.
     * @param array $files Uploaded files.
     * @return array Validation errors.
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);

        $maxgrade = (int) ($data['maxgrade'] ?? 100);
        $grade = $data['grade'];

        if ($grade !== '' && $grade !== null) {
            if (!is_numeric($grade)) {
                $errors['grade'] = get_string('gradeerror', 'mod_ocrsubmission', $maxgrade);
            } else {
                $gradenum = (float) $grade;
                if ($gradenum < 0 || $gradenum > $maxgrade) {
                    $errors['grade'] = get_string('gradeerror', 'mod_ocrsubmission', $maxgrade);
                }
            }
        }

        return $errors;
    }
}
