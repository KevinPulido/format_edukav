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
use completion_info;
use format_edukav\versionable_template;
use local_edukav\service\partners_service;
use format_topics\output\courseformat\content as content_base;
use moodle_exception;
use moodle_url;
use renderer_base;
use section_info;
use stdClass;

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
        global $PAGE,$DB,$CFG;

        // Is this a single section page?
        $singlesection = $this->format->get_sectionnum();
        $issectionpage = $PAGE->pagetype === 'course-section' || $PAGE->pagetype === 'course-view-section-edukav';

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

        $bannervideo = $this->format->get_format_option('banner_video');
        $videotype = (string) $this->format->get_format_option('banner_video_type');
        $level = trim((string) $this->format->get_format_option('level'));
        $leveldisplay = $level !== '' && get_string_manager()->string_exists('level:' . $level, 'format_edukav')
            ? get_string('level:' . $level, 'format_edukav')
            : $level;
        $videourl = $this->format->normalize_video_url($bannervideo);
        $videoid = $this->format->extract_video_id($bannervideo);
        $placeholderimage = $output->image_url('placeholder_video', 'format_edukav')->out();
        $herobackground = $output->image_url('hero-video-background', 'format_edukav')->out(false);
        $courseimage = '';
        $courseelement = new \core_course_list_element($course);
        foreach ($courseelement->get_course_overviewfiles() as $file) {
            if ($file->is_valid_image()) {
                $courseimage = moodle_url::make_file_url(
                    "$CFG->wwwroot/pluginfile.php",
                    '/' . $file->get_contextid() . '/course/overviewfiles' .
                        $file->get_filepath() . $file->get_filename()
                )->out(false);
                break;
            }
        }
        $videofileurl = null;
        if ($videotype === 'upload') {
            $videofiles = get_file_storage()->get_area_files(
                $context->id,
                'format_edukav',
                'bannervideo',
                0,
                'filename,filepath',
                false
            );
            $videofile = $videofiles ? reset($videofiles) : null;
            if ($videofile) {
                $videofileurl = moodle_url::make_pluginfile_url(
                    $context->id,
                    'format_edukav',
                    'bannervideo',
                    0,
                    $videofile->get_filepath(),
                    $videofile->get_filename()
                )->out(false);
            }
        }

        // Show the large course hero only on the course home page, not on the dedicated section page.
        $data->showcourseEdukav = !$singlesection && !$issectionpage;
        
        $moodlecourseprogress = $this->get_moodle_course_progress($output);

        $data->coursesEdukav = [
            'fullname' => $course->fullname,
            'summary' => format_text($course->summary, $course->summaryformat),
            'enrolledcount' => $enrolledstudents,
            'categoryName' => format_string($categoryName),
            'startdate' => userdate($course->startdate, '%d %b %Y'),
            'educators' => $educators,
            'partner' => $partner,
            'video_url' => $videourl,
            'video_id' => $videoid ?? '',
            'video_file_url' => $videofileurl,
            'course_image' => $courseimage ?: $placeholderimage,
            'placeholder_image' => $placeholderimage,
            'hero_background' => $herobackground,
            'duration' => trim((string) $this->format->get_format_option('duration')),
            'level' => $leveldisplay,
            'modulecount' => $this->get_visible_module_count(),
            'activityprogress' => $moodlecourseprogress,
        ];

        // Add version variables.
        $this->add_version_variables($data);

        // Rather than rolling our own empty placeholder, we can just re-use the "no courses" template
        // from block_myoverview and change the text to be "No activities" instead.
        $data->nocoursesimg = $output->image_url('courses', 'block_myoverview')->out();

        $data->userisediting = $PAGE->user_is_editing();

        $data->moduleprogress = $this->get_module_progress($moodlecourseprogress);

        $data->subsectionsascards = $this->format->get_format_option("subsectionsascards") == FORMAT_EDUKAV_SUBSECTIONS_AS_CARDS;

        $generalsection = $this->format->get_section(0);
        $objectives = $generalsection ? $this->format->get_format_option('objectives', $generalsection) : '';
        $objectivesformat = $generalsection
            ? (int)$this->format->get_format_option('objectivesformat', $generalsection)
            : FORMAT_HTML;
        if ($objectives === '') {
            $objectives = $this->format->get_format_option('objectives');
            $objectivesformat = (int)$this->format->get_format_option('objectivesformat');
        }
        $data->objectiveshtml = !empty($objectives)
            ? format_text(
                file_rewrite_pluginfile_urls(
                    $objectives,
                    'pluginfile.php',
                    $context->id,
                    'format_edukav',
                    FORMAT_EDUKAV_FILEAREA_OBJECTIVES,
                    $generalsection->id
                ),
                $objectivesformat ?: FORMAT_HTML,
                ['context' => $context]
            )
            : '';

        $generalcronograma = $generalsection ? $this->format->get_format_option('generalcronograma', $generalsection) : '';
        $generalcronogramaformat = $generalsection
            ? (int)$this->format->get_format_option('generalcronogramaformat', $generalsection)
            : FORMAT_HTML;
        if ($generalcronograma === '') {
            $generalcronograma = $this->format->get_format_option('generalcronograma');
            $generalcronogramaformat = (int)$this->format->get_format_option('generalcronogramaformat');
        }
        $data->generalcronogramahtml = !empty($generalcronograma)
            ? format_text(
                file_rewrite_pluginfile_urls(
                    $generalcronograma,
                    'pluginfile.php',
                    $context->id,
                    'format_edukav',
                    FORMAT_EDUKAV_FILEAREA_GENERALCRONOGRAMA,
                    $generalsection->id
                ),
                $generalcronogramaformat ?: FORMAT_HTML,
                ['context' => $context]
            )
            : '';

        $data->generalnavigation = $this->get_general_navigation_data($course);

        $this->add_section_navigation($data, $output);
        

        return $data;
    }

    /**
     * Build the course-level module progress summary for the card view.
     *
     * Only visible non-general sections with at least one completable
     * activity are included, so the summary reflects actual Moodle progress.
     *
     * @param array $courseprogress Official Moodle course progress data.
     * @return array
     */
    private function get_module_progress(array $courseprogress = []): array {
        if (isguestuser() || !$this->format->get_course()->enablecompletion) {
            return [];
        }

        $total = 0;
        $completed = 0;
        foreach ($this->format->get_modinfo()->get_section_info_all() as $section) {
            if ((int) $section->section === 0 || !$section->uservisible) {
                continue;
            }

            $completion = $this->get_section_completion_for($section);
            if (empty($completion)) {
                continue;
            }

            $total++;
            if ($completion['iscomplete']) {
                $completed++;
            }
        }

        if ($total === 0) {
            return [];
        }

        // Keep the module completion count independent from the official
        // activity-based percentage displayed by Moodle's course card.
        $percentage = isset($courseprogress['percentage']) ? (int) $courseprogress['percentage'] : 0;

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'dashoffset' => 100 - $percentage,
        ];
    }

    /**
     * Count visible course modules represented as sections, excluding General.
     *
     * @return int
     */
    private function get_visible_module_count(): int {
        $count = 0;

        foreach ($this->format->get_modinfo()->get_section_info_all() as $section) {
            if ((int) $section->section !== 0 && $section->uservisible) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get the course progress from the same Moodle exporter used by the
     * dashboard course card.
     *
     * @param renderer_base $output
     * @return array
     */
    private function get_moodle_course_progress(renderer_base $output): array {
        if (isguestuser()) {
            return [];
        }

        $course = $this->format->get_course();
        $exporter = new \core_course\external\course_summary_exporter(
            $course,
            ['context' => \context_course::instance($course->id), 'isfavourite' => false]
        );
        $coursecard = $exporter->export($output);

        if (!property_exists($coursecard, 'progress')) {
            return [];
        }

        return [
            'percentage' => $coursecard->progress,
        ];
    }

    /**
     * Build the CTA for the general section welcome card.
     *
     * The button points to the first visible section that still has progress pending.
     * If the learner has already completed something, the label changes to "continue".
     *
     * @param stdClass $course
     * @return stdClass|null
     * @throws moodle_exception
     */
    private function get_general_navigation_data(stdClass $course): ?stdClass {
        $modinfo = $this->format->get_modinfo();
        $fallbacksection = null;
        $targetsection = null;
        $hasprogress = false;

        foreach ($modinfo->get_section_info_all() as $sectioninfo) {
            if ((int) $sectioninfo->section === 0) {
                continue;
            }

            if (!$sectioninfo->uservisible) {
                continue;
            }

            if ($fallbacksection === null) {
                $fallbacksection = $sectioninfo;
            }

            $completion = $this->get_section_completion_for($sectioninfo);
            if (!empty($completion) && !empty($completion['completed'])) {
                $hasprogress = true;
            }

            if (!empty($completion) && empty($completion['iscomplete'])) {
                $targetsection = $sectioninfo;
                break;
            }
        }

        if ($targetsection === null) {
            $targetsection = $fallbacksection;
        }

        if ($targetsection === null) {
            return null;
        }

        return (object) [
            'homeurl' => course_get_url($course, null, ['navigation' => true])->out(),
            'homename' => get_string('maincoursepage'),
            'hasnext' => true,
            'nexturl' => $this->format->get_view_url($targetsection, ['navigation' => true])->out(false),
            'nextname' => $targetsection->name,
            'nexthidden' => !$targetsection->visible,
            'hasprogress' => $hasprogress,
            'buttonlabel' => $hasprogress
                ? get_string('navigation:continuemodule', 'format_edukav')
                : get_string('navigation:progressmodule', 'format_edukav'),
        ];
    }

    /**
     * Grabs the completion info for a specific section.
     *
     * @param section_info $section
     * @return array
     */
    private function get_section_completion_for(section_info $section): array {
        global $CFG;

        if (isguestuser() || !$this->format->get_course()->enablecompletion) {
            return [];
        }

        if ($this->format->get_format_option('showprogress') == FORMAT_EDUKAV_SHOWPROGRESS_HIDE) {
            return [];
        }

        $completioninfo = new completion_info($this->format->get_course());
        $modinfo = $section->modinfo;

        if (!array_key_exists($section->section, $modinfo->sections)) {
            return [];
        }

        $sectioncmids = $modinfo->sections[$section->section];

        if ($CFG->version >= 2024100700) {
            foreach ($sectioncmids as $cmid) {
                $cminfo = $modinfo->cms[$cmid];

                if ($cminfo->modname !== 'subsection') {
                    continue;
                }

                $subsection = $modinfo->get_section_info_by_component('mod_subsection', $cminfo->instance);

                if (!array_key_exists($subsection->section, $modinfo->sections)) {
                    continue;
                }

                $subsectioncmids = $modinfo->sections[$subsection->section];

                if (empty($subsectioncmids)) {
                    continue;
                }

                array_push($sectioncmids, ...$subsectioncmids);
            }
        }

        $total = 0;
        $completed = 0;

        foreach ($sectioncmids as $cmid) {
            $cminfo = $modinfo->cms[$cmid];

            if (!$cminfo->uservisible || $cminfo->deletioninprogress) {
                continue;
            }

            if ($completioninfo->is_enabled($cminfo) == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $total++;

            $completiondata = $completioninfo->get_data($cminfo, true);

            if (in_array(
                $completiondata->completionstate,
                [COMPLETION_COMPLETE, COMPLETION_COMPLETE_PASS]
            )) {
                $completed++;
            }
        }

        if ($total == 0) {
            return [];
        }

        $iscomplete = $total == $completed;
        $progressformat = $this->format->get_format_option('progressformat');
        $percentage = round(($completed / $total) * 100);

        return [
            'total' => $total,
            'completed' => $completed,
            'percentage' => $percentage,
            'dashoffset' => 100 - $percentage,
            'iscomplete' => $iscomplete,
            'hasprogress' => $completed > 0,
            'showpercentage' => !$iscomplete && $progressformat == FORMAT_EDUKAV_PROGRESSFORMAT_PERCENTAGE,
            'showcount' => !$iscomplete && $progressformat == FORMAT_EDUKAV_PROGRESSFORMAT_COUNT,
        ];
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
