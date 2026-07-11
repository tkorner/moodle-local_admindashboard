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
 * Health signals for the admin dashboard (SPEC section 4).
 *
 * security_overview_summary() and cron_status() deliberately reuse existing
 * core infrastructure instead of re-implementing checks:
 * - Security checks: \core\check\manager::get_checks('security'), the same
 *   API report_security/index.php itself uses (new core\check\table(...)).
 *   Core defines 7 granular statuses (result::OK/INFO/NA/UNKNOWN/WARNING/
 *   ERROR/CRITICAL) but no 3-bucket "ok/warning/error" grouping, so this
 *   class provides that mapping itself (see bucket_for_status()) - OK/INFO/NA
 *   count as ok, WARNING/UNKNOWN as warning (core's own docblock for
 *   UNKNOWN literally recommends treating it as a warning), ERROR/CRITICAL
 *   as error.
 * - Cron status: admin/tool/task/classes/check/cronrunning.php (core's own
 *   "is cron running" check) shows the canonical last-run source is
 *   get_config('tool_task', 'lastcronstart'), not a derived value from
 *   task_log - a task_log MAX(timeend) would be misleading if cron ran but
 *   no task happened to be due. Failed-task counting uses task_log.result
 *   (0 = pass, 1 = fail per its install.xml comment).
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\metrics;

/**
 * Data hygiene and infrastructure health signals for the admin dashboard.
 */
class health_signals {
    /**
     * Finds email addresses shared by more than one non-deleted, locally
     * managed, non-guest user account.
     *
     * "Aktives Nutzerkonto" here uses the same convention as user_metrics:
     * not deleted, not guest, not a remote MNet account - see that class's
     * docblock for the rationale.
     *
     * @return \stdClass with count (number of duplicated email addresses)
     *         and details (array of stdClass: userid, email, fullname - one
     *         row per affected user, for the drill-down list)
     */
    public static function duplicate_emails(): \stdClass {
        global $DB, $CFG;

        $sql = "SELECT email, COUNT(*) AS usercount
                  FROM {user}
                 WHERE deleted = 0
                   AND id != :guestid
                   AND mnethostid = :mnethostid
                   AND email != ''
              GROUP BY email
                HAVING COUNT(*) > 1";
        $params = ['guestid' => $CFG->siteguest, 'mnethostid' => $CFG->mnet_localhost_id];
        $duplicategroups = $DB->get_records_sql($sql, $params);

        $result = new \stdClass();
        $result->details = [];

        if (!empty($duplicategroups)) {
            [$emailsql, $emailparams] = $DB->get_in_or_equal(array_keys($duplicategroups));
            $sql = "SELECT id, email, firstname, lastname
                      FROM {user}
                     WHERE deleted = 0
                       AND id != :guestid
                       AND mnethostid = :mnethostid
                       AND email $emailsql
                  ORDER BY email, id";
            $params = array_merge(
                ['guestid' => $CFG->siteguest, 'mnethostid' => $CFG->mnet_localhost_id],
                $emailparams
            );
            $users = $DB->get_records_sql($sql, $params);
            foreach ($users as $user) {
                $result->details[] = (object) [
                    'userid' => $user->id,
                    'email' => $user->email,
                    'fullname' => fullname($user),
                ];
            }
        }

        $result->count = count($duplicategroups);

        return $result;
    }

    /**
     * Finds courses with enddate = 0.
     *
     * The join against course_categories naturally excludes the site course
     * (category = 0, which has no matching category row) - intentional,
     * not an oversight: the front page is not a "course without an enddate"
     * in any actionable sense for this signal.
     *
     * @return \stdClass with count and details (array of stdClass: courseid,
     *         fullname, categoryname)
     */
    public static function courses_without_enddate(): \stdClass {
        global $DB;

        $sql = "SELECT c.id, c.fullname, cc.name AS categoryname
                  FROM {course} c
                  JOIN {course_categories} cc ON cc.id = c.category
                 WHERE c.enddate = 0
              ORDER BY c.fullname";
        $courses = $DB->get_records_sql($sql);

        $result = new \stdClass();
        $result->details = [];
        foreach ($courses as $course) {
            $result->details[] = (object) [
                'courseid' => $course->id,
                'fullname' => $course->fullname,
                'categoryname' => $course->categoryname,
            ];
        }
        $result->count = count($result->details);

        return $result;
    }

    /**
     * Aggregates the core security overview checks into a 3-state traffic
     * light. Reuses \core\check\manager - the same API report_security's own
     * page uses - rather than re-implementing any of the checks.
     *
     * @return \stdClass with ok, warning, and error counts
     */
    public static function security_overview_summary(): \stdClass {
        $result = new \stdClass();
        $result->ok = 0;
        $result->warning = 0;
        $result->error = 0;

        $checks = \core\check\manager::get_checks('security');
        foreach ($checks as $check) {
            $bucket = self::bucket_for_status($check->get_result()->get_status());
            $result->$bucket++;
        }

        return $result;
    }

    /**
     * Maps a core\check\result status onto this dashboard's 3-state
     * ok/warning/error traffic light.
     *
     * @param string $status one of the \core\check\result::* constants
     * @return string 'ok', 'warning', or 'error'
     */
    private static function bucket_for_status(string $status): string {
        switch ($status) {
            case \core\check\result::OK:
            case \core\check\result::INFO:
            case \core\check\result::NA:
                return 'ok';
            case \core\check\result::WARNING:
            case \core\check\result::UNKNOWN:
                return 'warning';
            case \core\check\result::ERROR:
            case \core\check\result::CRITICAL:
                return 'error';
            default:
                // Fail safe: an unrecognised status is surfaced as an error rather than silently hidden.
                return 'error';
        }
    }

    /**
     * Reads the core scheduled-task infrastructure for the last cron run
     * and recent task failures.
     *
     * @return \stdClass with lastrunat (unix timestamp, 0 if cron has never
     *         run) and failedtasks24h (count of task_log rows with
     *         result = 1 in the last 24 hours)
     */
    public static function cron_status(): \stdClass {
        global $DB;

        $result = new \stdClass();
        $result->lastrunat = (int) get_config('tool_task', 'lastcronstart');
        $result->failedtasks24h = $DB->count_records_select(
            'task_log',
            'result = :fail AND timestart >= :since',
            ['fail' => 1, 'since' => time() - DAYSECS]
        );

        return $result;
    }
}
