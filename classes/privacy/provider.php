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
 * Privacy API implementation for the landingocractivity module.
 *
 * @package   mod_landingocractivity
 * @copyright 2024, LandingAI OCR Submission
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_landingocractivity\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\helper;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy Subsystem implementation for mod_landingocractivity.
 *
 * @package    mod_landingocractivity
 * @copyright  2024, LandingAI OCR Submission
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Return the fields which contain personal data.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'landingocractivity_submissions',
            [
                'userid'      => 'privacy:metadata:landingocractivity_submissions:userid',
                'ocr_text'    => 'privacy:metadata:landingocractivity_submissions:ocr_text',
                'status'      => 'privacy:metadata:landingocractivity_submissions:status',
                'timecreated' => 'privacy:metadata:landingocractivity_submissions:timecreated',
            ],
            'privacy:metadata:landingocractivity_submissions'
        );

        $collection->add_database_table(
            'landingocractivity_grades',
            [
                'userid'     => 'privacy:metadata:landingocractivity_grades:userid',
                'grade'      => 'privacy:metadata:landingocractivity_grades:grade',
                'feedback'   => 'privacy:metadata:landingocractivity_grades:feedback',
                'grader'     => 'privacy:metadata:landingocractivity_grades:grader',
                'timegraded' => 'privacy:metadata:landingocractivity_grades:timegraded',
            ],
            'privacy:metadata:landingocractivity_grades'
        );

        $collection->add_external_location_link(
            'landingai_api',
            [
                'document' => 'privacy:metadata:landingai_api:document',
            ],
            'privacy:metadata:landingai_api'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'landingocractivity'
                  JOIN {landingocractivity_submissions} os ON os.landingocractivityid = cm.instance
                 WHERE os.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        $sql = "SELECT ctx.id
                  FROM {context} ctx
                  JOIN {course_modules} cm ON cm.id = ctx.instanceid AND ctx.contextlevel = :contextlevel
                  JOIN {modules} m ON m.id = cm.module AND m.name = 'landingocractivity'
                  JOIN {landingocractivity_grades} og ON og.landingocractivityid = cm.instance
                 WHERE og.userid = :userid";
        $contextlist->add_from_sql($sql, ['contextlevel' => CONTEXT_MODULE, 'userid' => $userid]);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();

        if (!$context instanceof \context_module) {
            return;
        }

        $params = [
            'contextid'    => $context->id,
            'contextlevel' => CONTEXT_MODULE,
        ];

        $sql = "SELECT os.userid
                  FROM {landingocractivity_submissions} os
                  JOIN {course_modules} cm ON cm.instance = os.landingocractivityid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);

        $sql = "SELECT og.userid
                  FROM {landingocractivity_grades} og
                  JOIN {course_modules} cm ON cm.instance = og.landingocractivityid
                  JOIN {context} ctx ON ctx.instanceid = cm.id AND ctx.contextlevel = :contextlevel
                 WHERE ctx.id = :contextid";
        $userlist->add_from_sql('userid', $sql, $params);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('landingocractivity', $context->instanceid);
            if (!$cm) {
                continue;
            }

            // Export submission.
            $submission = $DB->get_record('landingocractivity_submissions', [
                'landingocractivityid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($submission) {
                $data = (object) [
                    'status'      => $submission->status,
                    'ocr_text'    => $submission->ocr_text,
                    'timecreated' => transform::datetime($submission->timecreated),
                    'timemodified' => transform::datetime($submission->timemodified),
                ];
                writer::with_context($context)->export_data(['submission'], $data);

                // Export associated files.
                writer::with_context($context)->export_area_files(
                    ['submission'],
                    'mod_landingocractivity',
                    'submission',
                    $submission->id
                );
            }

            // Export grade/feedback.
            $grade = $DB->get_record('landingocractivity_grades', [
                'landingocractivityid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($grade) {
                $data = (object) [
                    'grade'       => $grade->grade,
                    'feedback'    => $grade->feedback,
                    'timegraded'  => transform::datetime($grade->timegraded),
                ];
                writer::with_context($context)->export_data(['grade'], $data);
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('landingocractivity', $context->instanceid);
        if (!$cm) {
            return;
        }

        $submissions = $DB->get_records('landingocractivity_submissions', ['landingocractivityid' => $cm->instance]);
        foreach ($submissions as $submission) {
            $fs = get_file_storage();
            $fs->delete_area_files($context->id, 'mod_landingocractivity', 'submission', $submission->id);
        }

        $DB->delete_records('landingocractivity_submissions', ['landingocractivityid' => $cm->instance]);
        $DB->delete_records('landingocractivity_grades', ['landingocractivityid' => $cm->instance]);
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_MODULE) {
                continue;
            }

            $cm = get_coursemodule_from_id('landingocractivity', $context->instanceid);
            if (!$cm) {
                continue;
            }

            $submission = $DB->get_record('landingocractivity_submissions', [
                'landingocractivityid' => $cm->instance,
                'userid' => $userid,
            ]);
            if ($submission) {
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_landingocractivity', 'submission', $submission->id);
                $DB->delete_records('landingocractivity_submissions', ['id' => $submission->id]);
            }

            $DB->delete_records('landingocractivity_grades', [
                'landingocractivityid' => $cm->instance,
                'userid' => $userid,
            ]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel != CONTEXT_MODULE) {
            return;
        }

        $cm = get_coursemodule_from_id('landingocractivity', $context->instanceid);
        if (!$cm) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);

        $submissions = $DB->get_records_select(
            'landingocractivity_submissions',
            "landingocractivityid = :instanceid AND userid {$insql}",
            array_merge(['instanceid' => $cm->instance], $inparams)
        );

        $fs = get_file_storage();
        foreach ($submissions as $submission) {
            $fs->delete_area_files($context->id, 'mod_landingocractivity', 'submission', $submission->id);
        }

        $DB->delete_records_select(
            'landingocractivity_submissions',
            "landingocractivityid = :instanceid AND userid {$insql}",
            array_merge(['instanceid' => $cm->instance], $inparams)
        );

        $DB->delete_records_select(
            'landingocractivity_grades',
            "landingocractivityid = :instanceid AND userid {$insql}",
            array_merge(['instanceid' => $cm->instance], $inparams)
        );
    }
}
