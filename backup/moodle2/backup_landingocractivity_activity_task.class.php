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
 * The task that provides the complete moodle2 backup implementation for landingocractivity.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/landingocractivity/backup/moodle2/backup_landingocractivity_stepslib.php');

/**
 * landingocractivity backup task.
 */
class backup_landingocractivity_activity_task extends backup_activity_task {

    /**
     * No specific settings for this activity.
     */
    protected function define_my_settings(): void {
    }

    /**
     * Defines a backup step to store the instance data in the landingocractivity.xml file.
     */
    protected function define_my_steps(): void {
        $this->add_step(new backup_landingocractivity_activity_structure_step('landingocractivity_structure', 'landingocractivity.xml'));
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

        // Link to the list of landingocractivity instances.
        $search  = '/(' . $base . '\/mod\/landingocractivity\/index\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@LANDINGOCRACTIVITYINDEX*$2@$', $content);

        // Link to landingocractivity view by moduleid.
        $search  = '/(' . $base . '\/mod\/landingocractivity\/view\.php\?id\=)([0-9]+)/';
        $content = preg_replace($search, '$@LANDINGOCRACTIVITYVIEWBYID*$2@$', $content);

        return $content;
    }
}
