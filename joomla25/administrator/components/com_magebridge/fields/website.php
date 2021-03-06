<?php
/*
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('JPATH_BASE') or die();

// Import the MageBridge autoloader
require_once JPATH_SITE.'/components/com_magebridge/helpers/loader.php';

/*
 * Form Field-class for selecting Magento websites 
 */
class JFormFieldWebsite extends JFormFieldAbstract
{
    /*
     * Form field type
     */
    public $type = 'Magento website';

    /*
     * Method to construct the HTML of this element
     *
     * @param null
     * @return string
     */
    protected function getInput()
	{
        $name = $this->name;
        $fieldName = $name;
        $value = $this->value;

        // Only build a dropdown when the API-widgets are enabled
        if (MagebridgeModelConfig::load('api_widgets') == true) {

            // Fetch the widget data from the API
            $options = MageBridgeWidgetHelper::getWidgetData('website');

            // Parse the result into an HTML form-field
            if (!empty($options) && is_array($options)) {
                foreach ($options as $index => $option) {

                    // Customize the return-value when the attribute "output" is defined
                    $output = (string)$this->element['output'];
                    if (!empty($output) && array_key_exists($output, $option)) {
                        $option['value'] = $option[$output];
                    }

                    // Customize the label
                    $option['label'] = $option['label'] . ' ('.$option['value'].') ';

                    // Add the option back to the list of options
                    $options[$index] = $option;
                }

                // Return a dropdown list
                array_unshift( $options, array( 'value' => '', 'label' => ''));
                return JHTML::_('select.genericlist', $options, $fieldName, null, 'value', 'label', $value);

            // Fetching data from the bridge failed, so report a warning
            } else {
                MageBridgeModelDebug::getInstance()->warning( 'Unable to obtain MageBridge API Widget "website": '.var_export($options, true));
            }
        }

        // Return a simple input-field by default
        return '<input type="text" name="'.$fieldName.'" value="'.$value.'" />';
    }
}
