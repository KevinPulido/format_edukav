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
 * Moodle form for editing a section
 *
 * @package     format_cards
 * @copyright   2024 University of Essex
 * @author      John Maydew <jdmayd@essex.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_edukav\forms;

use coding_exception;
use editsection_form;
use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->libdir/formslib.php");
require_once("$CFG->dirroot/course/editsection_form.php");

/**
 * Moodle form for editing a section
 *
 * @package     format_cards
 * @copyright   2024 University of Essex
 * @author      John Maydew <jdmayd@essex.ac.uk>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class editcard_form extends editsection_form {

    /**
     * Expands the editsection_form by adding an image editing section to the end
     *
     * @return void
     * @throws coding_exception
     */
    public function definition(): void {
        parent::definition();

        $form = $this->_form;
        $editoroptions = $this->_customdata['editoroptions'];
        $section = $this->_customdata['cs'];

        if ($section->section === 0) {
            $form->addElement('header', 'generalcontent', get_string('form:course:generalobjectives', 'format_edukav'));
            $form->setExpanded('generalcontent');

            $form->addElement(
                'editor',
                'objectives_editor',
                get_string('form:course:objectives', 'format_edukav'),
                null,
                $editoroptions
            );
            $form->setType('objectives_editor', PARAM_RAW);
            $form->addHelpButton('objectives_editor', 'form:course:objectives', 'format_edukav');

            $form->addElement(
                'editor',
                'generalcronograma_editor',
                get_string('form:course:generalcronograma', 'format_edukav'),
                null,
                $editoroptions
            );
            $form->setType('generalcronograma_editor', PARAM_RAW);
            $form->addHelpButton('generalcronograma_editor', 'form:course:generalcronograma', 'format_edukav');
        }

        $form->addElement('header', 'cardimage', get_string('editcard', 'format_edukav'));
        $form->setExpanded('cardimage');

        $form->addElement(
            'filemanager',
            'image',
            get_string('image', 'format_edukav'),
            null,
            [
                'subdirs' => 0,
                'maxfiles' => 1,
                'accepted_types' => [ 'web_image' ],
            ]
        );

        if (array_key_exists('image', $this->_customdata)) {
            $form->setDefault('image', $this->_customdata['image']);
        }
    }

    /**
     * Load defaults and prepare the editor files for section 0 content blocks.
     *
     * @param stdClass|array $default_values object or array of default values
     * @return void
     */
    public function set_data($default_values) {
        if (!is_object($default_values)) {
            $default_values = (object)$default_values;
        }

        $editoroptions = $this->_customdata['editoroptions'];
        $section = $this->_customdata['cs'];

        if ($section->section === 0) {
            $course = $this->_customdata['course'];
            $courseformat = course_get_format($course);
            if (!property_exists($default_values, 'objectives') || trim((string)$default_values->objectives) === '') {
                $savedobjectives = $courseformat->get_format_option('objectives');
                $default_values->objectives = trim((string)$savedobjectives) !== ''
                    ? $savedobjectives
                    : $this->get_default_objectives_html();
                $default_values->objectivesformat = $courseformat->get_format_option('objectivesformat') ?: FORMAT_HTML;
            }
            if (!property_exists($default_values, 'generalcronograma')) {
                $default_values->generalcronograma = $courseformat->get_format_option('generalcronograma');
                $default_values->generalcronogramaformat = $courseformat->get_format_option('generalcronogramaformat');
            }
        }

        if ($section->section === 0) {
            $default_values = file_prepare_standard_editor(
                $default_values,
                'objectives',
                $editoroptions,
                $editoroptions['context'],
                'format_edukav',
                \FORMAT_EDUKAV_FILEAREA_OBJECTIVES,
                $default_values->id
            );
            $default_values = file_prepare_standard_editor(
                $default_values,
                'generalcronograma',
                $editoroptions,
                $editoroptions['context'],
                'format_edukav',
                \FORMAT_EDUKAV_FILEAREA_GENERALCRONOGRAMA,
                $default_values->id
            );
        }

        parent::set_data($default_values);
    }

    /**
     * Default objectives table shown in the editor when no content has been written yet.
     *
     * @return string
     */
    private function get_default_objectives_html(): string {
        return '
            <table class="edukav-objectives-table">
                <thead>
                    <tr>
                        <th scope="col">{{#str}}general:section:text:table:headermodule{{/str}}</th>
                        <th scope="col">{{#str}}general:section:text:table:headergeneralobjective{{/str}}</th>
                        <th scope="col">{{#str}}general:section:text:table:headerspecificobjectives{{/str}}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>1. {{#str}}general:section:text:table:instructionsmodule{{/str}}</td>
                        <td>{{#str}}general:section:text:table:instructionsgeneralobjective{{/str}}</td>
                        <td>
                            <p>{{#str}}general:section:text:table:instructionsspecificobjectives{{/str}}</p>
                        </td>
                    </tr>
                    <tr>
                        <td>2. {{#str}}general:section:text:table:instructionsmodule{{/str}}</td>
                        <td>{{#str}}general:section:text:table:instructionsgeneralobjective{{/str}}</td>
                        <td>
                            <p>{{#str}}general:section:text:table:instructionsspecificobjectives{{/str}}</p>
                        </td>
                    </tr>
                    <tr>
                        <td>3. {{#str}}general:section:text:table:instructionsmodule{{/str}}</td>
                        <td>{{#str}}general:section:text:table:instructionsgeneralobjective{{/str}}</td>
                        <td>
                            <p>{{#str}}general:section:text:table:instructionsspecificobjectives{{/str}}</p>
                        </td>
                        </td>
                    </tr>
                </tbody>
            </table>';
    }

    /**
     * Postprocess editor data so uploaded files are stored in Moodle file areas.
     *
     * @return stdClass|null
     */
    public function get_data() {
        $data = parent::get_data();
        if ($data === null) {
            return null;
        }

        $section = $this->_customdata['cs'];
        if ($section->section === 0) {
            $editoroptions = $this->_customdata['editoroptions'];
            $data = file_postupdate_standard_editor(
                $data,
                'objectives',
                $editoroptions,
                $editoroptions['context'],
                'format_edukav',
                \FORMAT_EDUKAV_FILEAREA_OBJECTIVES,
                $data->id
            );
            $data = file_postupdate_standard_editor(
                $data,
                'generalcronograma',
                $editoroptions,
                $editoroptions['context'],
                'format_edukav',
                \FORMAT_EDUKAV_FILEAREA_GENERALCRONOGRAMA,
                $data->id
            );
        }

        return $data;
    }
}

