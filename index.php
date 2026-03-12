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
 * Index page listing all ocrsubmission instances in a course.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/ocrsubmission/lib.php');

$id = required_param('id', PARAM_INT);

$course = $DB->get_record('course', ['id' => $id], '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/ocrsubmission/index.php', ['id' => $id]);
$PAGE->set_title(format_string($course->shortname) . ': ' . get_string('modulenameplural', 'mod_ocrsubmission'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'mod_ocrsubmission'));

// Get all ocrsubmission instances in this course.
$ocrsubmissions = get_all_instances_in_course('ocrsubmission', $course);

if (empty($ocrsubmissions)) {
    notice(get_string('nosubmission', 'mod_ocrsubmission'), new moodle_url('/course/view.php', ['id' => $course->id]));
}

$table = new html_table();
$table->attributes['class'] = 'generaltable';
$usesections = course_format_uses_sections($course->format);

if ($usesections) {
    $table->head  = [get_string('sectionname', 'format_' . $course->format), get_string('name'), get_string('intro')];
    $table->align = ['center', 'left', 'left'];
} else {
    $table->head  = [get_string('name'), get_string('intro')];
    $table->align = ['left', 'left'];
}

$modinfo     = get_fast_modinfo($course);
$currentsection = '';

foreach ($ocrsubmissions as $ocrsubmission) {
    $cm = $modinfo->cms[$ocrsubmission->coursemodule];

    $link = html_writer::link(
        new moodle_url('/mod/ocrsubmission/view.php', ['id' => $cm->id]),
        format_string($ocrsubmission->name, true)
    );

    if (!$ocrsubmission->visible) {
        $link = html_writer::tag('span', $link, ['class' => 'dimmed_text']);
    }

    $intro = format_module_intro('ocrsubmission', $ocrsubmission, $cm->id);

    if ($usesections) {
        if ($ocrsubmission->section !== $currentsection) {
            if ($ocrsubmission->section) {
                $table->data[] = 'hr';
            }
            $currentsection = $ocrsubmission->section;
        }
        $sectionname = get_section_name($course, $ocrsubmission->section);
        $table->data[] = [$sectionname, $link, $intro];
    } else {
        $table->data[] = [$link, $intro];
    }
}

echo html_writer::table($table);
echo $OUTPUT->footer();
