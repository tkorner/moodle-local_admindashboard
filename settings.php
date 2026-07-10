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
 * Registers the dashboard report page and the plugin configuration page.
 *
 * The dashboard itself is an admin_externalpage (Site administration >
 * Reports) because it renders a live view, not configuration values.
 * A separate admin_settingpage under Local plugins holds the actual
 * configuration (time range, active school codes) - populated in a
 * later implementation step, not part of this scaffold.
 *
 * @package   local_admindashboard
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage(
        'local_admindashboard',
        get_string('pluginname', 'local_admindashboard'),
        new moodle_url('/local/admindashboard/index.php'),
        'local/admindashboard:view'
    ));

    $settings = new admin_settingpage(
        'local_admindashboard_settings',
        get_string('pluginname', 'local_admindashboard')
    );
    $ADMIN->add('localplugins', $settings);
}
