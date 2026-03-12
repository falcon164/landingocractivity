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
 * Grade redirect for landingocractivity.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/landingocractivity/lib.php');

$id     = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);
$offset = optional_param('offset', 0, PARAM_INT);
$itemnumber = optional_param('itemnumber', 0, PARAM_INT);

if ($id) {
    $cm     = get_coursemodule_from_id('landingocractivity', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
} else {
    throw new moodle_exception('invalidcmid');
}

require_login($course, false, $cm);

$params = ['id' => $cm->id];
if ($userid) {
    $params['userid'] = $userid;
}

redirect(new moodle_url('/mod/landingocractivity/view.php', $params));
