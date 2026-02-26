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
 * Administrative settings
 *
 * @package     format_edukav
 * @copyright   2024 University of Essex
 * @author      John Maydew <jdmayd@essex.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $ADMIN, $CFG, $PAGE;

require_once("$CFG->dirroot/course/format/edukav/lib.php");

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'format_edukav',
        get_string('settings:name', 'format_edukav')
    );

    $settings->add(new admin_setting_heading('format_edukav_defaults',
        get_string('settings:defaults', 'format_edukav'),
        get_string('settings:defaults:description', 'format_edukav')
    ));

    $settings->add(new admin_setting_configselect('format_edukav/section0',
        get_string('form:course:section0', 'format_edukav'),
        get_string('form:course:section0_help', 'format_edukav'),
        FORMAT_EDUKAV_SECTION0_COURSEPAGE,
        [
            FORMAT_EDUKAV_SECTION0_COURSEPAGE => get_string('form:course:section0:coursepage', 'format_edukav'),
            FORMAT_EDUKAV_SECTION0_ALLPAGES => get_string('form:course:section0:allpages', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_edukav/sectionnavigation',
        get_string('form:course:sectionnavigation', 'format_edukav'),
        get_string('form:course:sectionnavigation_help', 'format_edukav'),
        $PAGE->theme->usescourseindex ? FORMAT_EDUKAV_SECTIONNAVIGATION_NONE : FORMAT_EDUKAV_SECTIONNAVIGATION_BOTH,
        [
            FORMAT_EDUKAV_SECTIONNAVIGATION_NONE => get_string('form:course:sectionnavigation:none', 'format_edukav'),
            FORMAT_EDUKAV_SECTIONNAVIGATION_TOP => get_string('form:course:sectionnavigation:top', 'format_edukav'),
            FORMAT_EDUKAV_SECTIONNAVIGATION_BOTTOM => get_string('form:course:sectionnavigation:bottom', 'format_edukav'),
            FORMAT_EDUKAV_SECTIONNAVIGATION_BOTH => get_string('form:course:sectionnavigation:both', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_edukav/sectionnavigationhome',
        get_string('form:course:sectionnavigationhome', 'format_edukav'),
        get_string('form:course:sectionnavigationhome_help', 'format_edukav'),
        FORMAT_EDUKAV_SECTIONNAVIGATIONHOME_HIDE,
        [
            FORMAT_EDUKAV_SECTIONNAVIGATIONHOME_HIDE => get_string('form:course:sectionnavigationhome:hide', 'format_edukav'),
            FORMAT_EDUKAV_SECTIONNAVIGATIONHOME_SHOW => get_string('form:course:sectionnavigationhome:show', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_edukav/cardorientation',
        get_string('form:course:cardorientation', 'format_edukav'),
        '',
        FORMAT_EDUKAV_ORIENTATION_VERTICAL,
        [
            FORMAT_EDUKAV_ORIENTATION_VERTICAL => get_string('form:course:cardorientation:vertical', 'format_edukav'),
            FORMAT_EDUKAV_ORIENTATION_HORIZONTAL => get_string('form:course:cardorientation:horizontal', 'format_edukav'),
            FORMAT_EDUKAV_ORIENTATION_SQUARE => get_string('form:course:cardorientation:square', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_edukav/showsummary',
        get_string('form:course:showsummary', 'format_edukav'),
        '',
        FORMAT_EDUKAV_SHOWSUMMARY_SHOW,
        [
            FORMAT_EDUKAV_SHOWSUMMARY_SHOW => get_string('form:course:showsummary:show', 'format_edukav'),
            FORMAT_EDUKAV_SHOWSUMMARY_HIDE => get_string('form:course:showsummary:hide', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_cards/showprogress',
        get_string('form:course:showprogress', 'format_edukav'),
        '',
        FORMAT_EDUKAV_SHOWPROGRESS_SHOW,
        [
            FORMAT_EDUKAV_SHOWPROGRESS_SHOW => get_string('form:course:showprogress:show', 'format_edukav'),
            FORMAT_EDUKAV_SHOWPROGRESS_HIDE => get_string('form:course:showprogress:hide', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_edukav/progressformat',
        get_string('form:course:progressformat', 'format_edukav'),
        '',
        FORMAT_EDUKAV_PROGRESSFORMAT_PERCENTAGE,
        [
            FORMAT_EDUKAV_PROGRESSFORMAT_COUNT => get_string('form:course:progressformat:count', 'format_edukav'),
            FORMAT_EDUKAV_PROGRESSFORMAT_PERCENTAGE => get_string('form:course:progressformat:percentage', 'format_edukav'),
        ]
    ));

    $settings->add(new admin_setting_configselect('format_cards/subsectionsascards',
        get_string('form:course:subsectionsascards', 'format_edukav'),
        '',
        FORMAT_EDUKAV_SUBSECTIONS_AS_ACTIVITIES,
        [
            FORMAT_EDUKAV_SUBSECTIONS_AS_CARDS => get_string('form:course:subsectionsascards:cards', 'format_edukav'),
            FORMAT_EDUKAV_SUBSECTIONS_AS_ACTIVITIES => get_string('form:course:subsectionsascards:activity', 'format_edukav'),
        ]
    ));
}
