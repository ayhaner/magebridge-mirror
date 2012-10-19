<?php
/**
 * Joomla! module MageBridge: CMS Block
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
class modMageBridgeCMSHelper
{
    /*
     * Method to be called as soon as MageBridge is loaded
     *
     * @access public
     * @param JParameter $params
     * @return array
     */
    public function register($params = null)
    {
        // Get the block name
        $blockName = $params->get('block');
        $arguments = array('blocktype' => 'cms');

        // Initialize the register 
        $register = array();
        $register[] = array('block', $blockName, $arguments);

        if ($params->get('load_css', 1) == 1 || $params->get('load_js', 1) == 1) {
            $register[] = array('headers');
        }
        return $register;
    }

    /*
     * Fetch the content from the bridge
     * 
     * @access public
     * @param JParameter $params
     * @return string
     */
    public function build($params = null)
    {
        // Get the block name
        $blockName = $params->get('block');
        $arguments = array('blocktype' => 'cms');

        // Include the MageBridge bridge
        $bridge = MageBridgeModelBridge::getInstance();

        // Load CSS if needed
        if ($params->get('load_css', 1) == 1) {
            $bridge->setHeaders('css');
        }

        // Load JavaScript if needed
        if ($params->get('load_js', 1) == 1) {
            $bridge->setHeaders('js');
        }

        return $bridge->getBlock($blockName, $arguments);
    }
}
