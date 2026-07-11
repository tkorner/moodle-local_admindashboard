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
 * Global, site-wide user metrics for the admin dashboard.
 *
 * Decision (SPEC section 3, "Nutzer gesamt"): suspended accounts ARE counted
 * as part of "total users". The SPEC defines this metric only as "(nicht
 * gelöschte) Nutzerkonten" - suspension is a reversible admin action that
 * blocks login, not account removal, and a suspended account still needs
 * administering (possible reactivation or cleanup). Excluding it would
 * understate how many accounts actually exist to manage. This mirrors core's
 * own base filter for "all users" (see
 * user/classes/reportbuilder/datasource/users.php), where suspended is a
 * separate, optional filter column rather than part of the base condition.
 * Revisit if this turns out to be the wrong call for this instance.
 *
 * Every count here also excludes the site guest account ($CFG->siteguest)
 * and any account belonging to a remote MNet host
 * (mnethostid != $CFG->mnet_localhost_id), for the same reason core excludes
 * them from its own user listings: neither is a "real" locally managed
 * account.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admindashboard\metrics;

/**
 * Global user metrics: total, recently active, and new-in-period accounts.
 */
class user_metrics {
    /**
     * Computes the three global user metrics from SPEC section 3.
     *
     * @param int $timerangedays length of the "new in period" window, in days
     * @return \stdClass with totalusers, activeusers (lastaccess in the last
     *         4 weeks), and newusers (timecreated within $timerangedays)
     */
    public static function get_metrics(int $timerangedays): \stdClass {
        $result = new \stdClass();
        $result->totalusers = self::count_total_users();
        $result->activeusers = self::count_recently_active_users();
        $result->newusers = self::count_new_users($timerangedays);
        return $result;
    }

    /**
     * Counts non-deleted, locally managed, non-guest user accounts.
     *
     * @return int
     */
    private static function count_total_users(): int {
        global $DB, $CFG;

        return $DB->count_records_select(
            'user',
            'deleted = 0 AND id != :guestid AND mnethostid = :mnethostid',
            [
                'guestid' => $CFG->siteguest,
                'mnethostid' => $CFG->mnet_localhost_id,
            ]
        );
    }

    /**
     * Counts non-deleted, locally managed, non-guest accounts with a
     * lastaccess timestamp within the last 4 weeks.
     *
     * @return int
     */
    private static function count_recently_active_users(): int {
        global $DB, $CFG;

        return $DB->count_records_select(
            'user',
            'deleted = 0 AND id != :guestid AND mnethostid = :mnethostid AND lastaccess >= :since',
            [
                'guestid' => $CFG->siteguest,
                'mnethostid' => $CFG->mnet_localhost_id,
                'since' => time() - (4 * WEEKSECS),
            ]
        );
    }

    /**
     * Counts non-deleted, locally managed, non-guest accounts created within
     * the given number of days.
     *
     * @param int $timerangedays
     * @return int
     */
    private static function count_new_users(int $timerangedays): int {
        global $DB, $CFG;

        return $DB->count_records_select(
            'user',
            'deleted = 0 AND id != :guestid AND mnethostid = :mnethostid AND timecreated >= :since',
            [
                'guestid' => $CFG->siteguest,
                'mnethostid' => $CFG->mnet_localhost_id,
                'since' => time() - ($timerangedays * DAYSECS),
            ]
        );
    }
}
