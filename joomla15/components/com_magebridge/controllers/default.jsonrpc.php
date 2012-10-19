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

// Include the parent controller
jimport( 'joomla.application.component.controller' );

/**
 * MageBridge JSON-RPC Controller 
 * Example: index.php?option=com_magebridge&view=jsonrpc&task=call
 *
 * @package MageBridge
 */
class MageBridgeControllerJsonrpc extends JController
{
    /*
     * @var object Zend_Json_Server
     */
    private $server = null;

    /*
     * Method to make a JSON-RPC call
     */
    public function call()
    {
        // Manually configure PHP settings
        ini_set('display_errors', 1);

        // Initialize the JSON-RPC server
        $this->init();

        // Fetch the parameters for authentication
        $params = $this->server->getRequest()->getParams();
        if (!isset($params['api_auth'])) {
            return $this->error('No authentication data', 403);
        }

        // Authenticate the API-credentials
        if ($this->authenticate($params['api_auth']) == false) {
            return $this->error('Authentication failed', 401);
        }

        // Remove the API-credentials from the parameters
        unset($params['api_auth']);
        $params = array('params' => $params);
        
        $this->server->getRequest()->setParams($params);

        // Make the actual call
        $this->server->handle($this->server->getRequest());
        return $this->close();
    }

    /*
     * Method to display a listing of all API-methods
     */
    public function servicemap()
    {
        $this->init();
        $smd = $this->server->getServiceMap();

        header('Content-Type: application/json');
        echo $smd;

        return $this->close();
    }

    /*
     * Helper method to get the JSON-RPC server object
     *
     * @param null
     * @return null
     */
    private function init()
    {
        // Include the MageBridge API
        $library = JPATH_SITE.'/components/com_magebridge/libraries';
        require_once $library.'/api.php';

        // Set the include_path to include the Zend Framework
        if (!defined( 'ZEND_PATH')) {
            set_include_path($library.PATH_SEPARATOR.get_include_path());
        } else {
            set_include_path(ZEND_PATH.PATH_SEPARATOR.get_include_path());
        }

        // Include the Zend Framework classes
        require_once 'Zend/Json/Server.php';
        require_once 'Zend/Json/Server/Error.php';

        $this->server = new Zend_Json_Server();
        $this->server->setClass('MageBridgeApi');
    }

    /*
     * Helper method to close this call 
     *
     * @param null
     * @return null
     */
    private function close()
    {
        $application = JFactory::getApplication();
        $application->close();
    }

    /*
     * Helper method to authenticate this API call
     *
     * @param string $message
     * @param int $code
     * @return null
     */
    private function error($message, $code = '500')
    {
        // Create a new error-object
        $error = new Zend_Json_Server_Error();
        $error->setCode($code);
        $error->setMessage($message);

        // Add the error to the current response
        $response = $this->server->getResponse();
        $response->setError($error);

        // Set the response 
        $this->server->setResponse($response);
        $this->server->handle();

        // Set the HTTP-header
        @header('HTTP/1.1 '.$code.' '.$message);
        @header('Status: '.$code.' '.$message);

        // Close the application
        $application = JFactory::getApplication();
        $application->close();

    }

    /*
     * Helper method to authenticate this API call
     *
     * @param array $auth
     * @return bool
     */
    private function authenticate($auth)
    {
        if (!empty($auth) && !empty($auth['api_user']) && !empty($auth['api_key'])) {

            $api_user = MageBridgeEncryptionHelper::decrypt($auth['api_user']);
            $api_key = MageBridgeEncryptionHelper::decrypt($auth['api_key']);

            if ($api_user != MagebridgeModelConfig::load('api_user')) { 
                MageBridgeModelDebug::getInstance()->error( 'JSON-RPC: API-authentication failed: Username "'.$api_user.'" did not match');
            } else if ($api_key != MagebridgeModelConfig::load('api_key')) {
                MageBridgeModelDebug::getInstance()->error( 'JSON-RPC: API-authentication failed: Key "'.$api_key.'" did not match');
            } else {
                return true;
            }
        } 
        return false;
    }
}
