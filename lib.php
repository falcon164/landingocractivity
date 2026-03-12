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
 * Library of interface functions and constants for the ocrsubmission module.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Return if the plugin supports a feature.
 *
 * @param string $feature Constant representing the feature.
 * @return mixed True if supported, null if unknown, false if not supported.
 */
function ocrsubmission_supports(string $feature): mixed {
    switch ($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_GRADE_OUTCOMES:
            return false;
        case FEATURE_GROUPS:
            return false;
        case FEATURE_GROUPINGS:
            return false;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_MOD_PURPOSE:
            return MOD_PURPOSE_ASSESSMENT;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the ocrsubmission into the database.
 *
 * @param stdClass $data An object from the form in mod_form.php.
 * @param mod_ocrsubmission_mod_form|null $mform The form.
 * @return int The id of the newly inserted record.
 */
function ocrsubmission_add_instance(stdClass $data, ?mod_ocrsubmission_mod_form $mform = null): int {
    global $DB;

    $data->timecreated  = time();
    $data->timemodified = time();

    $data->id = $DB->insert_record('ocrsubmission', $data);

    ocrsubmission_grade_item_update($data);

    return $data->id;
}

/**
 * Updates an instance of the ocrsubmission in the database.
 *
 * @param stdClass $data An object from the form in mod_form.php.
 * @param mod_ocrsubmission_mod_form|null $mform The form.
 * @return bool True if successful, false otherwise.
 */
function ocrsubmission_update_instance(stdClass $data, ?mod_ocrsubmission_mod_form $mform = null): bool {
    global $DB;

    $data->timemodified = time();
    $data->id = $data->instance;

    $result = $DB->update_record('ocrsubmission', $data);

    ocrsubmission_grade_item_update($data);

    return $result;
}

/**
 * Removes an instance of the ocrsubmission from the database.
 *
 * @param int $id Id of the module instance.
 * @return bool True if successful, false on failure.
 */
function ocrsubmission_delete_instance(int $id): bool {
    global $DB;

    $ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $id]);
    if (!$ocrsubmission) {
        return false;
    }

    $DB->delete_records('ocrsubmission_grades', ['ocrsubmissionid' => $id]);
    $DB->delete_records('ocrsubmission_submissions', ['ocrsubmissionid' => $id]);
    $DB->delete_records('ocrsubmission', ['id' => $id]);

    ocrsubmission_grade_item_delete($ocrsubmission);

    return true;
}

/**
 * Returns the lists of all browsable file areas within the given module context.
 *
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param stdClass $context The context.
 * @return array An array with file areas.
 */
function ocrsubmission_get_file_areas(stdClass $course, stdClass $cm, stdClass $context): array {
    return [
        'submission' => get_string('filearea_submission', 'mod_ocrsubmission'),
    ];
}

/**
 * File browsing support for ocrsubmission file areas.
 *
 * @param file_browser $browser The file browser.
 * @param array $areas File areas.
 * @param stdClass $course The course.
 * @param stdClass $cm The course module.
 * @param stdClass $context The context.
 * @param string $filearea The file area.
 * @param int|null $itemid The item id.
 * @param string|null $filepath The file path.
 * @param string|null $filename The filename.
 * @return file_info|null Instance of file_info, or null if not found.
 */
function ocrsubmission_get_file_info(
    file_browser $browser,
    array $areas,
    stdClass $course,
    stdClass $cm,
    stdClass $context,
    string $filearea,
    ?int $itemid,
    ?string $filepath,
    ?string $filename
): ?file_info {
    return null;
}

/**
 * Serves the files from the ocrsubmission file areas.
 *
 * @param stdClass $course The course object.
 * @param stdClass $cm The course module object.
 * @param stdClass $context The ocrsubmission's context.
 * @param string $filearea The name of the file area.
 * @param array $args Extra arguments (itemid, path).
 * @param bool $forcedownload Whether or not force download.
 * @param array $options Additional options affecting the file serving.
 * @return bool False if file not found, does not return if found.
 */
function ocrsubmission_pluginfile(
    stdClass $course,
    stdClass $cm,
    stdClass $context,
    string $filearea,
    array $args,
    bool $forcedownload,
    array $options = []
): bool {
    global $DB, $USER;

    if ($context->contextlevel != CONTEXT_MODULE) {
        return false;
    }

    require_login($course, true, $cm);

    if ($filearea !== 'submission') {
        return false;
    }

    $itemid = (int) array_shift($args);

    // Check the submission belongs to the current user or user is a teacher.
    $submission = $DB->get_record('ocrsubmission_submissions', ['id' => $itemid]);
    if (!$submission) {
        return false;
    }

    $isteacher = has_capability('mod/ocrsubmission:grade', $context);
    if ($submission->userid != $USER->id && !$isteacher) {
        return false;
    }

    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/{$context->id}/mod_ocrsubmission/{$filearea}/{$itemid}/{$relativepath}";
    $file = $fs->get_file_by_hash(sha1($fullpath));

    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
    return true;
}

/**
 * Creates or updates grade item for the given ocrsubmission instance.
 *
 * @param stdClass $ocrsubmission Instance object with extra cmidnumber and modname property.
 * @param mixed $grades Grade(s); false means do not update.
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED.
 */
function ocrsubmission_grade_item_update(stdClass $ocrsubmission, mixed $grades = false): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $item = [];
    $item['itemname'] = clean_param($ocrsubmission->name, PARAM_NOTAGS);
    $item['gradetype'] = GRADE_TYPE_VALUE;

    if ($ocrsubmission->grade > 0) {
        $item['gradetype'] = GRADE_TYPE_VALUE;
        $item['grademax']  = $ocrsubmission->grade;
        $item['grademin']  = 0;
    } else if ($ocrsubmission->grade < 0) {
        $item['gradetype'] = GRADE_TYPE_SCALE;
        $item['scaleid']   = -$ocrsubmission->grade;
    } else {
        $item['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $item['reset'] = true;
        $grades = null;
    }

    return grade_update(
        'mod/ocrsubmission',
        $ocrsubmission->course,
        'mod',
        'ocrsubmission',
        $ocrsubmission->id,
        0,
        $grades,
        $item
    );
}

/**
 * Delete grade item for given ocrsubmission instance.
 *
 * @param stdClass $ocrsubmission Instance object.
 * @return int Returns GRADE_UPDATE_OK, GRADE_UPDATE_FAILED, GRADE_UPDATE_MULTIPLE or GRADE_UPDATE_ITEM_LOCKED.
 */
function ocrsubmission_grade_item_delete(stdClass $ocrsubmission): int {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    return grade_update(
        'mod/ocrsubmission',
        $ocrsubmission->course,
        'mod',
        'ocrsubmission',
        $ocrsubmission->id,
        0,
        null,
        ['deleted' => 1]
    );
}

/**
 * Update ocrsubmission grades in the gradebook.
 *
 * @param stdClass $ocrsubmission Instance object with extra cmidnumber and modname property.
 * @param int $userid Update grade of specific user only, 0 means all participants.
 */
function ocrsubmission_update_grades(stdClass $ocrsubmission, int $userid = 0): void {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($ocrsubmission->grade == 0) {
        ocrsubmission_grade_item_update($ocrsubmission);
        return;
    }

    $conditions = ['ocrsubmissionid' => $ocrsubmission->id];
    if ($userid) {
        $conditions['userid'] = $userid;
    }

    $graderecords = $DB->get_records('ocrsubmission_grades', $conditions);
    $grades = [];

    foreach ($graderecords as $record) {
        $grade = new stdClass();
        $grade->userid   = $record->userid;
        $grade->rawgrade = $record->grade;
        $grades[$record->userid] = $grade;
    }

    if (empty($grades)) {
        ocrsubmission_grade_item_update($ocrsubmission);
    } else {
        ocrsubmission_grade_item_update($ocrsubmission, $grades);
    }
}

/**
 * Return the list of views available on the activity module.
 *
 * @param stdClass $cm The course module object.
 * @return array Array of views.
 */
function ocrsubmission_get_coursemodule_info(stdClass $cm): ?cached_cm_info {
    global $DB;

    $ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $cm->instance], 'id, name, intro, introformat');
    if (!$ocrsubmission) {
        return null;
    }

    $info = new cached_cm_info();
    $info->name = $ocrsubmission->name;

    if ($cm->showdescription) {
        $info->content = format_module_intro('ocrsubmission', $ocrsubmission, $cm->id, false);
    }

    return $info;
}
