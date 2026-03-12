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
 * The task that provides the complete moodle2 restore implementation for landingocractivity.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/landingocractivity/backup/moodle2/restore_landingocractivity_stepslib.php');

/**
 * landingocractivity restore task.
 */
class restore_landingocractivity_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Define (add) particular steps this activity can have.
     */
    protected function define_my_steps(): void {
        $this->add_step(new restore_landingocractivity_activity_structure_step('landingocractivity_structure', 'landingocractivity.xml'));
    }

    /**
     * Define the contents in the activity that must be processed by the link decoder.
     *
     * @return restore_decode_content[]
     */
    public static function define_decode_contents(): array {
        return [
            new restore_decode_content('landingocractivity', ['intro'], 'landingocractivity'),
        ];
    }

    /**
     * Define the decoding rules for links belonging to the activity to be executed by the link decoder.
     *
     * @return restore_decode_rule[]
     */
    public static function define_decode_rules(): array {
        return [
            new restore_decode_rule('LANDINGOCRACTIVITYVIEWBYID', '/mod/landingocractivity/view.php?id=$1', 'course_module'),
            new restore_decode_rule('LANDINGOCRACTIVITYINDEX', '/mod/landingocractivity/index.php?id=$1', 'course'),
        ];
    }

    /**
     * Define the restore log rules that will be applied by the restore_logs_processor
     * when restoring landingocractivity logs. It must return one array of restore_log_rule objects.
     *
     * @return restore_log_rule[]
     */
    public static function define_restore_log_rules(): array {
        return [
            new restore_log_rule('landingocractivity', 'view', 'view.php?id={course_module}', '{landingocractivity}'),
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
            new restore_log_rule('landingocractivity', 'view all', 'index.php?id={course}', null),
        ];
    }
}
