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
 * Displays an landingocractivity activity instance.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/landingocractivity/lib.php');
require_once($CFG->libdir . '/formslib.php');

$id     = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if ($id) {
    $cm     = get_coursemodule_from_id('landingocractivity', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $landingocractivity = $DB->get_record('landingocractivity', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new \moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/landingocractivity:view', $context);

$PAGE->set_url('/mod/landingocractivity/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($landingocractivity->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Log the view event.
$event = \mod_landingocractivity\event\course_module_viewed::create([
    'objectid' => $landingocractivity->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('landingocractivity', $landingocractivity);
$event->trigger();

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$isteacher = has_capability('mod/landingocractivity:grade', $context);

// Handle grade save (teacher action) using the moodleform.
if ($isteacher && $userid) {
    $gradeformurl = new moodle_url('/mod/landingocractivity/view.php', [
        'id'     => $cm->id,
        'userid' => $userid,
        'action' => 'savegrade',
    ]);

    $gradeform = new \mod_landingocractivity\form\grade_feedback($gradeformurl->out(false), [
        'maxgrade' => $landingocractivity->grade,
        'context'  => $context,
        'cmid'     => $cm->id,
        'userid'   => $userid,
    ]);

    if ($formdata = $gradeform->get_data()) {
        require_capability('mod/landingocractivity:grade', $context);

        $targetuserid = (int) $formdata->userid;
        $grade        = $formdata->grade;
        $feedbacktext = $formdata->feedback_editor['text'] ?? '';
        $feedbackformat = $formdata->feedback_editor['format'] ?? FORMAT_HTML;

        // Determine numeric grade value.
        $gradevalue = null;
        if ($grade !== '' && $grade !== null) {
            $gradevalue = (float) $grade;
        }

        // Save or update grade record.
        $existing = $DB->get_record('landingocractivity_grades', [
            'landingocractivityid' => $landingocractivity->id,
            'userid' => $targetuserid,
        ]);

        $record = new stdClass();
        $record->landingocractivityid = $landingocractivity->id;
        $record->userid          = $targetuserid;
        $record->grader          = $USER->id;
        $record->grade           = $gradevalue;
        $record->feedback        = $feedbacktext;
        $record->feedbackformat  = $feedbackformat;
        $record->timegraded      = time();
        $record->timemodified    = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('landingocractivity_grades', $record);
            $gradeid = $existing->id;
        } else {
            $gradeid = $DB->insert_record('landingocractivity_grades', $record);
        }

        // Update gradebook.
        if ($gradevalue !== null) {
            $gradeobj = new stdClass();
            $gradeobj->userid   = $targetuserid;
            $gradeobj->rawgrade = $gradevalue;
            landingocractivity_grade_item_update($landingocractivity, [$targetuserid => $gradeobj]);
        }

        // Fire event.
        $event = \mod_landingocractivity\event\submission_graded::create([
            'objectid'      => $gradeid,
            'context'       => $context,
            'relateduserid' => $targetuserid,
        ]);
        $event->trigger();

        redirect(
            new moodle_url('/mod/landingocractivity/view.php', ['id' => $cm->id, 'userid' => $targetuserid]),
            get_string('gradesaved', 'mod_landingocractivity'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($landingocractivity->name));

// Show description.
if ($landingocractivity->intro) {
    echo $OUTPUT->box(
        format_module_intro('landingocractivity', $landingocractivity, $cm->id),
        'generalbox mod_introbox'
    );
}

if ($isteacher) {
    // Teacher view: show a specific student's submission or the submission list.
    if ($userid) {
        $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        echo $OUTPUT->heading(
            get_string('gradefor', 'mod_landingocractivity', fullname($student)),
            3
        );

        $submission = $DB->get_record('landingocractivity_submissions', [
            'landingocractivityid' => $landingocractivity->id,
            'userid' => $userid,
        ]);

        if (!$submission) {
            echo $OUTPUT->notification(get_string('nosubmission', 'mod_landingocractivity'));
        } else {
            // Show the uploaded file.
            echo $OUTPUT->heading(get_string('yoursubmission', 'mod_landingocractivity'), 4);

            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $context->id,
                'mod_landingocractivity',
                'submission',
                $submission->id,
                'id DESC',
                false
            );

            if (!empty($files)) {
                $file = reset($files);
                $fileurl = moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_landingocractivity',
                    'submission',
                    $submission->id,
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );
                echo html_writer::tag(
                    'p',
                    html_writer::link($fileurl, get_string('downloadfile', 'mod_landingocractivity'), ['target' => '_blank'])
                );
            }

            // Show OCR status and text.
            echo $OUTPUT->heading(get_string('ocrtext', 'mod_landingocractivity'), 4);
            $statuslabel = get_string('ocrstatus_' . $submission->status, 'mod_landingocractivity');
            echo html_writer::tag('p',
                html_writer::tag('strong', get_string('ocrstatus', 'mod_landingocractivity') . ': ') . $statuslabel
            );

            if ($submission->status === 'complete' && !empty($submission->ocr_text)) {
                echo html_writer::tag(
                    'div',
                    html_writer::tag('pre', s($submission->ocr_text), ['class' => 'landingocractivity-ocrtext']),
                    ['class' => 'landingocractivity-ocrtext-container card p-3 mb-3']
                );
            } else if ($submission->status === 'error') {
                echo $OUTPUT->notification(
                    get_string('ocrerrormessage', 'mod_landingocractivity', s($submission->error_message)),
                    \core\output\notification::NOTIFY_ERROR
                );
            } else if (in_array($submission->status, ['pending', 'processing'])) {
                echo $OUTPUT->notification(get_string('pleasewait', 'mod_landingocractivity'), \core\output\notification::NOTIFY_INFO);
            }
        }

        // Show existing grade/feedback.
        $graderecord = $DB->get_record('landingocractivity_grades', [
            'landingocractivityid' => $landingocractivity->id,
            'userid' => $userid,
        ]);

        // Show grade form using the grade_feedback moodleform (already instantiated above).
        echo $OUTPUT->heading(get_string('feedback', 'mod_landingocractivity'), 4);

        // Set default values from existing grade record.
        $formdefaults = [
            'grade'    => $graderecord ? (string) $graderecord->grade : '',
            'cmid'     => $cm->id,
            'userid'   => $userid,
            'maxgrade' => $landingocractivity->grade,
        ];
        if ($graderecord) {
            $formdefaults['feedback_editor'] = [
                'text'   => $graderecord->feedback ?? '',
                'format' => $graderecord->feedbackformat ?? FORMAT_HTML,
            ];
        }
        $gradeform->set_data($formdefaults);
        $gradeform->display();

        // Show previously saved grade info.
        if ($graderecord) {
            echo html_writer::start_div('mt-3 text-muted small');
            $grader = $DB->get_record('user', ['id' => $graderecord->grader]);
            if ($grader) {
                echo html_writer::tag('p', get_string('gradedby', 'mod_landingocractivity', fullname($grader)));
            }
            echo html_writer::tag('p', get_string('gradedon', 'mod_landingocractivity',
                userdate($graderecord->timegraded)));
            echo html_writer::end_div();
        }

        // Back link.
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/mod/landingocractivity/view.php', ['id' => $cm->id]),
            '&laquo; ' . get_string('viewsubmissions', 'mod_landingocractivity')
        ), ['class' => 'mt-3']);

    } else {
        // Show submission list.
        echo $OUTPUT->heading(get_string('submissionlist', 'mod_landingocractivity'), 3);

        $submissions = $DB->get_records('landingocractivity_submissions', ['landingocractivityid' => $landingocractivity->id]);

        if (empty($submissions)) {
            echo $OUTPUT->notification(get_string('nostudentsubmissions', 'mod_landingocractivity'), 'info');
        } else {
            $table = new html_table();
            $table->head = [
                get_string('student', 'mod_landingocractivity'),
                get_string('submissiondate', 'mod_landingocractivity'),
                get_string('ocrstatus', 'mod_landingocractivity'),
                get_string('grade', 'mod_landingocractivity'),
                get_string('actions', 'mod_landingocractivity'),
            ];
            $table->attributes['class'] = 'generaltable table table-striped';

            foreach ($submissions as $sub) {
                $student = $DB->get_record('user', ['id' => $sub->userid]);
                $graderecord = $DB->get_record('landingocractivity_grades', [
                    'landingocractivityid' => $landingocractivity->id,
                    'userid'          => $sub->userid,
                ]);

                $gradedisplay = $graderecord && $graderecord->grade !== null
                    ? format_float($graderecord->grade, 2) . ' / ' . $landingocractivity->grade
                    : get_string('notgraded', 'mod_landingocractivity');

                $viewurl = new moodle_url('/mod/landingocractivity/view.php', [
                    'id'     => $cm->id,
                    'userid' => $sub->userid,
                ]);

                $row = new html_table_row([
                    fullname($student),
                    userdate($sub->timecreated),
                    get_string('ocrstatus_' . $sub->status, 'mod_landingocractivity'),
                    $gradedisplay,
                    html_writer::link($viewurl, get_string('viewgrade', 'mod_landingocractivity'),
                        ['class' => 'btn btn-sm btn-outline-primary']),
                ]);

                $table->data[] = $row;
            }

            echo html_writer::table($table);
        }
    }
} else {
    // Student view.
    $submission = $DB->get_record('landingocractivity_submissions', [
        'landingocractivityid' => $landingocractivity->id,
        'userid'          => $USER->id,
    ]);

    // Upload button.
    $submiturl = new moodle_url('/mod/landingocractivity/submit.php', ['id' => $cm->id]);
    $buttonlabel = $submission ? get_string('resubmit', 'mod_landingocractivity') : get_string('submitfile', 'mod_landingocractivity');
    echo html_writer::div(
        $OUTPUT->single_button($submiturl, $buttonlabel, 'get'),
        'landingocractivity-submit-btn mb-3'
    );

    if (!$submission) {
        echo $OUTPUT->notification(get_string('nosubmission', 'mod_landingocractivity'), 'info');
    } else {
        echo $OUTPUT->heading(get_string('yoursubmission', 'mod_landingocractivity'), 3);

        // Show file download link.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_landingocractivity',
            'submission',
            $submission->id,
            'id DESC',
            false
        );

        if (!empty($files)) {
            $file = reset($files);
            $fileurl = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_landingocractivity',
                'submission',
                $submission->id,
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            echo html_writer::tag(
                'p',
                html_writer::link($fileurl, get_string('downloadfile', 'mod_landingocractivity'), ['target' => '_blank'])
            );
        }

        // OCR status.
        $statuslabel = get_string('ocrstatus_' . $submission->status, 'mod_landingocractivity');
        echo html_writer::tag('p',
            html_writer::tag('strong', get_string('ocrstatus', 'mod_landingocractivity') . ': ') . $statuslabel
        );

        if ($submission->status === 'complete' && !empty($submission->ocr_text)) {
            echo $OUTPUT->heading(get_string('ocrtext', 'mod_landingocractivity'), 4);
            echo html_writer::tag(
                'div',
                html_writer::tag('pre', s($submission->ocr_text), ['class' => 'landingocractivity-ocrtext']),
                ['class' => 'landingocractivity-ocrtext-container card p-3 mb-3']
            );
        } else if ($submission->status === 'error') {
            echo $OUTPUT->notification(
                get_string('ocrerrormessage', 'mod_landingocractivity', s($submission->error_message)),
                \core\output\notification::NOTIFY_ERROR
            );
        } else if (in_array($submission->status, ['pending', 'processing'])) {
            echo $OUTPUT->notification(get_string('pleasewait', 'mod_landingocractivity'), \core\output\notification::NOTIFY_INFO);
        }

        // Teacher feedback.
        $graderecord = $DB->get_record('landingocractivity_grades', [
            'landingocractivityid' => $landingocractivity->id,
            'userid'          => $USER->id,
        ]);

        if ($graderecord) {
            echo $OUTPUT->heading(get_string('feedback', 'mod_landingocractivity'), 4);
            if ($graderecord->grade !== null) {
                echo html_writer::tag('p',
                    html_writer::tag('strong', get_string('grade', 'mod_landingocractivity') . ': ')
                    . format_float($graderecord->grade, 2) . ' / ' . $landingocractivity->grade
                );
            }
            if (!empty($graderecord->feedback)) {
                echo html_writer::div(
                    format_text($graderecord->feedback, $graderecord->feedbackformat),
                    'landingocractivity-feedback card p-3'
                );
            } else {
                echo html_writer::tag('p', get_string('nofeedback', 'mod_landingocractivity'));
            }
        }
    }
}

echo $OUTPUT->footer();
