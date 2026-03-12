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
 * Define all the backup steps that will be used by the backup_ocrsubmission_activity_task.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Define the complete ocrsubmission structure for backup, with file and id annotations.
 */
class backup_ocrsubmission_activity_structure_step extends backup_activity_structure_step {

    /**
     * Define the ocrsubmission backup structure.
     *
     * @return backup_nested_element
     */
    protected function define_structure(): backup_nested_element {
        $userinfo = $this->get_setting_value('userinfo');

        // Define each element separated.
        $ocrsubmission = new backup_nested_element('ocrsubmission', ['id'], [
            'name', 'intro', 'introformat', 'grade', 'timecreated', 'timemodified',
        ]);

        $submissions = new backup_nested_element('submissions');
        $submission  = new backup_nested_element('submission', ['id'], [
            'userid', 'status', 'ocr_text', 'error_message', 'timecreated', 'timemodified',
        ]);

        $grades = new backup_nested_element('grades');
        $grade  = new backup_nested_element('grade', ['id'], [
            'userid', 'grader', 'grade', 'feedback', 'feedbackformat', 'timegraded', 'timemodified',
        ]);

        // Build the tree.
        $ocrsubmission->add_child($submissions);
        $submissions->add_child($submission);

        $ocrsubmission->add_child($grades);
        $grades->add_child($grade);

        // Define sources.
        $ocrsubmission->set_source_table('ocrsubmission', ['id' => backup::VAR_ACTIVITYID]);

        if ($userinfo) {
            $submission->set_source_table('ocrsubmission_submissions', ['ocrsubmissionid' => backup::VAR_PARENTID]);
            $grade->set_source_table('ocrsubmission_grades', ['ocrsubmissionid' => backup::VAR_PARENTID]);
        }

        // Define id annotations.
        $submission->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'grader');

        // Define file annotations (but not the apikey which is sensitive).
        $submission->annotate_files('mod_ocrsubmission', 'submission', 'id');

        return $this->prepare_activity_structure($ocrsubmission);
    }
}
