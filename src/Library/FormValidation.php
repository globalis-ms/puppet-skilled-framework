<?php
namespace Globalis\PuppetSkilled\Library;

require_once BASEPATH . 'libraries/Form_validation.php';

/**
 * Form Validation class
 */
class FormValidation extends \CI_Form_validation
{
    protected $ran = false;

    protected $_error_prefix = '';

    protected $_error_suffix = '';

    /**
     * Get the value from a form
     *
     * Permits you to repopulate a form field with the value it was submitted
     * with, or, if that value doesn't exist, with the default
     *
     * @param   string   the field name
     * @param   string
     * @return  string
     */
    public function set_value($field = '', $default = '')
    {
        if (!isset($this->_field_data[$field], $this->_field_data[$field]['postdata'])) {
            return $default;
        }
        return $this->_field_data[$field]['postdata'];
    }

    /**
     * Set Rules
     *
     * This function takes an array of field names and validation
     * rules as input, any custom error messages, validates the info,
     * and stores it
     *
     * @param    mixed   $field
     * @param    string  $label
     * @param    mixed   $rules
     * @param    array   $errors
     * @return   \Globalis\PuppetSkilled\Library\FormValidation
     */
    public function set_rules($field, $label = '', $rules = array(), $errors = array())
    {
        // If an array was passed via the first parameter instead of individual string
        // values we cycle through it and recursively call this function.
        if (is_array($field)) {
            foreach ($field as $row) {
                // Houston, we have a problem...
                if (!isset($row['field'], $row['rules'])) {
                    continue;
                }
                // If the field label wasn't passed we use the field name
                $label = isset($row['label']) ? $row['label'] : $row['field'];
                // Add the custom error message array
                $errors = (isset($row['errors']) && is_array($row['errors'])) ? $row['errors'] : array();
                // Here we go!
                $this->set_rules($row['field'], $label, $row['rules'], $errors);
            }
            return $this;
        }

        // No fields or no rules? Nothing to do...
        if (! is_string($field) or $field === '' or empty($rules)) {
            return $this;
        } elseif (!is_array($rules)) {
            // BC: Convert pipe-separated rules string to an array
            if (!is_string($rules)) {
                return $this;
            }
            $rules = preg_split('/\|(?![^\[]*\])/', $rules);
        }

        // If the field label wasn't passed we use the field name
        $label = ($label === '') ? $field : $label;

        $indexes = array();

        // Is the field name an array? If it is an array, we break it apart
        // into its components so that we can fetch the corresponding POST data later
        if (($is_array = (bool) preg_match_all('/\[(.*?)\]/', $field, $matches)) === true) {
            sscanf($field, '%[^[][', $indexes[0]);

            for ($i = 0, $c = count($matches[0]); $i < $c; $i++) {
                if ($matches[1][$i] !== '') {
                    $indexes[] = $matches[1][$i];
                }
            }
        }
        // Build our master array
        $this->_field_data[$field] = array(
            'field'        => $field,
            'label'        => $label,
            'rules'        => $rules,
            'errors'    => $errors,
            'is_array'    => $is_array,
            'keys'        => $indexes,
            'postdata'    => null,
            'error'        => ''
        );
        return $this;
    }

    /**
     * Add error message
     *
     * @param   string  $message
     * @param   string  $field
     * @return  \Globalis\PuppetSkilled\Library\FormValidation
     */
    public function add_error($message, $field = null)
    {
        $message = array_values((array)$message);

        // Save the error message
        if (isset($field)) {
            $this->_field_data[$field]['error'] = $message[0];
        }
        $this->_error_array = array_merge($this->_error_array, $message);
        return $this;
    }

    /**
     * Set Select
     *
     * Enables pull-down lists to be set to the value the user
     * selected in the event of an error
     *
     * @param   string
     * @param   string
     * @param   boolean
     * @return  string
     */
    public function set_select($field = '', $value = '', $default = false)
    {
        if (!$this->ran()) {
            return ($default === true) ? ' selected="selected"' : '';
        }

        $field = (isset($this->_field_data[$field]['postdata']) ? $this->_field_data[$field]['postdata']: '');
        $value = (string) $value;
        if (is_array($field)) {
        // Note: in_array('', array(0)) returns true, do not use it
            foreach ($field as $v) {
                if ($value === $v) {
                    return ' selected="selected"';
                }
            }

            return '';
        } elseif (($field === '' || $value === '') || ($field !== $value)) {
            return '';
        }

        return ' selected="selected"';
    }

    /**
     * Set Radio
     *
     * Enables radio buttons to be set to the value the user
     * selected in the event of an error
     *
     * @param    string
     * @param    string
     * @param    boolean
     * @return   string
     */
    public function set_radio($field = '', $value = '', $default = false)
    {
        if (!isset($this->_field_data[$field]['postdata'])) {
            return ($default === true) ? ' checked="checked"' : '';
        }

        $field = $this->_field_data[$field]['postdata'];
        $value = (string) $value;
        if (is_array($field)) {
            // Note: in_array('', array(0)) returns true, do not use it
            foreach ($field as &$v) {
                if ($value === $v) {
                    return ' checked="checked"';
                }
            }

            return '';
        } elseif (($field === '' or $value === '') or ($field !== $value)) {
            return '';
        }

        return ' checked="checked"';
    }

    /**
     * Is required field
     *
     * @param  string  $fieldName
     * @return boolean
     */
    public function isRequired($fieldName)
    {
        return $this->has_rule($fieldName) and in_array('required', $this->_field_data[$fieldName]['rules']);
    }

    /**
     * Validation is valid
     *
     * @return boolean
     */
    public function isValid()
    {
        return ($this->ran && empty($this->error_array()));
    }

    /**
     * Validation has run
     *
     * @return boolean
     */
    public function ran()
    {
        return $this->ran;
    }

    /**
     * Run the Validator
     *
     * This function does all the work.
     *
     * @param   string  $group
     * @return  bool
     */
    public function run($group = '')
    {
        if (!empty($this->validation_data) || $this->CI->input->method() === 'post') {
            $this->ran = true;
            return parent::run($group);
        }
        return false;
    }

    /**
     * Exist
     *
     * Check if the input value exist
     * in the specified database field.
     *
     * @param   string  $str
     * @param   string  $field
     * @return  bool
     */
    public function exist($str, $field)
    {
        return !$this->is_unique($str, $field);
    }
}
