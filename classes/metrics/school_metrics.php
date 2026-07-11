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
 * Per-school metrics for the admin dashboard (SPEC section 3, "Pro Schule").
 *
 * A school is identified by a cohort id and a top-level category id, both
 * resolved beforehand via school_matcher - this class does not do any
 * idnumber matching itself, it only needs the two ids.
 *
 * Confirmed assumption (SPEC section 8, item 1): "Kurszahl" and "Neue Kurse"
 * count courses in the category INCLUDING all subcategories, not just
 * courses directly in the top-level category, but without breaking the
 * total down per subcategory. Implemented via category.path prefix
 * matching, the same approach core itself uses to find subcategories (see
 * \core_course_category::get_children() in course/classes/category.php).
 * Revisit if a school ever wants subcategories excluded or reported
 * separately.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\metrics;

/**
 * Membership and course metrics for a single school (cohort + category pair).
 */
class school_metrics {
    /**
     * Computes the five per-school metrics from SPEC section 3.
     *
     * @param int $cohortid the school's cohort id
     * @param int $categoryid the school's top-level category id
     * @param int $timerangedays length of the "new in period" window, in days
     * @return \stdClass with membercount, newmembers, activemembers,
     *         coursecount, and newcourses
     */
    public static function get_metrics(int $cohortid, int $categoryid, int $timerangedays): \stdClass {
        $result = new \stdClass();
        $result->membercount = self::count_members($cohortid);
        $result->newmembers = self::count_new_members($cohortid, $timerangedays);
        $result->activemembers = self::count_active_members($cohortid);
        $result->coursecount = self::count_courses($categoryid);
        $result->newcourses = self::count_new_courses($categoryid, $timerangedays);
        return $result;
    }

    /**
     * Counts the cohort's members.
     *
     * @param int $cohortid
     * @return int
     */
    private static function count_members(int $cohortid): int {
        global $DB;

        return $DB->count_records('cohort_members', ['cohortid' => $cohortid]);
    }

    /**
     * Counts cohort members added within the given number of days.
     *
     * @param int $cohortid
     * @param int $timerangedays
     * @return int
     */
    private static function count_new_members(int $cohortid, int $timerangedays): int {
        global $DB;

        return $DB->count_records_select(
            'cohort_members',
            'cohortid = :cohortid AND timeadded >= :since',
            [
                'cohortid' => $cohortid,
                'since' => time() - ($timerangedays * DAYSECS),
            ]
        );
    }

    /**
     * Counts cohort members whose user account has a lastaccess timestamp
     * within the last 4 weeks.
     *
     * @param int $cohortid
     * @return int
     */
    private static function count_active_members(int $cohortid): int {
        global $DB;

        $sql = "SELECT COUNT(*)
                  FROM {cohort_members} cm
                  JOIN {user} u ON u.id = cm.userid
                 WHERE cm.cohortid = :cohortid
                   AND u.deleted = 0
                   AND u.lastaccess >= :since";

        return $DB->count_records_sql($sql, [
            'cohortid' => $cohortid,
            'since' => time() - (4 * WEEKSECS),
        ]);
    }

    /**
     * Counts courses in the category, including subcategories.
     *
     * @param int $categoryid
     * @return int
     */
    private static function count_courses(int $categoryid): int {
        global $DB;

        [$categoryssql, $categoryparams] = self::category_and_subcategories_sql($categoryid);

        return $DB->count_records_select('course', "category IN ($categoryssql)", $categoryparams);
    }

    /**
     * Counts courses in the category (including subcategories) created
     * within the given number of days.
     *
     * @param int $categoryid
     * @param int $timerangedays
     * @return int
     */
    private static function count_new_courses(int $categoryid, int $timerangedays): int {
        global $DB;

        [$categoryssql, $categoryparams] = self::category_and_subcategories_sql($categoryid);

        return $DB->count_records_select(
            'course',
            "category IN ($categoryssql) AND timecreated >= :since",
            $categoryparams + ['since' => time() - ($timerangedays * DAYSECS)]
        );
    }

    /**
     * Builds a subquery (and its params) selecting the ids of the given
     * category and all of its descendants, based on category.path.
     *
     * @param int $categoryid
     * @return array{0: string, 1: array} [subquery SQL, params]
     */
    private static function category_and_subcategories_sql(int $categoryid): array {
        global $DB;

        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid], MUST_EXIST);
        $likepath = $DB->sql_like('path', ':pathprefix');

        $sql = "SELECT id FROM {course_categories} WHERE id = :topcategoryid OR $likepath";
        $params = [
            'topcategoryid' => $categoryid,
            'pathprefix' => $DB->sql_like_escape($path) . '/%',
        ];

        return [$sql, $params];
    }
}
