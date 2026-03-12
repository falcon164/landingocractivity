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
 * The task that provides the complete moodle2 restore implementation for ocrsubmission.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ocrsubmission/backup/moodle2/restore_ocrsubmission_stepslib.php');

/**
 * ocrsubmission restore task.
 */
class restore_ocrsubmission_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_ocrsubmission_activity_structure_step('ocrsubmission_structure', 'ocrsubmission.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('ocrsubmission', ['intro'], 'ocrsubmission'),
        ];
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('OCRSUBMISSIONVIEWBYID', '/mod/ocrsubmission/view.php?id=$1', 'course_module'),
            new restore_decode_rule('OCRSUBMISSIONINDEX', '/mod/ocrsubmission/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor
     * when restoring ocrsubmission logs. It must return one array of restore_log_rule objects.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules(): array {
        return [
            new restore_log_rule('ocrsubmission', 'view', 'view.php?id={course_module}', '{ocrsubmission}'),
        ];
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor
     * when restoring course logs. It must return one array of restore_log_rule objects.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules_for_course(): array {
        return [
            new restore_log_rule('ocrsubmission', 'view all', 'index.php?id={course}', null),
        ];
    }
}
