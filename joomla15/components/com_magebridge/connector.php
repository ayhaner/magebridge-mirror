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

/**
 * MageBridge Connector class
 *
 * @package MageBridge
 */
class MageBridgeConnector
{
    /*
     * List of product-connectors
     */
    protected $connectors = array();

    /*
     * Method to check whether this connector is enabled or not
     *
     * @param null
     * @return bool
     */
    public function isEnabled()
    {
        return true;
    }

    /*
     * Method to check whether this connector is visible or not
     *
     * @param null
     * @return bool
     */
    public function isVisible()
    {
        return true;
    }

    /*
     * Method to get the HTML for a connector input-field
     *
     * @param string $value
     * @return null
     */
    public function getFormField($value = null)
    {
        return null;
    }

    /*
     * Method to get the HTML for a connector input-field
     *
     * @param string $name
     * @return null
     */
    public function getFormPost($name = null)
    {
        return null;
    }

    /*
     * Get a list of all connectors
     * 
     * @param string $type
     * @return array
     */
    protected function _getConnectors($type = null)
    {
        $db = JFactory::getDBO();
        $query = "SELECT * FROM #__magebridge_connectors WHERE `published`=1 AND `type`='".$type."' ORDER BY `ordering`";
        $db->setQuery($query);
        $connectors = $db->loadObjectList();
        if (!empty($connectors)) {
            foreach ($connectors as $index => $connector) {

                $connector = self::_getConnectorObject($type, $connector);
                if (!empty($connector) && $connector != false && $connector->isEnabled() != false) { 
                    $connectors[$index] = $connector;
                } else {
                    unset($connectors[$index]);
                }

            }
        }

        return $connectors;
    }

    /*
     * Get a specific connector
     *
     * @param string $type
     * @param string $name
     * @return object
     */
    protected function _getConnector($type = null, $name = null)
    {
        $db = JFactory::getDBO();
        $query = "SELECT * FROM #__magebridge_connectors WHERE `published`=1 AND `type`='".$type."' AND `name`=".$db->Quote($name);
        $db->setQuery($query);
        $connector = $db->loadObject();
        $connector = self::_getConnectorObject($type, $connector);
        return $connector;
    }

    /*
     * Method to get a specific connector-object
     *
     * @param string $type
     * @param string $connector
     * @return object
     */
    protected function _getConnectorObject($type = null, $connector = null)
    {
        if (empty($connector) || empty($connector->filename)) {
            return false;
        }

        $file = self::_getPath($type, $connector->filename);
        if ($file == false) {
            return false;
        }

        require_once $file;
        $class = 'MageBridgeConnector'.ucfirst($type).ucfirst($connector->name);
        if (!class_exists($class)) {
            return false;
        }

        $object = new $class(); 
        if (empty($object)) {
            return false;
        }

        $vars = get_object_vars($connector);
        if (!empty($vars)) {
            foreach ($vars as $name => $value) {
                $object->$name = $value;
            }
        }

        return $object;
    }

    /*
     * Get the connector-parameters
     *
     * @param string $type
     * @return JParameter
     */
    protected function _getParams($type)
    {
        static $params = null;
        if (empty($params)) {
            $file = self::_getPath($type, $this->name.'.xml');

            jimport('joomla.html.parameter');
            if (isset($this->params) && !empty($this->params)) {
                $params = new JParameter( $this->params, $file );
            } else if ($file == true) {
                $params = new JParameter( null, $file );
            } else {
                $params = new JParameter( null, null );
            }
        }
        return $params;
    }

    /*
     * Get the right path to a file
     *
     * @param string $type
     * @param string $filename
     * @return string
     */
    protected function _getPath($type, $filename)
    {
        $path = JPATH_SITE.'/components/com_magebridge/connectors/'.$type.'/'.$filename;
        if (file_exists($path) && is_file($path)) {
            return $path;
        } else {
            return false;
        }
    }

    /*
     * Method to check whether a specific component is there
     *
     * @param string $component
     * @return bool
     */
    protected function checkComponent($component)
    {
        jimport('joomla.application.component.helper');
        if (is_dir(JPATH_ADMINISTRATOR.'/components/'.$component) && JComponentHelper::isEnabled($component) == true) {
            return true;
        } else {
            return false;
        }
    }
}
