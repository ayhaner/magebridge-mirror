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
defined('_JEXEC') or die();

// Require the parent view
require_once JPATH_COMPONENT.'/view.php';

// Import the needed libraries
jimport('joomla.filter.output');

/**
 * HTML View class
 */
class MageBridgeViewUsergroup extends MageBridgeView
{
    /*
     * Display method
     *
     * @param string $tpl
     * @return null
     */
	public function display($tpl = null)
	{
        // Initialize the view
        $this->setTitle('Edit usergroup relation');

        // Initialize common variables
        $application = JFactory::getApplication();
        $user = JFactory::getUser();
        $option = JRequest::getCmd( 'option' );

		// Get data from the model
        $model = $this->getModel();
		$item = $this->get( 'Data');

        // Get the item
        $item = $this->get('data');
        $isNew = ($item->id < 1);

        // Fail if checked out not by 'me'
        if ($model->isCheckedOut( $user->get('id') )) {
            $msg = JText::sprintf( 'Item locked', $item->name);
            $application->redirect( 'index.php?option='. $option, $msg );
        }

        // Edit or Create?
        if (!$isNew) {
            $model->checkout( $user->get('id') );
        } else {
            // initialise new record
            $item->published = 1;
            $item->order = 0;
        }

        // Before loading anything, we build the bridge
        $this->preBuildBridge();

        // Build the HTML-select list for ordering
        $query = 'SELECT ordering AS value, description AS text'
            . ' FROM #__magebridge_usergroups'
            . ' ORDER BY ordering';

        // Build the fields
        $fields = array();
        $fields['joomla_group'] = $this->getFieldJoomlaGroup($item->joomla_group);
        $fields['magento_group'] = $this->getFieldMagentoGroup($item->magento_group);
        $fields['ordering'] = JHTML::_('list.specificordering',  $item, $item->id, $query );
        $fields['published'] = JHTML::_('select.booleanlist',  'published', 'class="inputbox"', $item->published );

        // Clean the object before displaying
        JFilterOutput::objectHTMLSafe( $item, ENT_QUOTES, 'text' );

        $user = JFactory::getUser();
		$this->assignRef('user', $user);
		$this->assignRef('fields', $fields);
		$this->assignRef('params', $params);
		$this->assignRef('item', $item);

		parent::display($tpl);
	}

    /*
     * Get the HTML-field for the Joomla! usergroup
     *
     * @param null
     * @return string
     */
    public function getFieldJoomlaGroup($value = null)
    {
        $usergroups = MageBridgeFormHelper::getUsergroupOptions();
        return JHTML::_('select.genericlist', $usergroups, 'joomla_group', null, 'value', 'text', $value);
    }

    /*
     * Get the HTML-field for the Magento customer group
     *
     * @param null
     * @return string
     */
    public function getFieldMagentoGroup($value = null)
    {
        require_once JPATH_COMPONENT.'/elements/customergroup.php';
        $fake = null;
        if (MageBridgeHelper::isJoomla15()) {
            require_once JPATH_COMPONENT.'/elements/customergroup.php';
            $fake = null;
            return JElementCustomerGroup::fetchElement('magento_group', $value, $fake, '');
        } else {
            require_once JPATH_COMPONENT.'/fields/customergroup.php';
            $field = new JFormFieldCustomerGroup();
            $field->setName('magento_group');
            $field->setValue($value);
            return $field->getHtmlInput();
        }
    }

    /*
     * Shortcut method to build the bridge for this page
     *
     * @param null
     * @return null
     */
    public function preBuildBridge()
    {
        // Register the needed segments
        $register = MageBridgeModelRegister::getInstance();
        $register->add('api', 'customer_group.list');

        // Build the bridge and collect all segments
        $bridge = MageBridge::getBridge();
        $bridge->build();
    }
}
