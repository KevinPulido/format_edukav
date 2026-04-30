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
 * Course content renderer
 *
 * @package     format_cards
 * @copyright   2024 University of Essex
 * @author      John Maydew <jdmayd@essex.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_edukav\output\courseformat;

use coding_exception;
use format_edukav\versionable_template;
use local_edukav\service\partners_service;
use format_topics\output\courseformat\content as content_base;
use moodle_exception;
use renderer_base;

/**
 * Course content renderer
 *
 * @package     format_cards
 * @copyright   2024 University of Essex
 * @author      John Maydew <jdmayd@essex.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class content extends content_base {
    use versionable_template;

    /**
     * If the user is editing the page, just use the default renderer for format_topics
     * Otherwise, override the renderer to add our own sections onto the page
     *
     * @param renderer_base $renderer
     * @return string
     * @throws coding_exception
     */
    public function get_template_name(renderer_base $renderer): string {
        return "format_edukav/local/content";
    }

    /**
     * Export template data
     *
     * @param renderer_base $output
     * @return object
     * @throws moodle_exception
     */
    public function export_for_template(renderer_base $output) {
        global $PAGE,$DB;

        // Is this a single section page?
        $singlesection = $this->format->get_sectionnum();

        $this->hasaddsection = !$singlesection;
        

        $data = parent::export_for_template($output);


        $course = $this->format->get_course();
        $context = \context_course::instance($course->id);

        // Get enrolled students count.
        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        $students = get_role_users($studentrole->id, $context);
        $enrolledstudents = count($students);

        // Get course category name.
        $category = \core_course_category::get($course->category);
        $categoryName = $category->get_formatted_name();

        // Get course educators.
        $educators = $this->edukav_course_educators($course->id);
        $partnerdata = [];
        if (class_exists(partners_service::class)) {
            $partnerdata = partners_service::get_course_partner_branding($course->id);
        }
        $partner = [
            'id' => $partnerdata['id'] ?? 0,
            'name' => trim((string)($partnerdata['name'] ?? '')),
            'logo' => trim((string)($partnerdata['logo'] ?? '')),
            'brand_color' => trim((string)($partnerdata['brand_color'] ?? '')),
            'gradient' => trim((string)($partnerdata['gradient'] ?? '')),
            'style' => trim((string)($partnerdata['style'] ?? '')),
        ];

        $videourl = $this->format->normalize_video_url($this->format->get_format_option('banner_video'));

        $data->showcourseEdukav = !$singlesection;

        $data->coursesEdukav = [
            'fullname' => $course->fullname,
            'summary' => format_text($course->summary, $course->summaryformat),
            'enrolledcount' => $enrolledstudents,
            'categoryName' => format_string($categoryName),
            'startdate' => userdate($course->startdate, '%d %b %Y'),
            'educators' => $educators,
            'partner' => $partner,
            'video_url' => $videourl,
        ];

        // Add version variables.
        $this->add_version_variables($data);

        // Rather than rolling our own empty placeholder, we can just re-use the "no courses" template
        // from block_myoverview and change the text to be "No activities" instead.
        $data->nocoursesimg = $output->image_url('courses', 'block_myoverview')->out();

        $data->userisediting = $PAGE->user_is_editing();

        $data->subsectionsascards = $this->format->get_format_option("subsectionsascards") == FORMAT_EDUKAV_SUBSECTIONS_AS_CARDS;

        if (!$singlesection) {
            return $data;
        }

        if ($PAGE->user_is_editing()) {
            $data->initialsection = '';
        } else if ($this->format->get_format_option('section0') == FORMAT_EDUKAV_SECTION0_COURSEPAGE) {
            $data->initialsection = '';
        } else if (empty($data->initialsection)) {
            $section0 = new $this->sectionclass($this->format, $this->format->get_section(0));
            $data->initialsection = $section0->export_for_template($output);
        }

        $this->add_section_navigation($data, $output);
        

        return $data;
    }

    /**
     * Adds section navigation data to the template
     *
     * @param object $data Current template context
     * @param renderer_base $output Output renderer
     * @return void $data is modified directly
     */
    private function add_section_navigation(&$data, renderer_base $output): void {
        $singlesection = $this->format->get_sectionnum();

        if (!$singlesection) {
            return;
        }

        $navigationoption = $this->format->get_format_option('sectionnavigation');

        // Remove section navigation if it's set in the options.
        if ($navigationoption == FORMAT_EDUKAV_SECTIONNAVIGATION_NONE) {
            $data->sectionnavigation = false;
            $data->sectionselector = false;

            return;
        }

        $sectionnavigation = new $this->sectionnavigationclass($this->format, $singlesection);
        $sectionselector = new $this->sectionselectorclass($this->format, $sectionnavigation);

        // Add top navigation.
        switch ($navigationoption) {
            case FORMAT_EDUKAV_SECTIONNAVIGATION_TOP:
                $data->sectionnavigation = $sectionnavigation->export_for_template($output);
                $data->sectionselector = false;
                break;
            case FORMAT_EDUKAV_SECTIONNAVIGATION_BOTTOM:
                $data->sectionselector = $sectionselector->export_for_template($output);
                $data->sectionnavigation = false;
                break;
            default:
            case FORMAT_EDUKAV_SECTIONNAVIGATION_BOTH:
                $data->sectionnavigation = $sectionnavigation->export_for_template($output);
                $data->sectionselector = $sectionselector->export_for_template($output);
                break;
        }

        if ($data->sectionselector || $data->sectionnavigation) {
            $data->hasnavigation = true;
            $data->sectionreturn = $singlesection;
        }
    }
    function edukav_course_educators($courseid) {
        global $CFG, $DB;

        require_once($CFG->dirroot.'/user/lib.php');

        $educators = [];

        $context = \context_course::instance($courseid);

        // Obtener roles de profesor
        $roles = $DB->get_records_list('role', 'shortname', ['editingteacher', 'teacher']);

        foreach ($roles as $role) {

            $teachers = get_role_users(
                $role->id,
                $context,
                false,
                'u.id, u.firstname, u.lastname, u.picture, u.imagealt'
            );

            foreach ($teachers as $teacher) {

                $userpicture = new \user_picture($teacher);
                $userpicture->size = 100;

                $educators[] = [
                    'educator_name' => fullname($teacher),
                    'educator_profileurl' => (new \moodle_url('/user/profile.php', ['id' => $teacher->id]))->out(false),
                    'educator_icon' => $userpicture->get_url($GLOBALS['PAGE'])->out(false),
                ];
            }
        }

        return $educators;
    }
}
