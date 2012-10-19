<?php
/**
 * Joomla! MageBridge Preloader - System plugin
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2012
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// Check to ensure this file is included in Joomla!
defined('_JEXEC') or die( 'Restricted access' );

// Import the parent class
jimport( 'joomla.plugin.plugin' );

// Import the MageBridge autoloader
include_once JPATH_SITE.'/components/com_magebridge/helpers/loader.php';

/**
 * MageBridge Preloader System Plugin
 */
class plgSystemMageBridgePre extends JPlugin
{
    /**
     * Event onAfterLoad
     *
     * @access public
     * @param null
     * @return null
     */
    public function onAfterLoad()
    {
    }

    /**
     * Event onAfterInitialise
     *
     * @access public
     * @param null
     * @return null
     */
    public function onAfterInitialise()
    {
        // Don't do anything if MageBridge is not enabled 
        if ($this->isEnabled() == false) return false;

        // Perform actions on the frontend
        $application = JFactory::getApplication();
        if ($application->isSite()) {

            // Import the custom module helper - this is needed to make it possible to flush certain positions 
            if ($this->getParam('override_modulehelper', 1) == 1 && class_exists('JModuleHelper') == false) {
                $component_path = JPATH_SITE.'/components/com_magebridge/';
                if (MageBridgeHelper::isJoomla15()) {
                    @include_once($component_path.'rewrite/joomla/application/module/helper.php');
                } else if (MageBridgeHelper::isJoomla16()) {
                    @include_once($component_path.'rewrite-16/joomla/application/module/helper.php');
                } else if (MageBridgeHelper::isJoomla17()) {
                    @include_once($component_path.'rewrite-17/joomla/application/module/helper.php');
                } else {
                    @include_once($component_path.'rewrite-25/joomla/application/module/helper.php');
                }
            }
        }

        // Check for postlogin-cookie
        if(isset($_COOKIE['mb_postlogin']) && !empty($_COOKIE['mb_postlogin'])) {

            // If the user is already logged in, remove the cookie
            if(JFactory::getUser()->id > 0) {
                setcookie('mb_postlogin', '', time() - 3600, '/', '.'.JURI::getInstance()->toString(array('host')));
            }

            // Otherwise decrypt the cookie and use it here
            $data = MageBridgeEncryptionHelper::decrypt($_COOKIE['mb_postlogin']);
            if(!empty($data)) $customer_email = $data;
        }

        // Perform a postlogin if needed
        $post = JRequest::get('post');
        if (empty($post)) {
            $postlogin_userevents = ($this->getParams()->get('postlogin_userevents', 0) == 1) ? true : false;
            if(empty($customer_email)) $customer_email = MageBridgeModelBridge::getInstance()->getMageConfig('customer/email');
            if (!empty($customer_email)) MageBridge::getUser()->postlogin($customer_email, null, $postlogin_userevents);
        }
    }

    /**
     * Event onAfterRoute
     *
     * @access public
     * @param null
     * @return null
     */
    /*public function onAfterRoute()
    {
        // Don't do anything if MageBridge is not enabled 
        if ($this->isEnabled() == false) return false;
    }*/

    /*
     * Event onPrepareModuleList (used by Advanced Module Manager)
     */
    public function onPrepareModuleList(&$modules)
    {
        foreach ($modules as $id => $module) {
            if (MageBridgeTemplateHelper::allowPosition($module->position) == false) {
                unset($modules[$id]);
                continue;
            } 
        }
    }

    /**
     * Load the parameters
     *
     * @access private
     * @param null
     * @return JParameter
     */
    private function getParams()
    {
        if (!MageBridgeHelper::isJoomla15()) {
            return $this->params;
        } else {
            jimport('joomla.html.parameter');
            $plugin = JPluginHelper::getPlugin('system', 'magebridgepre');
            $params = new JParameter($plugin->params);
            return $params;
        }
    }

    /**
     * Load a specific parameter
     *
     * @access private
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    private function getParam($name, $default = null)
    {
        return $this->getParams()->get($name, $default);
    }

    /**
     * Simple check to see if MageBridge exists
     * 
     * @access private
     * @param null
     * @return bool
     */
    private function isEnabled()
    {
        if (is_file(JPATH_SITE.'/components/com_magebridge/models/config.php')) {
            return true;
        }
        return false;
    }
}
