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
 * Displays an ocrsubmission activity instance.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/ocrsubmission/lib.php');
require_once($CFG->libdir . '/formslib.php');

$id     = optional_param('id', 0, PARAM_INT);
$userid = optional_param('userid', 0, PARAM_INT);

if ($id) {
    $cm     = get_coursemodule_from_id('ocrsubmission', $id, 0, false, MUST_EXIST);
    $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
    $ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $cm->instance], '*', MUST_EXIST);
} else {
    throw new \moodle_exception('invalidcoursemodule');
}

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/ocrsubmission:view', $context);

$PAGE->set_url('/mod/ocrsubmission/view.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($ocrsubmission->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

// Log the view event.
$event = \mod_ocrsubmission\event\course_module_viewed::create([
    'objectid' => $ocrsubmission->id,
    'context'  => $context,
]);
$event->add_record_snapshot('course_modules', $cm);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot('ocrsubmission', $ocrsubmission);
$event->trigger();

// Mark as viewed.
$completion = new completion_info($course);
$completion->set_module_viewed($cm);

$isteacher = has_capability('mod/ocrsubmission:grade', $context);

// Handle grade save (teacher action) using the moodleform.
if ($isteacher && $userid) {
    $gradeformurl = new moodle_url('/mod/ocrsubmission/view.php', [
        'id'     => $cm->id,
        'userid' => $userid,
        'action' => 'savegrade',
    ]);

    $gradeform = new \mod_ocrsubmission\form\grade_feedback($gradeformurl->out(false), [
        'maxgrade' => $ocrsubmission->grade,
        'context'  => $context,
        'cmid'     => $cm->id,
        'userid'   => $userid,
    ]);

    if ($formdata = $gradeform->get_data()) {
        require_capability('mod/ocrsubmission:grade', $context);

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
        $existing = $DB->get_record('ocrsubmission_grades', [
            'ocrsubmissionid' => $ocrsubmission->id,
            'userid' => $targetuserid,
        ]);

        $record = new stdClass();
        $record->ocrsubmissionid = $ocrsubmission->id;
        $record->userid          = $targetuserid;
        $record->grader          = $USER->id;
        $record->grade           = $gradevalue;
        $record->feedback        = $feedbacktext;
        $record->feedbackformat  = $feedbackformat;
        $record->timegraded      = time();
        $record->timemodified    = time();

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('ocrsubmission_grades', $record);
            $gradeid = $existing->id;
        } else {
            $gradeid = $DB->insert_record('ocrsubmission_grades', $record);
        }

        // Update gradebook.
        if ($gradevalue !== null) {
            $gradeobj = new stdClass();
            $gradeobj->userid   = $targetuserid;
            $gradeobj->rawgrade = $gradevalue;
            ocrsubmission_grade_item_update($ocrsubmission, [$targetuserid => $gradeobj]);
        }

        // Fire event.
        $event = \mod_ocrsubmission\event\submission_graded::create([
            'objectid'      => $gradeid,
            'context'       => $context,
            'relateduserid' => $targetuserid,
        ]);
        $event->trigger();

        redirect(
            new moodle_url('/mod/ocrsubmission/view.php', ['id' => $cm->id, 'userid' => $targetuserid]),
            get_string('gradesaved', 'mod_ocrsubmission'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($ocrsubmission->name));

// Show description.
if ($ocrsubmission->intro) {
    echo $OUTPUT->box(
        format_module_intro('ocrsubmission', $ocrsubmission, $cm->id),
        'generalbox mod_introbox'
    );
}

if ($isteacher) {
    // Teacher view: show a specific student's submission or the submission list.
    if ($userid) {
        $student = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
        echo $OUTPUT->heading(
            get_string('gradefor', 'mod_ocrsubmission', fullname($student)),
            3
        );

        $submission = $DB->get_record('ocrsubmission_submissions', [
            'ocrsubmissionid' => $ocrsubmission->id,
            'userid' => $userid,
        ]);

        if (!$submission) {
            echo $OUTPUT->notification(get_string('nosubmission', 'mod_ocrsubmission'));
        } else {
            // Show the uploaded file.
            echo $OUTPUT->heading(get_string('yoursubmission', 'mod_ocrsubmission'), 4);

            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $context->id,
                'mod_ocrsubmission',
                'submission',
                $submission->id,
                'id DESC',
                false
            );

            if (!empty($files)) {
                $file = reset($files);
                $fileurl = moodle_url::make_pluginfile_url(
                    $context->id,
                    'mod_ocrsubmission',
                    'submission',
                    $submission->id,
                    $file->get_filepath(),
                    $file->get_filename(),
                    false
                );
                echo html_writer::tag(
                    'p',
                    html_writer::link($fileurl, get_string('downloadfile', 'mod_ocrsubmission'), ['target' => '_blank'])
                );
            }

            // Show OCR status and text.
            echo $OUTPUT->heading(get_string('ocrtext', 'mod_ocrsubmission'), 4);
            $statuslabel = get_string('ocrstatus_' . $submission->status, 'mod_ocrsubmission');
            echo html_writer::tag('p',
                html_writer::tag('strong', get_string('ocrstatus', 'mod_ocrsubmission') . ': ') . $statuslabel
            );

            if ($submission->status === 'complete' && !empty($submission->ocr_text)) {
                echo html_writer::tag(
                    'div',
                    html_writer::tag('pre', s($submission->ocr_text), ['class' => 'ocrsubmission-ocrtext']),
                    ['class' => 'ocrsubmission-ocrtext-container card p-3 mb-3']
                );
            } else if ($submission->status === 'error') {
                echo $OUTPUT->notification(
                    get_string('ocrerrormessage', 'mod_ocrsubmission', s($submission->error_message)),
                    \core\output\notification::NOTIFY_ERROR
                );
            } else if (in_array($submission->status, ['pending', 'processing'])) {
                echo $OUTPUT->notification(get_string('pleasewait', 'mod_ocrsubmission'), \core\output\notification::NOTIFY_INFO);
            }
        }

        // Show existing grade/feedback.
        $graderecord = $DB->get_record('ocrsubmission_grades', [
            'ocrsubmissionid' => $ocrsubmission->id,
            'userid' => $userid,
        ]);

        // Show grade form using the grade_feedback moodleform (already instantiated above).
        echo $OUTPUT->heading(get_string('feedback', 'mod_ocrsubmission'), 4);

        // Set default values from existing grade record.
        $formdefaults = [
            'grade'    => $graderecord ? (string) $graderecord->grade : '',
            'cmid'     => $cm->id,
            'userid'   => $userid,
            'maxgrade' => $ocrsubmission->grade,
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
                echo html_writer::tag('p', get_string('gradedby', 'mod_ocrsubmission', fullname($grader)));
            }
            echo html_writer::tag('p', get_string('gradedon', 'mod_ocrsubmission',
                userdate($graderecord->timegraded)));
            echo html_writer::end_div();
        }

        // Back link.
        echo html_writer::tag('p', html_writer::link(
            new moodle_url('/mod/ocrsubmission/view.php', ['id' => $cm->id]),
            '&laquo; ' . get_string('viewsubmissions', 'mod_ocrsubmission')
        ), ['class' => 'mt-3']);

    } else {
        // Show submission list.
        echo $OUTPUT->heading(get_string('submissionlist', 'mod_ocrsubmission'), 3);

        $submissions = $DB->get_records('ocrsubmission_submissions', ['ocrsubmissionid' => $ocrsubmission->id]);

        if (empty($submissions)) {
            echo $OUTPUT->notification(get_string('nostudentsubmissions', 'mod_ocrsubmission'), 'info');
        } else {
            $table = new html_table();
            $table->head = [
                get_string('student', 'mod_ocrsubmission'),
                get_string('submissiondate', 'mod_ocrsubmission'),
                get_string('ocrstatus', 'mod_ocrsubmission'),
                get_string('grade', 'mod_ocrsubmission'),
                get_string('actions', 'mod_ocrsubmission'),
            ];
            $table->attributes['class'] = 'generaltable table table-striped';

            foreach ($submissions as $sub) {
                $student = $DB->get_record('user', ['id' => $sub->userid]);
                $graderecord = $DB->get_record('ocrsubmission_grades', [
                    'ocrsubmissionid' => $ocrsubmission->id,
                    'userid'          => $sub->userid,
                ]);

                $gradedisplay = $graderecord && $graderecord->grade !== null
                    ? format_float($graderecord->grade, 2) . ' / ' . $ocrsubmission->grade
                    : get_string('notgraded', 'mod_ocrsubmission');

                $viewurl = new moodle_url('/mod/ocrsubmission/view.php', [
                    'id'     => $cm->id,
                    'userid' => $sub->userid,
                ]);

                $row = new html_table_row([
                    fullname($student),
                    userdate($sub->timecreated),
                    get_string('ocrstatus_' . $sub->status, 'mod_ocrsubmission'),
                    $gradedisplay,
                    html_writer::link($viewurl, get_string('viewgrade', 'mod_ocrsubmission'),
                        ['class' => 'btn btn-sm btn-outline-primary']),
                ]);

                $table->data[] = $row;
            }

            echo html_writer::table($table);
        }
    }
} else {
    // Student view.
    $submission = $DB->get_record('ocrsubmission_submissions', [
        'ocrsubmissionid' => $ocrsubmission->id,
        'userid'          => $USER->id,
    ]);

    // Upload button.
    $submiturl = new moodle_url('/mod/ocrsubmission/submit.php', ['id' => $cm->id]);
    $buttonlabel = $submission ? get_string('resubmit', 'mod_ocrsubmission') : get_string('submitfile', 'mod_ocrsubmission');
    echo html_writer::div(
        $OUTPUT->single_button($submiturl, $buttonlabel, 'get'),
        'ocrsubmission-submit-btn mb-3'
    );

    if (!$submission) {
        echo $OUTPUT->notification(get_string('nosubmission', 'mod_ocrsubmission'), 'info');
    } else {
        echo $OUTPUT->heading(get_string('yoursubmission', 'mod_ocrsubmission'), 3);

        // Show file download link.
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $context->id,
            'mod_ocrsubmission',
            'submission',
            $submission->id,
            'id DESC',
            false
        );

        if (!empty($files)) {
            $file = reset($files);
            $fileurl = moodle_url::make_pluginfile_url(
                $context->id,
                'mod_ocrsubmission',
                'submission',
                $submission->id,
                $file->get_filepath(),
                $file->get_filename(),
                false
            );
            echo html_writer::tag(
                'p',
                html_writer::link($fileurl, get_string('downloadfile', 'mod_ocrsubmission'), ['target' => '_blank'])
            );
        }

        // OCR status.
        $statuslabel = get_string('ocrstatus_' . $submission->status, 'mod_ocrsubmission');
        echo html_writer::tag('p',
            html_writer::tag('strong', get_string('ocrstatus', 'mod_ocrsubmission') . ': ') . $statuslabel
        );

        if ($submission->status === 'complete' && !empty($submission->ocr_text)) {
            echo $OUTPUT->heading(get_string('ocrtext', 'mod_ocrsubmission'), 4);
            echo html_writer::tag(
                'div',
                html_writer::tag('pre', s($submission->ocr_text), ['class' => 'ocrsubmission-ocrtext']),
                ['class' => 'ocrsubmission-ocrtext-container card p-3 mb-3']
            );
        } else if ($submission->status === 'error') {
            echo $OUTPUT->notification(
                get_string('ocrerrormessage', 'mod_ocrsubmission', s($submission->error_message)),
                \core\output\notification::NOTIFY_ERROR
            );
        } else if (in_array($submission->status, ['pending', 'processing'])) {
            echo $OUTPUT->notification(get_string('pleasewait', 'mod_ocrsubmission'), \core\output\notification::NOTIFY_INFO);
        }

        // Teacher feedback.
        $graderecord = $DB->get_record('ocrsubmission_grades', [
            'ocrsubmissionid' => $ocrsubmission->id,
            'userid'          => $USER->id,
        ]);

        if ($graderecord) {
            echo $OUTPUT->heading(get_string('feedback', 'mod_ocrsubmission'), 4);
            if ($graderecord->grade !== null) {
                echo html_writer::tag('p',
                    html_writer::tag('strong', get_string('grade', 'mod_ocrsubmission') . ': ')
                    . format_float($graderecord->grade, 2) . ' / ' . $ocrsubmission->grade
                );
            }
            if (!empty($graderecord->feedback)) {
                echo html_writer::div(
                    format_text($graderecord->feedback, $graderecord->feedbackformat),
                    'ocrsubmission-feedback card p-3'
                );
            } else {
                echo html_writer::tag('p', get_string('nofeedback', 'mod_ocrsubmission'));
            }
        }
    }
}

echo $OUTPUT->footer();
