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
 * Define all the restore steps that will be used by the restore_ocrsubmission_activity_task.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Structure step to restore one ocrsubmission activity.
 */
class restore_ocrsubmission_activity_structure_step extends restore_activity_structure_step {

    /**
     * Define the structure of the restore workflow.
     *
     * @return restore_path_element[]
     */
    protected function define_structure(): array {
        $paths = [];
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('ocrsubmission', '/activity/ocrsubmission');

        if ($userinfo) {
            $paths[] = new restore_path_element(
                'ocrsubmission_submission',
                '/activity/ocrsubmission/submissions/submission'
            );
            $paths[] = new restore_path_element(
                'ocrsubmission_grade',
                '/activity/ocrsubmission/grades/grade'
            );
        }

        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the ocrsubmission element.
     *
     * @param array $data Restore data.
     */
    protected function process_ocrsubmission(array $data): void {
        global $DB;

        $data = (object) $data;
        $data->course = $this->get_courseid();
        $data->timecreated  = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // Note: apikey is intentionally not backed up/restored for security.
        $data->apikey = '';

        $newid = $DB->insert_record('ocrsubmission', $data);
        $this->apply_activity_instance($newid);
    }

    /**
     * Process each submission.
     *
     * @param array $data Restore data.
     */
    protected function process_ocrsubmission_submission(array $data): void {
        global $DB;

        $data = (object) $data;
        $oldid = $data->id;

        $data->ocrsubmissionid = $this->get_new_parentid('ocrsubmission');
        $data->userid          = $this->get_mappingid('user', $data->userid);
        $data->timecreated     = $this->apply_date_offset($data->timecreated);
        $data->timemodified    = $this->apply_date_offset($data->timemodified);

        $newid = $DB->insert_record('ocrsubmission_submissions', $data);
        $this->set_mapping('ocrsubmission_submission', $oldid, $newid, true);
    }

    /**
     * Process each grade.
     *
     * @param array $data Restore data.
     */
    protected function process_ocrsubmission_grade(array $data): void {
        global $DB;

        $data = (object) $data;

        $data->ocrsubmissionid = $this->get_new_parentid('ocrsubmission');
        $data->userid          = $this->get_mappingid('user', $data->userid);
        $data->grader          = $this->get_mappingid('user', $data->grader);
        $data->timegraded      = $this->apply_date_offset($data->timegraded);
        $data->timemodified    = $this->apply_date_offset($data->timemodified);

        $DB->insert_record('ocrsubmission_grades', $data);
    }

    /**
     * Post-execution actions.
     */
    protected function after_execute(): void {
        // Add ocrsubmission related files, no need to match by itemname (just default).
        $this->add_related_files('mod_ocrsubmission', 'intro', null);
        $this->add_related_files('mod_ocrsubmission', 'submission', 'ocrsubmission_submission');
    }
}
