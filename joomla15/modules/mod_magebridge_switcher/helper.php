<?php
/**
 * Joomla! module MageBridge: Store switcher
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/*
 * Helper-class for the module
 */
class modMageBridgeSwitcherHelper
{
    /*
     * Method to be called once the MageBridge is loaded
     *
     * @access public
     * @param JParameter $params
     * @return array
     */
    public function register($params = null)
    {
        // Initialize the register 
        $register = array();
        $register[] = array('api', 'magebridge_storeviews.hierarchy');
        return $register;
    }

    /*
     * Fetch the content from the bridge
     * 
     * @access public
     * @param JParameter $params
     * @return array
     */
    public function build($params = null)
    {
        $bridge = MageBridgeModelBridge::getInstance();
        $stores = $bridge->getAPI('magebridge_storeviews.hierarchy');
    
        if (empty($stores) || !is_array($stores)) {
            return null;
        }

        $storeId = $params->get('store_id');
        foreach ($stores as $store) {

            if ($store['value'] == $storeId) {
                return array($store);
                break;
            }
        }

        return $stores;
    }

    /*
     * Generate a HTML selectbox
     * 
     * @access public
     * @param array $stores
     * @param JParameter $params
     * @return string
     */
    public function getFullSelect($stores, $params = null)
    {
        $options = array();
        $currentType = self::getCurrentStoreType();
        $currentName = self::getCurrentStoreName();
        $currentValue = ($currentType == 'store') ? 'v:'.$currentName : 'g:'.$currentName;
        $showGroups = (count($stores) > 1) ? true : false;

        if (!empty($stores) && is_array($stores)) {
            foreach ($stores as $group) {

                if ($group['website'] != MageBridgeModelConfig::load('website')) {
                    continue;
                }

                if ($showGroups) {
                    $options[] = array(
                        'value' => 'g:'.$group['value'],
                        'label' => $group['label'],
                    );
                }

                if (!empty($group['childs'])) {
                    foreach ($group['childs'] as $child) {
                        $labelPrefix = ($showGroups) ? '-- ' : null;
                        $options[] = array(
                            'value' => 'v:'.$child['value'],
                            'label' => $labelPrefix.$child['label'],
                        );
                    }
                }
            }
        }
    
        array_unshift( $options, array( 'value' => '', 'label' => '-- Select --'));
        return JHTML::_('select.genericlist', $options, 'magebridge_store', 'onChange="document.forms[\'mbswitcher\'].submit();"', 'value', 'label', $currentValue);
    }

    /*
     * Generate a simple list of store languages
     * 
     * @access public
     * @param array $stores
     * @param JParameter $params
     * @return string
     */
    public function getStoreSelect($stores, $params = null)
    {
        $options = array();
        $currentName = (MageBridgeStoreHelper::getInstance()->getAppType() == 'store') ? MageBridgeStoreHelper::getInstance()->getAppValue() : null;
        $currentValue = null;

        if (!empty($stores) && is_array($stores)) {
            foreach ($stores as $group) {

                if ($group['website'] != MageBridgeModelConfig::load('website')) {
                    continue;
                }

                if (!empty($group['childs'])) {
                    foreach ($group['childs'] as $child) {
                        $url = JURI::current().'?__store='.$child['value'];
                        if ($child['value'] == $currentName) $currentValue = $url;
                        $options[] = array(
                            'value' => $url,
                            'label' => $child['label'],
                        );
                    }
                }
            }
        }
    
        array_unshift( $options, array( 'value' => '', 'label' => '-- Select --'));
        return JHTML::_('select.genericlist', $options, 'magebridge_store', 'onChange="window.location.href=this.value"', 'value', 'label', $currentValue);
    }

    /*
     * Helper method to get the current store name 
     * 
     * @access public
     * @param null
     * @return string
     */
    public function getCurrentStoreName()
    {
        $application = JFactory::getApplication();
        $name = $application->getUserState('magebridge.store.name');
        return $name;
    }

    /*
     * Helper method to get the current store type
     * 
     * @access public
     * @param null
     * @return string
     */
    public function getCurrentStoreType()
    {
        $application = JFactory::getApplication();
        $type = $application->getUserState('magebridge.store.type');
        return $type;
    }
}
