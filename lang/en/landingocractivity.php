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
 * English strings for landingocractivity.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// General.
$string['pluginname']          = 'OCR Submission';
$string['modulename']          = 'OCR Submission';
$string['modulenameplural']    = 'OCR Submissions';
$string['modulename_help']     = 'The OCR Submission activity allows students to upload a scanned document (JPG or PDF) which is processed by LandingAI\'s Document Analysis API to extract text. Teachers can view the extracted text and provide TinyMCE-based feedback and grades.';
$string['pluginadministration'] = 'OCR Submission administration';

// Activity name.
$string['landingocractivityname']      = 'Activity name';
$string['landingocractivityname_help'] = 'Give this OCR Submission activity a descriptive name.';

// Settings sections.
$string['landingaisettings']   = 'LandingAI API settings';
$string['submissionsettings']  = 'Submission settings';

// API key.
$string['apikey']              = 'LandingAI API key';
$string['apikey_help']         = 'Enter the API key obtained from LandingAI (https://landing.ai). This key is used to authenticate requests to the Document Analysis API.';
$string['apikeyerror']         = 'An API key is required.';

// File area.
$string['filearea_submission'] = 'Submission files';

// Submission page.
$string['submitdocument']      = 'Submit document';
$string['submitdocument_help'] = 'Upload a scanned document in JPG, JPEG, or PDF format.';
$string['submitfile']          = 'Upload document';
$string['nofilesubmitted']     = 'No file has been uploaded yet.';
$string['submissionsuccess']   = 'Your document has been uploaded and queued for OCR processing.';
$string['submissionupdated']   = 'Your submission has been updated and queued for re-processing.';
$string['filetypeerror']       = 'Only JPG, JPEG, or PDF files are accepted.';
$string['pleasewait']          = 'Your document is being processed. Please check back shortly.';
$string['ocrprocessing']       = 'OCR Processing';
$string['ocrstatus_pending']   = 'Pending';
$string['ocrstatus_processing'] = 'Processing';
$string['ocrstatus_complete']  = 'Complete';
$string['ocrstatus_error']     = 'Error';
$string['ocrstatus']           = 'OCR status';
$string['ocrtext']             = 'Extracted text';
$string['ocrtextnotavailable'] = 'OCR text is not yet available.';
$string['ocrerror']            = 'OCR processing error';
$string['ocrerrormessage']     = 'Error: {$a}';

// View page.
$string['yoursubmission']      = 'Your submission';
$string['nosubmission']        = 'You have not submitted a document yet.';
$string['viewsubmissions']     = 'View all submissions';
$string['submittedby']         = 'Submitted by';
$string['submissiondate']      = 'Submission date';
$string['viewdocument']        = 'View uploaded document';
$string['downloadfile']        = 'Download file';
$string['resubmit']            = 'Resubmit';

// Grading.
$string['grade']               = 'Grade';
$string['gradefor']            = 'Grade for {$a}';
$string['feedback']            = 'Feedback';
$string['feedback_help']       = 'Enter feedback for the student. You can use formatting options.';
$string['savefeedback']        = 'Save feedback';
$string['gradesaved']          = 'Grade and feedback saved successfully.';
$string['gradeerror']          = 'The grade must be between 0 and {$a}.';
$string['notgraded']           = 'Not graded';
$string['gradedby']            = 'Graded by {$a}';
$string['gradedon']            = 'Graded on {$a}';
$string['nofeedback']          = 'No feedback provided.';

// Submission list (teacher view).
$string['submissionlist']      = 'Student submissions';
$string['nostudentsubmissions'] = 'No students have submitted yet.';
$string['student']             = 'Student';
$string['status']              = 'Status';
$string['actions']             = 'Actions';
$string['viewgrade']           = 'View / Grade';

// Events.
$string['eventcoursemoduleviewed']   = 'OCR Submission activity viewed';
$string['eventsubmissionuploaded']   = 'Document submission uploaded';
$string['eventsubmissiongraded']     = 'Submission graded';

// Privacy.
$string['privacy:metadata:landingocractivity_submissions']           = 'Information about student submissions.';
$string['privacy:metadata:landingocractivity_submissions:userid']    = 'The ID of the student.';
$string['privacy:metadata:landingocractivity_submissions:ocr_text']  = 'The OCR-extracted text from the submitted document.';
$string['privacy:metadata:landingocractivity_submissions:status']    = 'The processing status of the submission.';
$string['privacy:metadata:landingocractivity_submissions:timecreated'] = 'The time the submission was created.';
$string['privacy:metadata:landingocractivity_grades']                = 'Information about grades and feedback given to students.';
$string['privacy:metadata:landingocractivity_grades:userid']         = 'The ID of the student who was graded.';
$string['privacy:metadata:landingocractivity_grades:grade']          = 'The grade given to the student.';
$string['privacy:metadata:landingocractivity_grades:feedback']       = 'The feedback provided to the student.';
$string['privacy:metadata:landingocractivity_grades:grader']         = 'The ID of the teacher who graded.';
$string['privacy:metadata:landingocractivity_grades:timegraded']     = 'The time at which the student was graded.';
$string['privacy:metadata:landingai_api']                       = 'Document text is sent to the LandingAI Document Analysis API for OCR processing.';
$string['privacy:metadata:landingai_api:document']              = 'The uploaded document (image or PDF) is sent to LandingAI for text extraction.';
