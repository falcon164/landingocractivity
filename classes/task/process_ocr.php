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
    const API_ENDPOINT = 'https://api.va.landing.ai/v1/ade/parse';

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

        // Get the ocrsubmission instance.
        $ocrsubmission = $DB->get_record('ocrsubmission', ['id' => $submission->ocrsubmissionid]);
        if (!$ocrsubmission) {
            mtrace("process_ocr task: ocrsubmission instance {$submission->ocrsubmissionid} not found.");
            $this->mark_error($submission, 'Activity configuration not found.');
            return;
        }

        // Get the API key from the global plugin setting.
        // Administrators configure this once via Site administration > Plugins > Activity modules >
        // OCR Submission. For backward compatibility, if the global key is not set, the key stored
        // on the activity instance (from a prior version of the plugin) is used as a fallback.
        // Once all existing instances have been superseded by the global setting, the instance-level
        // fallback can be removed along with the 'apikey' column in the ocrsubmission table.
        $apikey = get_config('mod_ocrsubmission', 'apikey');
        if (empty($apikey)) {
            // Fallback: use the legacy instance-level API key if the global key is not configured.
            $apikey = $ocrsubmission->apikey ?? '';
            if (!empty($apikey)) {
                mtrace(
                    "process_ocr task: global API key is not set; using legacy instance-level key " .
                    "for ocrsubmission {$ocrsubmission->id}. Please configure the key in the plugin settings."
                );
            }
        }

        if (empty($apikey)) {
            mtrace("process_ocr task: no API key configured for ocrsubmission {$ocrsubmission->id}.");
            $this->mark_error($submission, 'LandingAI API key not configured.');
            return;
        }
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
            $ocrtext = $this->call_landingai_api($file, $apikey);
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
     * @throws \RuntimeException If the API call fails.
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
                        'Authorization: Bearer ' . $apikey,
                    ],
                ]
            );

            $httpcode = $curl->get_info()['http_code'] ?? 0;

            if ($httpcode !== 200) {
                throw new \RuntimeException(
                    "LandingAI API returned HTTP {$httpcode}: {$response}"
                );
            }

            $decoded = json_decode($response, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \RuntimeException(
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
     * @throws \RuntimeException If text cannot be extracted.
     */
    protected function extract_text_from_response(array $response): string {
        // LandingAI ADE API (api.va.landing.ai/v1/ade/parse) returns a top-level "markdown" field.
        if (!empty($response['markdown'])) {
            return $this->strip_heading_anchors((string) $response['markdown']);
        }

        // Fallback: try chunks array (each chunk has a "markdown" or "text" field).
        if (!empty($response['chunks']) && is_array($response['chunks'])) {
            $texts = [];
            foreach ($response['chunks'] as $chunk) {
                if (!empty($chunk['markdown'])) {
                    $texts[] = (string) $chunk['markdown'];
                } elseif (!empty($chunk['text'])) {
                    $texts[] = (string) $chunk['text'];
                }
            }
            if (!empty($texts)) {
                return $this->strip_heading_anchors(implode("\n\n", $texts));
            }
        }

        // Fallback: legacy "data" wrapper from older API versions.
        if (!empty($response['data'])) {
            $data = $response['data'];

            if (!empty($data['markdown'])) {
                return $this->strip_heading_anchors((string) $data['markdown']);
            }

            if (!empty($data['chunks']) && is_array($data['chunks'])) {
                $texts = [];
                foreach ($data['chunks'] as $chunk) {
                    if (!empty($chunk['text'])) {
                        $texts[] = (string) $chunk['text'];
                    }
                }
                if (!empty($texts)) {
                    return $this->strip_heading_anchors(implode("\n\n", $texts));
                }
            }

            if (!empty($data['text'])) {
                return $this->strip_heading_anchors((string) $data['text']);
            }
        }

        // Fallback: try top-level "result" or "text".
        if (!empty($response['result'])) {
            return $this->strip_heading_anchors((string) $response['result']);
        }

        if (!empty($response['text'])) {
            return $this->strip_heading_anchors((string) $response['text']);
        }

        // If none of the above, return a JSON dump for debugging.
        throw new \RuntimeException(
            'Unable to extract text from LandingAI API response. Response: ' . json_encode($response)
        );
    }

    /**
     * Remove empty HTML anchor tags that LandingAI inserts before headings.
     *
     * The LandingAI API often returns markdown with anchor tags such as
     * <a id="..."></a> immediately before heading lines. These serve as
     * internal navigation targets but are unwanted in plain-text output.
     *
     * @param string $text The raw text to clean.
     * @return string The text with heading anchor tags removed.
     */
    protected function strip_heading_anchors(string $text): string {
        // Remove <a ...></a> tags that contain no visible content (including whitespace-only).
        $text = preg_replace('/<a[^>]*>\s*<\/a>/i', '', $text);
        return $text;
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
