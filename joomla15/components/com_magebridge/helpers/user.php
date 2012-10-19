<?php
/**
 * Joomla! component MageBridge
 *
 * @author Yireo (info@yireo.com)
 * @package MageBridge
 * @copyright Copyright 2011
 * @license GNU Public License
 * @link http://www.yireo.com
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

/*
 * Helper for dealing with Joomla!/Magento customer
 */
class MageBridgeUserHelper
{
    /*
     * Helper-method to return the default Joomla! usergroup ID
     *
     * @param null
     * @return int
     */
    public function getDefaultJoomlaGroupid()
    {
        $usergroup = MageBridgeModelConfig::load('usergroup');
        if (!empty($usergroup)) {
            return $usergroup;
        }

        if (MageBridgeHelper::isJoomla15()) {
            $group = self::getDefaultJoomlaGroup();
            $group_id = (int)JFactory::getACL()->get_group_id('', $group);
            return $group_id;
        } else {
            $params = JComponentHelper::getParams('com_users');
            $group_id = $params->get('new_usertype');
            return $group_id;
        }
    }

    /*
     * Helper-method to return the default Joomla! usergroup name
     *
     * @param null
     * @return string
     */
    public function getDefaultJoomlaGroup()
    {
        if (MageBridgeHelper::isJoomla15()) {
            $usergroup = MageBridgeModelConfig::load('usergroup');
            if (!empty($usergroup)) {
                $usergroup_name = JFactory::getACL()->get_group_name($usergroup);
                return $usergroup_name;
            } else {
                $conf = JComponentHelper::getParams('com_users');
                return $conf->get('new_usertype', 'Registered');
            }
        }
        return null;
    }

    /*
     * Helper-method to return the Magento customergroup based on the current Joomla! usergroup
     *
     * @param null
     * @return string
     */
    public function getMagentoGroupId($user)
    {
        static $rows = null;
        if (!is_array($rows)) {
            $db = JFactory::getDBO();
            $db->setQuery('SELECT * FROM #__magebridge_usergroups WHERE `published`=1 ORDER BY `ordering`');
            $rows = $db->loadObjectList();
        }

        if (!empty($rows)) {
            if (MageBridgeHelper::isJoomla15()) {
                $usergroups = (isset($user['gid'])) ? array($user['gid']) : array();
            } else {
                $usergroups = (isset($user['groups'])) ? $user['groups'] : array();
            }

            foreach ($rows as $row) {
                if (in_array($row->joomla_group, $usergroups)) {
                    return $row->magento_group;
                }
            }
        }

        return null;
    }

    /*
     * Helper-method to return the Joomla! usergroup based on the current Magento customergroup
     *
     * @param null
     * @return string
     */
    public function getJoomlaGroupId($customer)
    {
        if (!isset($customer['group_id'])) return null;

        static $rows = null;
        if (!is_array($rows)) {
            $db = JFactory::getDBO();
            $db->setQuery('SELECT * FROM #__magebridge_usergroups WHERE `published`=1 ORDER BY `ordering`');
            $rows = $db->loadObjectList();
        }

        if (!empty($rows)) {
            foreach ($rows as $row) {
                if ($row->magento_group == $customer['group_id']) {
                    return $row->joomla_group;
                }
            }
        }

        return null;
    }

    /*
     * Helper-method to convert the name into a firstname and lastname
     *
     * @param mixed $user
     * @return bool
     */
    public function convert($user)
    {
        jimport('joomla.utilities.arrayhelper');

        $rt = 'object';
        if (is_array($user)) {
            $rt = 'array';
            foreach ($user as $name => $value) {
                if (empty($value)) {
                    unset($user[$name]);
                }
            }
            $user = JArrayHelper::toObject($user);
        }

        $name = (isset($user->name)) ? $user->name : null;
        $firstname = (isset($user->firstname)) ? $user->firstname : null;
        $lastname = (isset($user->lastname)) ? $user->lastname : null;
        $username = (isset($user->username)) ? $user->username : null;

        // Generate an username
        if (empty($username)) {
            $username = $user->email;
        }

        // Generate a firstname and lastname
        if (!empty($name) && (empty($firstname) && empty($lastname))) {
            $array = explode(' ', $name);
            $firstname = array_shift($array);
            $lastname = implode( ' ', $array );
        }
    
        // Generate a name
        if (empty($name) && (!empty($firstname) && !empty($lastname))) {
            $name = $firstname.' '.$lastname;
        } else if (empty($name)) {
            $name = $username;
        }

        // Insert the values back into the object
        $user->name = trim($name);
        $user->username = trim($username);
        $user->firstname = trim($firstname);
        $user->lastname = trim($lastname);

        // Return either an array or an object
        if ($rt == 'array') {
            return JArrayHelper::fromObject($user);
        }
        return $user;
    }
}
