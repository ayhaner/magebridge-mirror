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

/*
 * Bridge user class
 */
class MageBridgeModelUser
{
    /*
     * Instance variable
     */
    protected static $_instance = null;

    /*
     * Singleton
     *
     * @return MageBridgeModelUser $_instance
     */
    public static function getInstance()
    {
        static $instance;

        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /*
     * Method to create a new Joomla! user if it does not yet exist
     *
     * @param array $user
     * @param bool $empty_password
     * @return JUser|null
     */
    public function create($user, $empty_password = false)
    {
        // Import needed libraries
        jimport('joomla.utilities.date');
        jimport('joomla.user.helper');
        jimport('joomla.application.component.helper');

        // Get system variables
        $db = JFactory::getDBO();

        // Try to fetch the user-record from the database
        $query = 'SELECT `id` FROM #__users WHERE email=' . $db->quote($user['email']);
        $db->setQuery( $query );
        $result = $db->loadResult();

        // If $result is empty, this user (with $user['email']) does not exist yet
        if (empty($result)) {

            // Construct a data-array for this user
            $data = array(
                'name' => $user['name'],
                'username' => $user['username'],
                'email' => $user['email'],
                'guest' => 0,
            );

            // Current date
            $now = new JDate();
            $data['registerDate'] = (method_exists('JDate', 'toSql')) ? $now->toSql() : $now->toMySQL();

            // Add Joomla! 1.5 specific data
            if (MageBridgeHelper::isJoomla15()) {
                $data['usertype'] = MageBridgeUserHelper::getDefaultJoomlaGroup();
                $data['gid'] = MageBridgeUserHelper::getDefaultJoomlaGroupid();
            }

            // Do not use empty passwords in the Joomla! user-record
            if ($empty_password == false) {

                // Generate a new password if a password is not set
                $pasword = (empty($user['password'])) ? JUserHelper::genRandomPassword() : $user['password'];

                // Generate the encrypted password
			    $salt  = JUserHelper::genRandomPassword(32);
    			$crypt = JUserHelper::getCryptedPassword($password, $salt);
                $data['password'] = $crypt.':'.$salt;
                $data['password2'] = $crypt.':'.$salt;

            // Use empty password in the Joomla! user-record
            } else {
                $data['password'] = '';
                $data['password2'] = '';
            }

            // Get the com_user table-class and use it to store the data to the database
            $table = JTable::getInstance('user', 'JTable');
            $table->bind($data);
            $table->store();

            // Add Joomla! 1.6 or higher specific data
            if (MageBridgeHelper::isJoomla15() == false) {
        
                if (isset($table->id) && $table->id > 0) {

                    // Check whether the current user is part of any groups
                    $db->setQuery('SELECT * FROM #__user_usergroup_map` WHERE `user_id`='.$table->id);
                    $rows = $db->loadObjectList();
                    if (empty($rows)) {
			            $group_id = MageBridgeUserHelper::getDefaultJoomlaGroupid();
                        if (!empty($group_id)) {
    				        $db->setQuery('INSERT INTO `#__user_usergroup_map` SET `user_id`='.$table->id.', `group_id`='.$group_id);
                            $db->query(); 
                        }
                    }
                }
            }

            // Get the resulting user
            return self::loadByEmail($user['email']);
        }
        return null;
    }

    /*
     * Method to fix ACL-rules in Joomla! 1.5
     *
     * @param object $user
     * @return bool
     */
    public function fixAcls($user = null)
    {
        if (MageBridgeHelper::isJoomla15() == false) return false;
        if (empty($user) || !is_object($user)) return false;
        if (empty($user->id) || $user->guest == 0) return false;
        $user_id = $user->id;
        
        $db = JFactory::getDBO();
        $db->setQuery('SELECT * FROM `#__core_acl_aro` WHERE `value`='.$user_id.' LIMIT 1');
        $row = $db->loadObject();
        if (empty($row)) {

            $db->setQuery('INSERT INTO `#__core_acl_aro` SET `section_value`="users", `value`='.$user_id.', `name`="'.$user->name.'"');
            $db->query();

            $db->setQuery('SELECT * FROM `#__core_acl_aro` WHERE `value`='.$user_id.' LIMIT 1');
            $row = $db->loadObject();

            if (!empty($row)) {
                $db->setQuery('INSERT INTO `#__core_acl_groups_aro_map` SET `group_id`="18", `section_value`="", `aro_id`='.(int)$row->id);
                $db->query();
            }

            return true;
        }
        return false;
    }

    /*
     * Method to synchronize the user account with Magento
     *
     * @param array $user
     * @return array $data Data as returned by Magento
     */
    public function synchronize($user)
    {
        MageBridgeModelDebug::getInstance()->notice( "MageBridgeModelUser::synchronize() on user ".$user['email'] );

        // Use the email if no username is set
        if (empty($user['username'])) {
            $user['username'] = $user['email'];
        }

        // Set the right ID
        $user['joomla_id'] = (isset($user['id'])) ? $user['id'] : 0;

        // Find some logic to divide the "name" into a "firstname" and "lastname"
        $user = MageBridgeUserHelper::convert($user);

        // Only set the password, when the password does not appear to be the encrypted version
        if (empty($user['password_clear'])) {
            if (isset($user['password']) && !preg_match('/([a-z0-9]{32}):([a-zA-Z0-9]+)/', $user['password'])) {
                $user['password_clear'] = $user['password'];
            }
        }

        // Try to detect the password in this POST
        if (empty($user['password_clear'])) {
            $fields = array('password_clear', 'password', 'passwd');
            $jform = JRequest::getVar('jform', array(), 'post');
            foreach ($fields as $field) {
                $password = JRequest::getString($field, '', 'post');
                if (empty($password) && is_array($jform) && !empty($jform[$field])) {
                    $password = $jform[$field];
                }

                if (!empty($password)) {
                    $user['password_clear'] = $password;
                    break;
                }
            }
        }
        
        // Delete unusable fields
        unset($user['id']);
        unset($user['password']);
        unset($user['params']);
        unset($user['userType']);
        unset($user['sendEmail']);
        unset($user['option']);
        unset($user['task']);

        // Delete unusable empty fields
        foreach ($user as $name => $value) {
            if (empty($value)) unset($user[$name]);
        }

        // Encrypt the user-password for transfer through the MageBridge API
        if (isset($user['password_clear'])) {
            if (empty($user['password_clear'])) {
                unset($user['password_clear']);

            } else {
                $user['password_clear'] = MageBridgeEncryptionHelper::encrypt($user['password_clear']);
            }
        }

        // Add the Website ID to this user
        $user['website_id'] = MagebridgeModelConfig::load('website');

        // Add the default customer-group ID to this user (in case we need to create a new user)
        $user['default_customer_group'] = MagebridgeModelConfig::load('customer_group');

        // Add the customer-group ID to this user (based upon groups configured in #__magebridge_usergroups)
        $user['customer_group'] = MageBridgeUserHelper::getMagentoGroupId($user);

        // Make sure events are disabled on the Magento side
        $user['disable_events'] = 1;

        // Add the profile-connector data to this user
        $user = MageBridgeConnectorProfile::modifyUserFields($user);

        // Initalize the needed objects
        $bridge = MageBridgeModelBridge::getInstance();
        $register = MageBridgeModelRegister::getInstance();

        // Build the bridge and fetch the result
        $id = $register->add('api', 'magebridge_user.save', $user);
        $bridge->build();
        $data = $register->getDataById($id);

        return $data;
    }

    /*
     * Method to delete the customer from Magento
     *
     * @param array $user
     * @return array $data
     */
    public function delete($user)
    {
        // Add the Website ID to this user
        $user['website_id'] = MagebridgeModelConfig::load('website');

        // Initalize the needed objects
        $bridge = MageBridgeModelBridge::getInstance();
        $register = MageBridgeModelRegister::getInstance();

        // Build the bridge and fetch the result
        $id = $register->add('api', 'magebridge_user.delete', $user);
        $bridge->build();
        $data = $register->getDataById($id);

        return $data;
    }

    /*
     * Method to login an user into Magento - called from the "User - MageBridge" plugin
     *
     * @param string $email
     * @return array
     */
    public function login($email = null)
    {
        // Backend access
        if (JFactory::getApplication()->isSite() == false) {

            // Check if authentication is enabled for the backend
            if (MagebridgeModelConfig::load('enable_auth_backend') != 1) {
                return false;
            }

            $application_name = 'admin';

        // Frontend access
        } else {

            // Check if authentication is enabled for the frontend
            if (MagebridgeModelConfig::load('enable_auth_frontend') != 1) {
                return false;
            }

            $application_name = 'site';
        }

        // Encrypt values for transfer through the MageBridge API
        $email = MageBridgeEncryptionHelper::encrypt($email);

        // Construct the API-arguments
        $arguments = array(
            'email' => $email,
            'application' => $application_name,
            'disable_events' => 1,
        );

        // Initalize the needed objects
        $bridge = MageBridgeModelBridge::getInstance();
        $register = MageBridgeModelRegister::getInstance();

        // Build the bridge and fetch the result
        $id = $register->add('api', 'magebridge_user.login', $arguments);
        $bridge->build();
        $data = $register->getDataById($id);

        return $data;
    }

    /*
     * Method to authenticate an user - called from the "Authentication - MageBridge" plugin
     *
     * @param string $username
     * @param string $password
     * @param string $application
     * @return array
     */
    public function authenticate($username = null, $password = null, $application = 'site')
    {
        // Encrypt values for transfer through the MageBridge API
        $username = MageBridgeEncryptionHelper::encrypt($username);
        $password = MageBridgeEncryptionHelper::encrypt($password);

        // Construct the API-arguments
        $arguments = array(
            'username' => $username,
            'password' => $password,
            'application' => $application,
            'disable_events' => 1,
        );

        // Initalize the needed objects
        $bridge = MageBridgeModelBridge::getInstance();
        $register = MageBridgeModelRegister::getInstance();

        // Build the bridge and fetch the result
        $register->clean();
        $id = $register->add('authenticate', null, $arguments);
        $bridge->build();
        $data = $register->getDataById($id);

        return $data;
    }

    /*
     * Method to load an user-record by its email address
     *
     * @param string $email
     * @return bool|JUser 
     */
    public function loadByEmail($email = null)
    {
        // Abort if the email is not set
        if (empty($email)) {
            return false;
        }

        // Fetch the user-record for this email-address
        $db = JFactory::getDBO();
        $query = "SELECT id FROM #__users WHERE `email` = ".$db->Quote($email);
        $db->setQuery($query);
        $row = $db->loadObject();

        // If there is no such a row, this user does not exist
        if (empty($row) || !isset($row->id) || !$row->id > 0) {
            return false;
        }

        // Load the user by its user-ID
        $user_id = $row->id;
        $user = JFactory::getUser();
        if ($user->load($user_id) == false) {
            return false;
        }

        return $user;
    }

    /*
     * Helper method to update the current session with the new user-data
     * - Replicate behaviour of Joomla! User Plugin > onLoginUser
     *  
     *
     */
    public function updateSession($user)
    {
        return false;
        /*
        // Check if type is JUser
        if (!is_object($user) || !method_exists($user, 'get')) {
            return false;
        }

        // Make sure all the fields are there
        $user = new JUser($user->id);
        
        // Update the session
        $session = JFactory::getSession();
        $session_user = $session->get('user');
        if ($user->id != $session_user->id) {
            return;
        }

        // Update the session-table
        $table = JTable::getInstance('session');
        $table->load($session->getId());
        $table->guest = 0;
        $table->aid = null; // @todo: Determine the right access-level
        $table->username = $user->get('username');
        $table->userid = $user->get('id');
        $table->usertype = $user->get('usertype');
        $table->gid = $user->get('gid');
        $table->update();

        return true;
        */
    }

    /*
     * Method to check whether an user should be synchronized or not
     *
     * @param JUser $user
     * @return bool
     */
    public function allowSynchronization($user = null, $action = null)
    {
        // Check if we have a valid object
        if ($user instanceof JUser) {

            // Don't synchronize backend-users
            if (!empty($user->usertype) && (stristr($user->usertype, 'administrator') || stristr($user->usertype, 'manager'))) {
                return false;
            }

            return true;
        }

        return false;
    }

    /*
     * Method to postlogin a Magento customer
     *
     * @param string $user_email
     * @param int $user_id
     * @param bool $throw_event
     * @return bool
     */
    public function postlogin($user_email = null, $user_id = null, $throw_event = true)
    {
        // Check if the arguments are set
        if (empty($user_email) && ($user_id > 0) == false) {
            return false;
        }

        // Check if this is the frontend
        $application = JFactory::getApplication();
        if ($application->isSite() == false) {
            return false;
        }

        // Check if this current request is actually a POST-request
        $post = JRequest::get('post');
        if (!empty($post)) {
            return false;
        }
    
        // Fetch the current user
        $user = JFactory::getUser();
        
        // Set the changed-flag
        $changed = false;

        // Check whether the Joomla! ID is different
        if ($user_id > 0 && $user->id != $user_id) {
            $db = JFactory::getDBO();
            $query = "SELECT id FROM #__users WHERE `id` = ".(int)$user_id;
            $db->setQuery($query);
            $row = $db->loadObject();
            if (!empty($row)) {
                $user->load($user_id);
                $changed = true;
            }
        }

        // Double-check whether the Joomla! email is different
        if (!empty($user_email) && $user->email != $user_email) {
            $user = $this->loadByEmail($user_email);
            $changed = true;
        }

        // Check whether the Joomla! ID is set, but guest is still 1
        if ($user->id > 0 && $user->guest == 1) {
            $changed = true;
        }

        // If there is still no valid user, autocreate it
        // @note: Removed because this makes things overly complex
        /*if (!empty($user_email) && empty($user->email)) {
            $data = array(
                'name' => $user_email,
                'username' => $user_email,
                'email' => $user_email,
            );
            $user = $this->create($data);
            $changed = true;
        }*/

        // Do not fire the event when using the onepage-checkout
        if (MageBridgeTemplateHelper::isPage('checkout/onepage') == true && MageBridgeTemplateHelper::isPage('checkout/onepage/success') == false) {
            $throw_event = false;
        }

        if ($changed == true) {
            MageBridgeModelDebug::getInstance()->notice("Postlogin on user = ".$user_email);
        }

        // If there are changes, throw the onLoginUser event
        $throw_event = true;
        if ($throw_event == true && $changed == true && !empty($user)) {

            // Add options for our own user-plugin
            $options = array('disable_bridge' => true, 'action' => 'core.login.site', 'return' => null);

            // Convert the user-object to an array
            $user = JArrayHelper::fromObject($user);

            // Determine the event-name
            $eventName = (MageBridgeHelper::isJoomla15()) ? 'onLoginUser' : 'onUserLogin';

            // Fire the event
            MageBridgeModelDebug::getInstance()->notice( "Firing event ".$eventName );
            JPluginHelper::importPlugin('user');
            JFactory::getApplication()->triggerEvent($eventName, array($user, $options));

        } else {
            // Update the user-session
            if (!empty($user)) {
                $this->updateSession($user);
            }
        }

        return true;
    }
}
