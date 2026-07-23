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
 * The admin cockpit viewed event.
 *
 * Modelled directly on report_security\event\report_viewed (same
 * situation: a report page being viewed, with no associated database
 * record) - crud 'r', edulevel 'other', no objecttable/objectid at all.
 *
 * Shared by the main dashboard and its two drill-down pages rather than
 * having three near-identical event classes - which specific page is
 * recorded via the 'page' key in 'other' (defaulting to index.php, so
 * existing call sites that never set it keep working), used by
 * get_description() and get_url() below.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit\event;

/**
 * Event triggered when the admin cockpit page, or one of its drill-down
 * pages, is viewed.
 */
class dashboard_viewed extends \core\event\base {
    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_OTHER;
    }

    /**
     * Returns the localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventdashboardviewed', 'local_admincockpit');
    }

    /**
     * Returns a description of what happened.
     *
     * @return string
     */
    public function get_description() {
        $page = $this->other['page'] ?? 'index.php';
        return "The user with id '$this->userid' viewed the admin cockpit page '$page'.";
    }

    /**
     * Returns the relevant URL.
     *
     * @return \core\url
     */
    public function get_url() {
        $page = $this->other['page'] ?? 'index.php';
        return new \core\url('/local/admincockpit/' . $page);
    }
}
