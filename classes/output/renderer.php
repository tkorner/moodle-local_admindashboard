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
 * Renderer for local_admincockpit.
 *
 * @package   local_admincockpit
 * @copyright 2026 Thomas Korner <thomas.korner@edu.zh.ch>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_admincockpit\output;

/**
 * Thin renderer: all data assembly lives in dashboard_page, not here.
 */
class renderer extends \core\output\plugin_renderer_base {
    /**
     * Renders the main dashboard page.
     *
     * @param dashboard_page $page
     * @return string
     */
    public function render_dashboard_page(dashboard_page $page): string {
        return $this->render_from_template('local_admincockpit/dashboard', $page->export_for_template($this));
    }
}
