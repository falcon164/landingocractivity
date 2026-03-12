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
 * Adhoc task to process OCR on a submitted document via LandingAI API.
 *
 * @package   mod_ocrsubmission
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_ocrsubmission\task;

use core\task\adhoc_task;

/**
 * Process OCR task - calls LandingAI Document Analysis API.
 *
 * @package    mod_ocrsubmission
 * @copyright  2024, LandingAI OCR Submission
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_ocr extends adhoc_task {

    /** @var string LandingAI API endpoint */
    const API_ENDPOINT = 'https://api.landing.ai/v1/tools/document-analysis';

    /**
     * Return the task's name as shown in admin screens.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('ocrprocessing', 'mod_ocrsubmission');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        if (empty($data->submissionid)) {
            mtrace('process_ocr task: missing submissionid in custom data.');
            return;
        }

        $submissionid = (int) $data->submissionid;
        $submission = $DB->get_record('ocrsubmission_submissions', ['id' => $submissionid]);

        if (!$submission) {
            mtrace("process_ocr task: submission {$submissionid} not found.");
            return;
        }

        // Get the ocrsubmission instance to retrieve the API key.
        $ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $submission->ocrsubmissionid]);
        if (!$ocrsubmission) {
            mtrace("process_ocr task: ocrsubmission instance {$submission->ocrsubmissionid} not found.");
            $this->mark_error($submission, 'Activity configuration not found.');
            return;
        }

        if (empty($ocrsubmission->apikey)) {
            mtrace("process_ocr task: no API key configured for ocrsubmission {$ocrsubmission->id}.");
            $this->mark_error($submission, 'LandingAI API key not configured.');
            return;
        }

        // Mark as processing.
        $DB->set_field('ocrsubmission_submissions', 'status', 'processing', ['id' => $submissionid]);

        // Get the stored file.
        $fs = get_file_storage();
        $cm = get_coursemodule_from_instance('ocrsubmission', $ocrsubmission->id, $ocrsubmission->course, false, MUST_EXIST);
        $context = \context_module::instance($cm->id);

        $files = $fs->get_area_files(
            $context->id,
            'mod_ocrsubmission',
            'submission',
            $submissionid,
            'id DESC',
            false
        );

        if (empty($files)) {
            mtrace("process_ocr task: no files found for submission {$submissionid}.");
            $this->mark_error($submission, 'No file found for this submission.');
            return;
        }

        $file = reset($files);

        // Perform the OCR API call.
        try {
            $ocrtext = $this->call_landingai_api($file, $ocrsubmission->apikey);
        } catch (\Exception $e) {
            mtrace("process_ocr task: API call failed for submission {$submissionid}: " . $e->getMessage());
            $this->mark_error($submission, $e->getMessage());
            return;
        }

        // Save the OCR result.
        $record = new \stdClass();
        $record->id           = $submissionid;
        $record->status       = 'complete';
        $record->ocr_text     = $ocrtext;
        $record->error_message = null;
        $record->timemodified = time();
        $DB->update_record('ocrsubmission_submissions', $record);

        mtrace("process_ocr task: submission {$submissionid} processed successfully.");
    }

    /**
     * Call the LandingAI Document Analysis API and return the extracted text.
     *
     * @param \stored_file $file The uploaded file.
     * @param string $apikey The LandingAI API key.
     * @return string The extracted text.
     * @throws \moodle_exception If the API call fails.
     */
    protected function call_landingai_api(\stored_file $file, string $apikey): string {
        // Write the file to a temp path for upload.
        $tmpfile = make_temp_directory('ocrsubmission') . '/' . $file->get_filename();
        $file->copy_content_to($tmpfile);

        $mimetype = $file->get_mimetype();
        $filename = $file->get_filename();

        try {
            $curl = new \curl();
            $curl->setopt([
                'CURLOPT_RETURNTRANSFER' => true,
                'CURLOPT_TIMEOUT'        => 120,
            ]);

            $response = $curl->post(
                self::API_ENDPOINT,
                [
                    'document' => curl_file_create($tmpfile, $mimetype, $filename),
                ],
                [
                    'CURLOPT_HTTPHEADER' => [
                        'Authorization: Basic ' . base64_encode($apikey . ':'),
                    ],
                ]
            );

            $httpcode = $curl->get_info()['http_code'] ?? 0;

            if ($httpcode !== 200) {
                throw new \moodle_exception(
                    'error',
                    'mod_ocrsubmission',
                    '',
                    "LandingAI API returned HTTP {$httpcode}: {$response}"
                );
            }

            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \moodle_exception(
                    'error',
                    'mod_ocrsubmission',
                    '',
                    'Invalid JSON response from LandingAI API.'
                );
            }

            return $this->extract_text_from_response($decoded);

        } finally {
            // Clean up temp file.
            if (file_exists($tmpfile)) {
                unlink($tmpfile);
            }
        }
    }

    /**
     * Extract the text content from the LandingAI API response.
     *
     * @param array $response Decoded API response.
     * @return string Extracted text.
     * @throws \moodle_exception If text cannot be extracted.
     */
    protected function extract_text_from_response(array $response): string {
        // LandingAI Document Analysis API returns data in the "data" key.
        // The structure may contain "chunks" with "text" fields, or a top-level "markdown" field.
        if (!empty($response['data'])) {
            $data = $response['data'];

            // Try to get markdown text.
            if (!empty($data['markdown'])) {
                return (string) $data['markdown'];
            }

            // Try chunks array.
            if (!empty($data['chunks']) && is_array($data['chunks'])) {
                $texts = [];
                foreach ($data['chunks'] as $chunk) {
                    if (!empty($chunk['text'])) {
                        $texts[] = (string) $chunk['text'];
                    }
                }
                if (!empty($texts)) {
                    return implode("\n\n", $texts);
                }
            }

            // Try text field directly.
            if (!empty($data['text'])) {
                return (string) $data['text'];
            }
        }

        // Fallback: try top-level "result" or "text".
        if (!empty($response['result'])) {
            return (string) $response['result'];
        }

        if (!empty($response['text'])) {
            return (string) $response['text'];
        }

        // If none of the above, return a JSON dump for debugging.
        throw new \moodle_exception(
            'error',
            'mod_ocrsubmission',
            '',
            'Unable to extract text from LandingAI API response. Response: ' . json_encode($response)
        );
    }

    /**
     * Mark a submission as having encountered an error.
     *
     * @param \stdClass $submission The submission record.
     * @param string $message The error message.
     */
    protected function mark_error(\stdClass $submission, string $message): void {
        global $DB;

        $record = new \stdClass();
        $record->id            = $submission->id;
        $record->status        = 'error';
        $record->error_message = $message;
        $record->timemodified  = time();
        $DB->update_record('ocrsubmission_submissions', $record);
    }
}
