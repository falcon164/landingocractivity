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
 * The task that provides the complete moodle2 backup implementation for ocrsubmission.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ocrsubmission/backup/moodle2/backup_ocrsubmission_stepslib.php');

/**
 * ocrsubmission backup task.
 */
class backup_ocrsubmission_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines a backup step to store the instance data in the ocrsubmission.xml file.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_ocrsubmission_activity_structure_step('ocrsubmission_structure', 'ocrsubmission.xml'));
    }

    /**
     * Encodes URLs to the index.php and view.php scripts.
     *
     * @param string $content The content.
     * @return string The encoded content.
     */
    public static function encode_content_links(string $content): string {
        global $CFG;

        $base = preg_quote($CFG->wwwroot, '/');

        // Link to the list of ocrsubmissions.
        $search  = '/(' . $base . '\/mod\/ocrsubmission\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@OCRSUBMISSIONINDEX*$2@$', $content);

        // Link to ocrsubmission view by moduleid.
        $search  = '/(' . $base . '\/mod\/ocrsubmission\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@OCRSUBMISSIONVIEWBYID*$2@$', $content);

        return $content;
    }
}
