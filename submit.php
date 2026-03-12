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
 * Student document submission page for ocrsubmission.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/ocrsubmission/lib.php');
require_once($CFG->libdir . '/filelib.php');

$id = required_param('id', PARAM_INT);

$cm     = get_coursemodule_from_id('ocrsubmission', $id, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, true, $cm);

$context = context_module::instance($cm->id);
require_capability('mod/ocrsubmission:submit', $context);

$PAGE->set_url('/mod/ocrsubmission/submit.php', ['id' => $cm->id]);
$PAGE->set_title(format_string($ocrsubmission->name) . ': ' . get_string('submitdocument', 'mod_ocrsubmission'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$viewurl = new moodle_url('/mod/ocrsubmission/view.php', ['id' => $cm->id]);

// Handle form submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();

    // Validate uploaded file.
    if (empty($_FILES['submissionfile']['name'])) {
        redirect($viewurl, get_string('nofilesubmitted', 'mod_ocrsubmission'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $uploadedfile = $_FILES['submissionfile'];
    $originalname = clean_filename($uploadedfile['name']);
    $ext = strtolower(pathinfo($originalname, PATHINFO_EXTENSION));

    $allowedexts = ['jpg', 'jpeg', 'pdf'];
    if (!in_array($ext, $allowedexts)) {
        redirect($viewurl, get_string('filetypeerror', 'mod_ocrsubmission'), null, \core\output\notification::NOTIFY_ERROR);
    }

    if ($uploadedfile['error'] !== UPLOAD_ERR_OK) {
        redirect($viewurl, get_string('error'), null, \core\output\notification::NOTIFY_ERROR);
    }

    // Check if student already has a submission.
    $existing = $DB->get_record('ocrsubmission_submissions', [
        'ocrsubmissionid' => $ocrsubmission->id,
        'userid'          => $USER->id,
    ]);

    $isnew = !$existing;

    if ($existing) {
        // Update existing submission.
        $submission = $existing;
        $submission->status        = 'pending';
        $submission->ocr_text      = null;
        $submission->error_message = null;
        $submission->timemodified  = time();
        $DB->update_record('ocrsubmission_submissions', $submission);
    } else {
        // Create new submission.
        $submission = new stdClass();
        $submission->ocrsubmissionid = $ocrsubmission->id;
        $submission->userid          = $USER->id;
        $submission->status          = 'pending';
        $submission->ocr_text        = null;
        $submission->error_message   = null;
        $submission->timecreated     = time();
        $submission->timemodified    = time();
        $submission->id = $DB->insert_record('ocrsubmission_submissions', $submission);
    }

    // Store the file in Moodle's file storage.
    $fs = get_file_storage();

    // Delete any previously uploaded file for this submission.
    $fs->delete_area_files($context->id, 'mod_ocrsubmission', 'submission', $submission->id);

    $filerecord = [
        'contextid' => $context->id,
        'component' => 'mod_ocrsubmission',
        'filearea'  => 'submission',
        'itemid'    => $submission->id,
        'filepath'  => '/',
        'filename'  => $originalname,
        'userid'    => $USER->id,
        'timecreated'  => time(),
        'timemodified' => time(),
    ];

    // Detect MIME type.
    $mimetype = mime_content_type($uploadedfile['tmp_name']);
    if (!in_array($mimetype, ['image/jpeg', 'application/pdf'])) {
        // Fallback: map by extension.
        $mimetypes = [
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'pdf'  => 'application/pdf',
        ];
        $mimetype = $mimetypes[$ext] ?? 'application/octet-stream';
    }
    $filerecord['mimetype'] = $mimetype;

    $storedfile = $fs->create_file_from_pathname($filerecord, $uploadedfile['tmp_name']);

    // Queue the OCR adhoc task.
    $task = new \mod_ocrsubmission\task\process_ocr();
    $task->set_custom_data(['submissionid' => $submission->id]);
    \core\task\manager::queue_adhoc_task($task, true);

    // Fire event.
    $event = \mod_ocrsubmission\event\submission_uploaded::create([
        'objectid' => $submission->id,
        'context'  => $context,
    ]);
    $event->trigger();

    $message = $isnew
        ? get_string('submissionsuccess', 'mod_ocrsubmission')
        : get_string('submissionupdated', 'mod_ocrsubmission');

    redirect($viewurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Show the upload form.
echo $OUTPUT->header();
echo $OUTPUT->heading(format_string($ocrsubmission->name));
echo $OUTPUT->heading(get_string('submitdocument', 'mod_ocrsubmission'), 3);

if ($ocrsubmission->intro) {
    echo $OUTPUT->box(
        format_module_intro('ocrsubmission', $ocrsubmission, $cm->id),
        'generalbox mod_introbox'
    );
}

// Check for existing submission and show status.
$existingsubmission = $DB->get_record('ocrsubmission_submissions', [
    'ocrsubmissionid' => $ocrsubmission->id,
    'userid'          => $USER->id,
]);

if ($existingsubmission) {
    echo $OUTPUT->notification(
        get_string('ocrstatus', 'mod_ocrsubmission') . ': '
        . get_string('ocrstatus_' . $existingsubmission->status, 'mod_ocrsubmission'),
        'info'
    );
}

$formurl = new moodle_url('/mod/ocrsubmission/submit.php', ['id' => $cm->id]);

echo html_writer::start_tag('form', [
    'method'  => 'post',
    'action'  => $formurl->out(false),
    'enctype' => 'multipart/form-data',
    'class'   => 'ocrsubmission-upload-form',
]);

echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

echo html_writer::start_div('mb-3');
echo html_writer::tag('label', get_string('submitdocument', 'mod_ocrsubmission'), [
    'for'   => 'submissionfile',
    'class' => 'form-label fw-bold',
]);
echo html_writer::tag(
    'p',
    get_string('submitdocument_help', 'mod_ocrsubmission'),
    ['class' => 'text-muted small']
);
echo html_writer::empty_tag('input', [
    'type'   => 'file',
    'name'   => 'submissionfile',
    'id'     => 'submissionfile',
    'class'  => 'form-control',
    'accept' => '.jpg,.jpeg,.pdf',
]);
echo html_writer::end_div();

echo html_writer::tag('button', get_string('submitfile', 'mod_ocrsubmission'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
]);

echo html_writer::tag(
    'a',
    get_string('cancel'),
    [
        'href'  => $viewurl->out(false),
        'class' => 'btn btn-secondary ms-2',
    ]
);

echo html_writer::end_tag('form');

echo $OUTPUT->footer();
