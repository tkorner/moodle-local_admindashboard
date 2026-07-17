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
 * Matches system-wide cohorts to top-level course categories via idnumber.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard;

/**
 * A "school" is a system-wide cohort and a top-level course category that
 * share the same idnumber. Matching is by idnumber only, never by name.
 */
class school_matcher {
    /**
     * Default value of the 'groupinglabel' setting (Schritt 9) - what to call a "school" in the
     * dashboard UI before an admin customises it. Kept here rather than duplicated as a literal in
     * both settings.php and dashboard_page.php, which used to be able to drift independently.
     */
    public const DEFAULT_GROUPING_LABEL = 'Schule';

    /**
     * Matches system-wide cohorts against top-level course categories by idnumber.
     *
     * @return \stdClass with three properties, each an array of stdClass rows:
     *         - matched: idnumber, cohortid, cohortname, categoryid, categoryname
     *         - cohortonly: idnumber, cohortid, cohortname
     *         - categoryonly: idnumber, categoryid, categoryname
     */
    public static function get_matches(): \stdClass {
        $cohorts = self::get_system_cohorts();
        $categories = self::get_toplevel_categories();

        $result = new \stdClass();
        $result->matched = [];
        $result->cohortonly = [];
        $result->categoryonly = [];

        foreach ($cohorts as $idnumber => $cohort) {
            if (isset($categories[$idnumber])) {
                $category = $categories[$idnumber];
                $result->matched[$idnumber] = (object) [
                    'idnumber' => $idnumber,
                    'cohortid' => $cohort->id,
                    'cohortname' => $cohort->name,
                    'categoryid' => $category->id,
                    'categoryname' => $category->name,
                ];
            } else {
                $result->cohortonly[$idnumber] = (object) [
                    'idnumber' => $idnumber,
                    'cohortid' => $cohort->id,
                    'cohortname' => $cohort->name,
                ];
            }
        }

        foreach ($categories as $idnumber => $category) {
            if (!isset($cohorts[$idnumber])) {
                $result->categoryonly[$idnumber] = (object) [
                    'idnumber' => $idnumber,
                    'categoryid' => $category->id,
                    'categoryname' => $category->name,
                ];
            }
        }

        return $result;
    }

    /**
     * Fetches all system-wide cohorts with a non-empty idnumber.
     *
     * @return array of stdClass (id, idnumber, name), keyed by idnumber
     */
    private static function get_system_cohorts(): array {
        global $DB;

        $records = $DB->get_records_select(
            'cohort',
            "contextid = :contextid AND idnumber IS NOT NULL AND idnumber != ''",
            ['contextid' => \core\context\system::instance()->id],
            '',
            'id, idnumber, name'
        );

        return self::key_by_idnumber($records);
    }

    /**
     * Fetches all top-level course categories with a non-empty idnumber.
     *
     * @return array of stdClass (id, idnumber, name), keyed by idnumber
     */
    private static function get_toplevel_categories(): array {
        global $DB;

        $records = $DB->get_records_select(
            'course_categories',
            "parent = 0 AND idnumber IS NOT NULL AND idnumber != ''",
            [],
            '',
            'id, idnumber, name'
        );

        return self::key_by_idnumber($records);
    }

    /**
     * Re-keys a recordset (currently keyed by id) by idnumber.
     *
     * @param array $records stdClass rows with an idnumber property
     * @return array same rows, keyed by idnumber
     */
    private static function key_by_idnumber(array $records): array {
        $keyed = [];
        foreach ($records as $record) {
            $keyed[$record->idnumber] = $record;
        }
        return $keyed;
    }
}
