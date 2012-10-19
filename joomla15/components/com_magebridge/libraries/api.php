<?php
/**
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

// Import the MageBridge autoloader
require_once JPATH_SITE.'/components/com_magebridge/helpers/loader.php';

/*
 * Main bridge class
 */
class MageBridgeApi
{
    /* 
     * Test method
     *
     * @param null
     * @return string
     */
    public function test()
    {
        return 'OK received from Joomla!';
    }

    /* 
     * Event method
     *
     * @param array $params
     * @return bool
     */
    public function event($params = array())
    {
        // Parse the parameters
        $event = (isset($params[0]) && is_string($params[0])) ? $params[0] : null;
        $arguments = (isset($params[1]) && is_array($params[1])) ? $params[1] : array();

        // Check if this call is valid
        if (empty($event)) return false;

        // Start debugging
        MageBridgeModelDebug::getDebugOrigin(MageBridgeModelDebug::MAGEBRIDGE_DEBUG_ORIGIN_JOOMLA_JSONRPC);
        MageBridgeModelDebug::getInstance()->trace( 'JSON-RPC: firing mageEvent ', $event);
        //MageBridgeModelDebug::getInstance()->trace( 'JSON-RPC: plugin arguments', $arguments );

        // Initialize the plugin-group "magento"
        JPluginHelper::importPlugin('magento');
        $application = JFactory::getApplication();

        // Trigger the event and return the result
        $result = $application->triggerEvent($event, array($arguments));
        if (!empty($result[0])) {
            return $result[0];
        } else {
            return false;
        }
	}

	/**
     * Logs a MageBridge message on the Joomla! side
     *
     * @param array $params
     * @return bool
	 */
	public function log($params = array())
	{
        // Parse the parameters
        $type = (isset($params['type'])) ? $params['type'] : MAGEBRIDGE_DEBUG_NOTICE;
        $message = (isset($params['message'])) ? $params['message'] : null;
        $section = (isset($params['section'])) ? $params['section'] : null;
        $time = (isset($params['time'])) ? $params['time'] : null;
        $origin = MAGEBRIDGE_DEBUG_ORIGIN_MAGENTO;

        // Log this message
        return (bool)MageBridgeModelDebug::getInstance()->add( $type, $message, $section, $origin, $time );
	}

    /* 
     * Output modules on a certain position
     *
     * @param array $params
     * @return bool
     */
    public function position($params = array())
    {
        if (empty($params) || empty($params[0])) {
            MageBridgeModelDebug::getInstance()->error('JSON-RPC: position-method called without parameters');
            return null;
        }

        $position = $params[0];
        $style = (isset($params[1])) ? $params[1] : null;

        jimport('joomla.application.module.helper');
        $modules = JModuleHelper::getModules($position);

        $moduleHtml = null;
        $attribs = array('style' => $params[1]);
        if (!empty($modules)) {
            foreach ($modules as $module) {
                $moduleHtml .= JModuleHelper::renderModule($module, $attribs);
            }
        }

        return $moduleHtml;
    }

    /*
     * Method to get a list of all users
     *
     * @param array $params
     * @return array
     */ 
    public function getUsers($params = array())
    {
        // System variables
        $db = JFactory::getDBO();

        // Construct the query
        $query = 'SELECT * FROM #__users';
        if (isset($params['search'])) {
            $query .= ' WHERE username LIKE '.$db->Quote($params['search']);
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();
        foreach ($rows as $index => $row) {
            $params = new JParameter($row->params);
            $row->params = $params->toArray();
            $rows[$index] = $row;
        }

        return $rows;
    }
}
