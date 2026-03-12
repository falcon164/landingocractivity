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
 * The mod_landingocractivity submission graded event.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_landingocractivity\event;

/**
 * The mod_landingocractivity submission graded event class.
 *
 * @package    mod_landingocractivity
 * @copyright  2024, LandingAI OCR Submission
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class submission_graded extends \core\event\base {

    /**
     * Init method.
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_TEACHING;
        $this->data['objecttable'] = 'landingocractivity_grades';
    }

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        return "The user with id '{$this->userid}' graded the submission from user with id "
            . "'{$this->relateduserid}' for the landingocractivity activity with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventsubmissiongraded', 'mod_landingocractivity');
    }

    /**
     * Get URL related to the action.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url(
            '/mod/landingocractivity/view.php',
            ['id' => $this->contextinstanceid, 'userid' => $this->relateduserid]
        );
    }

    /**
     * Custom validation.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The relateduserid must be set.');
        }
    }
}
