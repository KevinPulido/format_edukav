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

namespace format_edukav\output\courseformat\content\cm;

use core_courseformat\output\local\content\cm\completion as completion_base;
use stdClass;

/**
 * Completion renderer for edukav.
 *
 * Keeps the completion dropdown icon-only:
 * - empty checkbox for pending
 * - green check for completed
 */
class completion extends completion_base {

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output typically, the renderer that's calling this function
     * @return stdClass|null data context for a mustache template
     */
    public function export_for_template(\renderer_base $output): ?stdClass {
        $data = parent::export_for_template($output);

        if (empty($data) || empty($data->completiondialog) || !$data->istrackeduser) {
            return $data;
        }

        $buttoncontent = '';
        $buttonclasses = 'btn btn-sm dropdown-toggle icon-no-margin edukav-status-pill edukav-status-pending';

        if (!empty($data->overallcomplete)) {
            $buttoncontent = '<i class="fa fa-check text-success" aria-hidden="true"></i>'
                . '<span class="sr-only">' . get_string('completion_manual:done', 'core_course') . '</span>';
            $buttonclasses = 'btn btn-sm dropdown-toggle icon-no-margin edukav-status-pill edukav-status-complete';
        } else {
            $buttoncontent = '<i class="fa-regular fa-square" aria-hidden="true"></i>'
                . '<span class="sr-only">' . get_string('completion_manual:markdone', 'core_course') . '</span>';
        }

        if (is_array($data->completiondialog)) {
            $data->completiondialog['buttoncontent'] = $buttoncontent;
            $data->completiondialog['buttonclasses'] = $buttonclasses;
        } else {
            $data->completiondialog->buttoncontent = $buttoncontent;
            $data->completiondialog->buttonclasses = $buttonclasses;
        }

        return $data;
    }
}
