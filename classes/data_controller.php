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
 * Select plugin data controller
 *
 * @package   customfield_courseselect
 * @copyright 2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace customfield_courseselect;

use Exception;

defined('MOODLE_INTERNAL') || die;

/**
 * Class data
 *
 * @package customfield_courseselect
 * @copyright 2018 Daniel Neis Araujo <daniel@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class data_controller extends \core_customfield\data_controller
{

    /**
     * Return the name of the field where the information is stored
     * @return string
     */
    public function datafield(): string
    {
        return 'charvalue';
    }

    /**
     * Returns the default value as it would be stored in the database (not in human-readable format).
     *
     * @return mixed
     */
    public function get_default_value()
    {
        $defaultvalue = $this->get_field()->get_configdata_property('defaultvalue');
        if ('' . $defaultvalue !== '') {
            $key = array_search($defaultvalue, $this->get_field()->get_options());
            if ($key !== false) {
                return $key;
            }
        }
        return 0;
    }

    /**
     * Add fields for editing a textarea field.
     *
     * @param \MoodleQuickForm $mform
     */
    public function instance_form_definition(\MoodleQuickForm $mform)
    {
        global $DB;

        $field = $this->get_field();
        $config = $field->get('configdata');

        $query = "SELECT id, fullname from {course}";
        $courselist = $DB->get_records_sql($query);

        $options = $courselist;
        $formattedoptions = array();
        $context = $this->get_field()->get_handler()->get_configuration_context();
        foreach ($options as $key => $option) {
            // Multilang formatting with filters.
            $formattedoptions[$option->id] = format_string($option->fullname, true, ['context' => $context]);
        }

        $elementname = $this->get_form_element_name();
        $mform->addElement('autocomplete', $elementname, $this->get_field()->get_formatted_name(), $formattedoptions);
        $mform->getElement($elementname)->setMultiple(true);

        if (($defaultkey = array_search($config['defaultvalue'], $options)) !== false) {
            $mform->setDefault($elementname, $defaultkey);
        }
        if ($field->get_configdata_property('required')) {
            $mform->addRule($elementname, null, 'required', null, 'client');
        }
    }

    /**
     * Validates data for this field.
     *
     * @param array $data
     * @param array $files
     * @return array
     */
    public function instance_form_validation(array $data, array $files): array
    {
        $errors = parent::instance_form_validation($data, $files);
        if ($this->get_field()->get_configdata_property('required')) {
            // Standard required rule does not work on select element.
            $elementname = $this->get_form_element_name();
            if (empty($data[$elementname])) {
                $errors[$elementname] = get_string('err_required', 'form');
            }
        }
        return $errors;
    }

    /**
     * Returns value in a human-readable format
     *
     * @return mixed|null value or null if empty
     */
    public function export_value()
    {
        $value = $this->get_value();

        if ($this->is_empty($value)) {
            return null;
        }

        $options = $this->get_field()->get_options();
        if (array_key_exists($value, $options)) {
            return format_string(
                $options[$value],
                true,
                ['context' => $this->get_field()->get_handler()->get_configuration_context()]
            );
        }

        return null;
    }

    /**
     * Saves the data coming from form
     *
     * @param \stdClass $datanew data coming from the form
     */
    public function instance_form_save(\stdClass $datanew)
    {
        try {
            $elementname = $this->get_form_element_name();
            
            if (!property_exists($datanew, $elementname)) {
                return;
            }
            $value = $datanew->$elementname;
            $this->data->set($this->datafield(), json_encode($datanew->$elementname));
            $this->data->set('value', json_encode($datanew->$elementname));
            $this->save();
        } catch (\Throwable $th) {
            \core\notification::error($th->getMessage());
        }
    }

    /**
     * Prepares the custom field data related to the object to pass to mform->set_data() and adds them to it
     *
     * This function must be called before calling $form->set_data($object);
     *
     * @param \stdClass $instance the instance that has custom fields, if 'id' attribute is present the custom
     *    fields for this instance will be added, otherwise the default values will be added.
     */
    public function instance_form_before_set_data(\stdClass $instance)
    {
        $values = json_decode($this->get_value());
        $instance->{$this->get_form_element_name()} = $values;
    }
}
