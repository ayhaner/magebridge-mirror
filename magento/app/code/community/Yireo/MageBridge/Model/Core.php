<?php
/**
 * MageBridge
 *
 * @author Yireo
 * @package MageBridge
 * @copyright Copyright 2012
 * @license Yireo EULA (www.yireo.com)
 * @link http://www.yireo.com
 */

/*
 * MageBridge model serving as main bridge-resources which primarily handles the Magento configuration
 */
class Yireo_MageBridge_Model_Core 
{
    /*
     * Bridge-request
     */
    protected $_request = array();

    /*
     * Bridge-request
     */
    protected $_response = array();

    /*
     * Meta-data
     */
    protected $_meta = array();

    /*
     * Magento configuration
     */
    protected $_mage_config = array();

    /*
     * System events
     */
    protected $_events = array();

    /*
     * Flag to enable event forwarding
     */
    protected $_enable_events = true;

    /*
     * Flag for forcing preoutput
     */
    protected $_force_preoutput = false;

    /*
     * Initialize the bridge-core
     *
     * @access public
     * @param array $meta
     * @param array $request
     * @return bool
     */
    public function init($meta = null, $request = null)
    {
        // Set meta and request
        $this->_meta = $meta;
        $this->_request = $request;

        // Fill the response with the current request
        $this->setResponseData($request);

        // Decrypt everything that needs decrypting
        $this->_meta['api_user'] = $this->getMetaData('api_user');
        $this->_meta['api_key'] = $this->getMetaData('api_key');

        //Mage::getSingleton('magebridge/debug')->trace('Dump of meta', $this->_meta);
        //Mage::getSingleton('magebridge/debug')->trace('Dump of GET', $_GET);
        //Mage::getSingleton('magebridge/debug')->trace('Dump of response', $this->_response);
        Mage::getSingleton('magebridge/debug')->trace('HTTP referer', (isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : null );

        // Overwrite the default error-handling by routing all magebridge/debug
        set_error_handler('Yireo_MageBridge_ErrorHandler');
        set_exception_handler('Yireo_MageBridge_ExceptionHandler');

        // Try to initialize the session
        try {
            $session = Mage::getSingleton('core/session', array('name'=>'frontend'));
            $session->start();
        } catch( Exception $e ) {
            Mage::getSingleton('magebridge/debug')->error('Unable to instantiate core/session: '.$e->getMessage());
            return false;
        }

        // Set the magebridge-URLs
        $this->setConfig();

        // Post-login a Joomla! user
        $joomla_user_email = $this->getMetaData('joomla_user_email');
        if(!empty($joomla_user_email) && Mage::getModel('customer/session')->isLoggedIn() == false) {
            $data = array(
                'email' => $joomla_user_email,
                'application' => 'site',
                'disable_events' => true,
            );  
            Mage::getModel('magebridge/user_api')->login($data);
        }

        // Set the current store of this request
        try {
            Mage::app()->setCurrentStore(Mage::app()->getStore($this->getStore()));
        } catch( Exception $e ) {
            Mage::getSingleton('magebridge/debug')->error('Failed to intialize store "'.$this->getStore().'":'.$e->getMessage());
            // Do not return, but just keep on going with the default configuration
        }

        // Manual hack to set the right continue-shopping URL to the HTTP_REFERER, even if it isn't "internal"
        if(Mage::getStoreConfig('magebridge/settings/continue_shopping_to_previous') == 1) {
            if(isset($_SERVER['HTTP_REFERER']) && !empty($_SERVER['HTTP_REFERER'])) {
                if(strstr($this->getRequestUrl(), 'checkout/cart')) {
                    Mage::getSingleton('checkout/session')->setContinueShoppingUrl($_SERVER['HTTP_REFERER']);
                } elseif(strstr($this->getRequestUrl(), 'checkout/onepage/success')) {
                    Mage::getSingleton('customer/session')->setNextUrl($_SERVER['HTTP_REFERER']);
                }
            }
        }

        // Manual hack to set the right customer-redirect URL
        if(strstr($this->getRequestUrl(), 'customer/account/loginPost') 
            && Mage::getStoreConfig('customer/startup/redirect_dashboard') == 0
            && preg_match('/\/referer\/([a-A-Z0-9\,]+)\//', $this->getRequestUrl()) == false
            ) {
            $url_referer = base64_decode(Mage::app()->getRequest()->getParam('referer'));
            if(empty($url_referer)) {
                header('X-MageBridge-Location-Customer: '.Mage::app()->getStore()->getBaseUrl());
            }
        }

        return true;
    }

    /*
     * Method to change the regular Magento configuration as needed
     *  
     * @access public
     * @param null
     * @return bool
     */
    public function setConfig()
    {
        // To start with, save the meta data
        $this->saveMetaData();

        // Fetch a list of all stores 
        $stores = Mage::app()->getStores();
        $websiteId = $this->getMetaData('website');

        // Loop through the stores to modify data
        foreach($stores as $store) {

            // Do not override stores outside this website
            if($store->getWebsiteId() != $websiteId) {
                continue;
            }

            //Mage::getSingleton('magebridge/debug')->notice('Override store configuration "'.$store->getCode().'"');
            try {

                $config_values = array();

                // If URL-modification is disabled, exit
                if($this->getMetaData('modify_url') == 1) {

                    // Get the current store
                    //Mage::getSingleton('magebridge/debug')->notice('Set URLs of store "'.$store->getName().'" to '.$this->getMageBridgeSefUrl());

                    // Collect the unmodified original URLs from the Configuration
                    $urls = array();
                    $urls['web/unsecure/base_url'] = $store->getConfig('web/unsecure/base_url');
                    $urls['web/unsecure/base_link_url'] = $store->getConfig('web/unsecure/base_url');
                    $urls['web/unsecure/base_media_url'] = $store->getConfig('web/unsecure/base_media_url');
                    $urls['web/unsecure/base_skin_url'] = $store->getConfig('web/unsecure/base_skin_url');
                    $urls['web/unsecure/base_js_url'] = $store->getConfig('web/unsecure/base_js_url');
                    $urls['web/secure/base_url'] = $store->getConfig('web/secure/base_url');
                    $urls['web/secure/base_link_url'] = $store->getConfig('web/secure/base_url');
                    $urls['web/secure/base_media_url'] = $store->getConfig('web/secure/base_media_url');
                    $urls['web/secure/base_skin_url'] = $store->getConfig('web/secure/base_skin_url');
                    $urls['web/secure/base_js_url'] = $store->getConfig('web/secure/base_js_url');

                    // Store the unmodified URLs in the registry
                    if(Mage::registry('original_urls') == null) {
                        Mage::register('original_urls', $urls);
                    }

                    // Proxy static content as well
                    /*
                    if($store->getConfig('magebridge/settings/bridge_all') == 1) {
                        $proxy = 'index.php?option=com_magebridge&view=proxy&url=';
                        $base_media_url = str_replace($base_url, $proxy, $base_media_url);
                        $base_skin_url = str_replace($base_url, $proxy, $base_skin_url);
                        $base_js_url = str_replace($base_url, $proxy, $base_js_url);
                    }
                    */

                    // Set the main URL
                    $urls['web/unsecure/base_link_url'] = $this->getMageBridgeSefUrl();
                    $urls['web/secure/base_link_url'] = $this->getMageBridgeSefUrl();

                    // Correct HTTP and HTTPS URLs in all URLs
                    $has_ssl = Mage::getSingleton('magebridge/core')->getMetaData('has_ssl');
                    foreach($urls as $index => $url) {
                        if($has_ssl == true) {
                            $urls[$index] = preg_replace('/^http:/', 'https:', $url);
                        } else {
                            $urls[$index] = preg_replace('/^https:/', 'http:', $url);
                        }
                    }

                    // Rewrite of configuration values
                    $config_values['web/unsecure/base_url'] = $urls['web/unsecure/base_url'];
                    $config_values['web/unsecure/base_link_url'] = $urls['web/unsecure/base_link_url'];
                    $config_values['web/unsecure/base_media_url'] = $urls['web/unsecure/base_media_url'];
                    $config_values['web/unsecure/base_skin_url'] = $urls['web/unsecure/base_skin_url'];
                    $config_values['web/unsecure/base_js_url'] = $urls['web/unsecure/base_js_url'];
                    $config_values['web/secure/base_url'] = $urls['web/secure/base_url'];
                    $config_values['web/secure/base_link_url'] = $urls['web/secure/base_link_url'];
                    $config_values['web/secure/base_media_url'] = $urls['web/secure/base_media_url'];
                    $config_values['web/secure/base_skin_url'] = $urls['web/secure/base_skin_url'];
                    $config_values['web/secure/base_js_url'] = $urls['web/secure/base_js_url'];
                }

                // Apply other settings
                $config_values['web/seo/use_rewrites'] = 1;
                $config_values['web/session/use_remote_addr'] = 0;
                $config_values['web/session/use_http_via'] = 0;
                $config_values['web/session/use_http_x_forwarded_for'] = 0;
                $config_values['web/session/use_http_user_agent'] = 0;
                $config_values['web/cookie/cookie_domain'] = '';

                // Rewrite specific values
                if($this->getMetaData('joomla_conf_lifetime') > 0) $config_values['web/cookie/cookie_lifetime'] = $this->getMetaData('joomla_conf_lifetime');
                if($this->getMetaData('customer_group') > 0) $config_values['customer/create_account/default_group'] = $this->getMetaData('customer_group');
                if(strlen($this->getMetaData('theme')) > 0) $config_values['design/theme/default'] = $this->getMetaData('theme');

                // Rewrite these values for all stores
                foreach($config_values as $path => $value) {
                    if(method_exists($store, 'overrideCachedConfig')) {
                        $store->overrideCachedConfig($path, $value);
                    }
                }

                // Make sure we do not use SID= in the URL
                Mage::getModel('core/url')->setUseSession(false);
                Mage::getModel('core/url')->setUseSessionVar(true);
                //Mage::getSingleton('magebridge/debug')->notice('URL test 1: '.Mage::app()->getRequest()->getHttpHost());
                //Mage::getSingleton('magebridge/debug')->notice('URL test 2: '.Mage::helper('core/url')->getCurrentUrl());
                //Mage::getSingleton('magebridge/debug')->notice('URL test 3: '.Mage::helper('catalog/product')->getProductUrl(17));
                //Mage::getSingleton('magebridge/debug')->notice('URL test 4: '.$this->getRequestUrl());

            } catch(Exception $e) {
                Mage::getSingleton('magebridge/debug')->error('Unable to modify configuration: '.$e->getMessage());
            }
        }

        return true;
    }

    /*
     * Method to set the current URL to the MageBridge SEF URL
     *
     * @access public
     * @param null
     * @return bool
     */
    public function setSefUrl()
    {
        // Modify the configuration values
        $config_values = array(
            'web/unsecure/base_url' => $this->getMageBridgeSefUrl(),
            'web/unsecure/base_link_url' => $this->getMageBridgeSefUrl(),
            'web/secure/base_url' => $this->getMageBridgeSefUrl(),
            'web/secure/base_link_url' => $this->getMageBridgeSefUrl(),
        );

        // Rewrite the configuration
        $store = Mage::app()->getStore($this->getStore());
        foreach($config_values as $path => $value) {
            if(method_exists($store, 'overrideCachedConfig')) {
                $store->overrideCachedConfig($path, $value);
            }
        }

        return true;
    }

    /*
     * Method to save metadata in the Magento Configuration
     *
     * @access public
     * @param null
     * @return null
     */
    public function saveMetaData()
    {
        // List of keys (meta => conf)
        $keys = array(
            'api_url' => 'api_url',
            'api_user' => 'api_user',
            'api_key' => 'api_key',
        );

        // Check the Joomla! settings
        $refresh_cache = false;
        foreach($keys as $meta_key => $key) {

            $rt = $this->saveConfig($key, $this->getMetaData($meta_key), 'default', 0);
            if($rt == true) $refresh_cache = true;

            $rt = $this->saveConfig($key, $this->getMetaData($meta_key), 'websites', $this->getMetaData('website'));
            if($rt == true) $refresh_cache = true;
        }

        // Refresh the cache
        if($refresh_cache == true && Mage::app()->useCache('config') && Mage::helper('magebridge')->useApiDetect() == true) {
            Mage::getSingleton('magebridge/debug')->notice('Refresh configuration cache');
            Mage::getConfig()->removeCache();
        }
    }

    /*
     * Method to cache API-details in the Magento configuration
     *
     * @access public
     * @param string $key
     * @param string $value
     * @param string $scope
     * @param int $scopeId
     * @return bool
     */
    public function saveConfig($key, $value, $scope, $scopeId, $override = false)
    {
        // Do not save empty values
        if(empty($value)) return false;

        // Make sure the scope-ID is an integer
        $scopeId = (int)$scopeId;

        // Skip the Admin-scope
        if($scope == 'websites' && $scopeId == 0) return false;

        // Fetch the current value
        if($scope == 'default') {
            $current_value = (string)Mage::getConfig()->getNode('magebridge/settings/'.$key, 'default');
        } else {
            $current_value = (string)Mage::getConfig()->getNode('magebridge/settings/'.$key, $scope, $scopeId);
        }
        
        // Determine whether to save the current value
        $save = false;
        if(empty($current_value)) {
            $save = true;

        } elseif(Mage::helper('magebridge')->useApiDetect() == true && $scope != 'default') {
            if($key == 'api_url' && preg_replace('/^(http|https)\:/', '', $current_value) != preg_replace('/^(http|https)\:/', '', $value)) {
                Mage::getSingleton('magebridge/debug')->notice('New API-value for "'.$key.'": "'.$current_value.'"; previously "'.$value.'"');
                $save = true;
            } elseif($key != 'api_url' && $current_value != $value) {
                Mage::getSingleton('magebridge/debug')->notice('New API-value for "'.$key.'": "'.$current_value.'"; previously "'.$value.'"');
                $save = true;
            }
        }

        // Save the value
        if($save == true) {
            Mage::getSingleton('magebridge/debug')->notice('saveConfig: magebridge/settings/'.$key.' = '.$value.' ['.$scope.'/'.$scopeId.' ]');
            Mage::getConfig()->saveConfig('magebridge/settings/'.$key, $value, $scope, $scopeId);
            return true;
        }

        return false;
    }

    /*
     * Method to get the currently defined API-user
     *
     * @access public
     * @param null
     * @return Mage_Api_Model_User
     */
    public function getApiUser()
    {
        $api_user_id = Mage::getStoreConfig('magebridge/settings/api_user_id');

        if(!$api_user_id > 0) {
            $collection = Mage::getResourceModel('api/user_collection');
            foreach($collection as $user) {
                $api_user_id = $user->getId();
                break;
            }
        }

        $api_user = Mage::getModel('api/user')->load($api_user_id);
        return $api_user;
    }

    /*
     * Method to authenticate usage of the MageBridge API
     *
     * @access public
     * @param null
     * @return null
     */
    public function authenticate()
    {
        // Fetch the variables from the meta-data
        $api_session = $this->getMetaData('api_session');
        $api_user = $this->_meta['api_user'];
        $api_key = $this->_meta['api_key'];

        // If the API-session matches, we don't need authenticate any more
        if($api_session == md5(session_id().$api_user.$api_key)) {
            return true;
        }

        // If we still need authentication, authenticate against the Magento API-class
        try {
            $api = Mage::getModel('api/user');
            if( $api->authenticate($api_user, $api_key) == true ) {
                $this->setMetaData('api_session', md5(session_id().$api_user.$api_key));
                return true;
            }

        } catch(Exception $e) {
            Mage::getSingleton('magebridge/debug')->error('Exception while authorizing: '.$e->getMessage());
        }
        return false;
    }

    /*
     * Method to catch premature output in case of AJAX-stuff
     *
     * @access public
     * @param null
     * @return bool
     */
    public function preoutput()
    {
        // Match configured direct output
        $direct_output = Mage::helper('magebridge')->getDirectOutputUrls();
        if(!empty($direct_output)) {
            foreach($direct_output as $url) {
                if(strstr($this->getRequestUrl(), $url)) {
                    Mage::getSingleton('magebridge/core')->getController(false);
                    return true;
                }
            }
        }

        // Check for URLs that look like AJAX URLs
        $request = Mage::app()->getRequest();
        if(stristr($request->getControllerName(), 'ajax') || stristr($request->getActionName(), 'ajax') || stristr($this->getRequestUrl(), 'ajax')) {
            Mage::getSingleton('magebridge/core')->getController(false);
            return true;
        }

        // Check if preoutput is forced manually
        if($this->getForcePreoutput() == true) {
            Mage::getSingleton('magebridge/core')->getController(false);
            return true;
        }

        // Preoutput when MageBridge has set the AJAX-flag (and there is no POST)
        if($this->getMetaData('ajax') == 1 && ($this->getMetaData('post') == null && empty($_POST))) {
            Mage::getSingleton('magebridge/core')->getController(false);
            return true;
        }

        // Do NOT ever preoutput in the Joomla! backend
        if($this->getMetaData('app') == 1) {
            return false;
        }

        // Initialize the frontcontroller
        $controller = Mage::getSingleton('magebridge/core')->getController();

        // Start the buffer and fetch the output from Magento
        $body = Mage::app()->getResponse()->getBody();
        if(!empty($body)) {
            $controller->getResponse()->clearBody();
            return true;
        }

        // Determine whether to preoutput compare links
        if(strstr($this->getRequestUrl(), 'catalog/product_compare/index')) {
            if(Mage::app()->getStore()->getConfig('magebridge/settings/preoutput_compare') == 1) {
                echo $controller->getAction()->getLayout()->getOutput();
                return true;
            } else {
                return false;
            }
        }

        // Determine whether to preoutput gallery links
        if(strstr($this->getRequestUrl(), 'catalog/product/gallery')) {
            if(Mage::app()->getStore()->getConfig('magebridge/settings/preoutput_gallery') == 1) {
                echo $controller->getAction()->getLayout()->getOutput();
                return true;
            } else {
                return false;
            }
        }

        // Scan for modified HTTP-headers
        foreach($controller->getResponse()->getHeaders() as $header) {
            if(strtolower($header['name']) == 'content-type' && strstr($header['value'], 'text/xml')) {
                echo $controller->getAction()->getLayout()->getOutput();
                return true;
            }
        }

        /*// Get the current handles
        $handles = $controller->getAction()->getLayout()->getUpdate()->getHandles();

        // Check if there are any handles at all
        if(empty($handles)) {
            echo $controller->getAction()->getLayout()->getOutput();
            return true;
        }*/
    
        // Do not return direct output
        return false;
    }

    /*
     * Method to output the regular bridge-data through JSON
     *
     * @access public
     * @param bool $complete
     * @return bool
     */
    public function output($complete = true)
    {
        if($complete) {
            $this->closeBridge();
        } else {
            $this->addResponseData('meta', array(
                'type' => 'meta',
                'data' => array(
                    'state' => $this->getMetaData('state'),
                    'extra' => $this->getMetaData('extra'),
                )
            ));
        }

        if($this->getMetaData('debug')) {
            $debug = Mage::getSingleton('magebridge/debug')->getData();
            if(!empty($debug)) {
                $this->addResponseData('debug', array(
                    'type' => 'debug',
                    'data' => $debug,
                ));
            }
        }

        // Output the response
        $data = json_encode($this->getResponseData());
        return $data;
    }
    
    /*
     * Method to close the bridge and add the final data
     * 
     * @access public
     * @param null
     * @return null
     */
    public function closeBridge()
    {
        // Add extra information
        $this->setMetaData('magento_session', session_id());
        $this->setMetaData('magento_version', Mage::getVersion());

        // Append customer-data
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $this->setMetaData('magento_customer', array(
            'fullname' => $customer->getName(),
            'username' => $customer->getEmail(),
            'email' => $customer->getEmail(),
            'hash' => $customer->getPasswordHash(),
        ));
    
        // Append Magento-data
        $this->setMetaData('magento_config', $this->getMageConfig());

        // Add events to the response
        $events = $this->getEvents();
        if(!empty($events)) {
            $this->addResponseData('events', array(
                'type' => 'events',
                'data' => $events,
            ));
        }

        // Add metadata to the response
        $metadata = $this->getMetaData();
        if(!empty($metadata)) {
            $this->addResponseData('meta', array(
                'type' => 'meta',
                'data' => $metadata,
            ));
        }
    }

    /*
     * Helper-function to parse Magento output for usage in Joomla!
     * 
     * @access public
     * @param string $string
     * @return string
     */
    public function parse($string)
    {
        $string = str_replace(Mage::getUrl(), $this->getMageBridgeUrl(), $string);
        return $string;
    }

    /*
     * Return information on the current Magento configuration
     *
     * @access public
     * @param null
     * @return string
     */
    public function getMageConfig()
    {
        // Construct extra data
        $store = Mage::app()->getStore($this->getStore());
        $category = Mage::registry('current_category');
        $product = Mage::registry('current_product');
        $data = array(
            'catalog/seo/product_url_suffix' => $store->getConfig('catalog/seo/product_url_suffix'),
            'catalog/seo/category_url_suffix' => $store->getConfig('catalog/seo/category_url_suffix'),
            'admin/security/session_cookie_lifetime' => $store->getConfig('admin/security/session_cookie_lifetime'),
            'web/cookie/cookie_lifetime' => $store->getConfig('web/cookie/cookie_lifetime'),
            'customer/email' => Mage::getModel('customer/session')->getCustomer()->getEmail(),
            'customer/joomla_id' => Mage::helper('magebridge/user')->getCurrentJoomlaId(),
            'customer/magento_id' => Mage::getModel('customer/session')->getCustomerId(),
            'customer/magento_group_id' => Mage::getModel('customer/session')->getCustomer()->getGroupId(),
            'backend/path' => $this->getAdminPath(),
            'store_name' => Mage::app()->getStore()->getName(),
            'base_js_url' => Mage::getBaseUrl('js'),
            'base_media_url' => Mage::getBaseUrl('media'),
            'root_template' => $this->getRootTemplate(),
            'root_category' => Mage::app()->getStore($this->getStore())->getRootCategoryId(),
            'current_category_id' => (!empty($category)) ? $category->getId() : 0,
            'current_category_path' => (!empty($category)) ? $category->getPath() : 0,
            'current_product_id' => (!empty($product)) ? $product->getId() : 0,
            'referer' => Mage::app()->getRequest()->getServer('HTTP_REFERER'),
            'request' => $this->getRequestUrl(),
        );

        // Append extra data
        foreach($data as $name => $value) {
            $this->_mage_config[$name] = $value;
        }

        return $this->_mage_config;
    }

    /*
     * Set Magento config-data to return through the bridge
     *
     * @access public
     * @param string $name
     * @param string $value
     * @return null
     */
    public function setMageConfig($name, $value)
    {
        $this->_mage_config[$name] = $value;
    }

    /*
     * Return the current URL
     *
     * @access public
     * @param null
     * @return string
     */
    public function getRequestUrl()
    {
        return preg_replace('/^\//', '', Mage::getModel('core/url')->getRequest()->getRequestUri());
    }

    /*
     * Return the path to the Magento Admin Panel
     *
     * @access public
     * @param null
     * @return string
     */
    public function getAdminPath()
    {
        $routeName = 'adminhtml';
        $route = Mage::app()->getFrontController()->getRouterByRoute($routeName);
        $backend = $route->getFrontNameByRoute($routeName);
        return $backend;
    }

    /*
     * Return the current page layout for the Magento theme
     *
     * @access public
     * @param null
     * @return string
     */
    public function getRootTemplate()
    {
        $block = Mage::getModel('magebridge/block')->getBlock('root');
        $root_block = 'none';
        if(!empty($block)) {
            $root_block = $block->getTemplate();
        }
        return $root_block;
    }

    /*
     * Helper-method to get the Front-controller
     *
     * @access public
     * @param boolean $norender
     * @return object
     */
    public static function getController($norender = true)
    {
        static $controller;
        if(empty($controller)) {
            $controller = Mage::app()->getFrontController()
                ->setNoRender($norender)
                ->dispatch()
            ;

            // Preset some HTTP-headers
            header('X-MageBridge-Customer: '.Mage::getModel('customer/session')->getCustomer()->getEmail());
            // Note: Do not use the Magento API for this, because it is not used by magebridge.class.php > output
        }
        return $controller;
    }

    /*
     * Helper-method to get the bridge-request
     *
     * @access public
     * @param null
     * @return array
     */
    public function getRequestData()
    {
        return $this->_request;
    }

    /*
     * Helper-method to get the bridge-response
     *
     * @access public
     * @param null
     * @return array
     */
    public function getResponseData()
    {
        return $this->_response;
    }

    /*
     * Helper-method to set the bridge-response
     *
     * @access public
     * @param array $data
     * @return null
     */
    public function setResponseData($data)
    {
        $this->_response = $data;
    }

    /*
     * Helper-method to add some data to the bridge-response
     *
     * @access public
     * @param string $name
     * @param array $data
     * @return null
     */
    public function addResponseData($name = null, $data)
    {
        $this->_response[$name] = $data;
        return true;
    }

    /*
     * Helper-method to get the meta-data
     *
     * @access public
     * @param string $name
     * @return mixed
     */
    public function getMetaData($name = null)
    {
        if($name == null) {
            return $this->_meta;
        } elseif(isset($this->_meta[$name])) {
            return $this->decrypt($this->_meta[$name]);
        } else {
            return null;
        }
    }

    /*
     * Helper-method to set the meta-data
     *
     * @access public
     * @param string $name
     * @param mixed $value
     * @return null
     */
    public function setMetaData($name = null, $value = null)
    {
        $this->_meta[$name] = $value;
        return null;
    }

    /*
     * Helper-method to get the flag for preoutput-forcing
     *
     * @access public
     * @param null
     * @return array
     */
    public function getForcePreoutput()
    {
        return $this->_force_preoutput;
    }

    /*
     * Helper-method to set the flag for preoutput-forcing
     *
     * @access public
     * @param null
     * @return array
     */
    public function setForcePreoutput($force_preoutput)
    {
        $this->_force_preoutput = $force_preoutput;
    }

    /*
     * Helper-method to get the system events from the session and clean up afterwards
     *
     * @access public
     * @param null
     * @return array
     */
    public function getEvents()
    {
        $events = Mage::getSingleton('magebridge/session')->getEvents();;
        Mage::getSingleton('magebridge/session')->cleanEvents();
        return $events;
    }

    /*
     * Helper-method to set the system events
     *
     * @access public
     * @param array
     * @return null
     */
    public function setEvents($events)
    {
        $this->_events = $events;
        return null;
    }

    /*
     * Helper-method to get the Joomla! URL from the meta-data
     *
     * @access public
     * @param null
     * @return string
     * @deprecated
     */
    public function getMageBridgeUrl()
    {
        return $this->getMageBridgeSefUrl();
    }

    /*
     * Helper-method to get the Joomla! SEF URL from the meta-data
     *
     * @access public
     * @param null
     * @return string
     */
    public function getMageBridgeSefUrl()
    {
        if($this->getMetaData('app') == 1) { 
            return $this->getMetaData('joomla_url');
        } else {
            return $this->getMetaData('joomla_sef_url');
        }
    }

    /*
     * Helper-method to get the requested store-name from the meta-data
     *
     * @access public
     * @param null
     * @return string
     */
    public function getStore()
    {
        return $this->getMetaData('store');
    }

    /*
     * Return the configured license key
     *
     * @access public
     * @param null
     * @return string
     */
    public function getLicenseKey()
    {
        return Mage::getStoreConfig('magebridge/settings/license_key');
    }

    /*
     * Return the current session ID
     *
     * @access public
     * @param null
     * @return string
     */
    public function getMageSession()
    {
        return session_id();
    }

    /*
     * Encrypt data for security
     *
     * @access public
     * @param null
     * @return string
     */
    public function encrypt($data)
    {
        return Mage::helper('magebridge/encryption')->encrypt($data);
    }

    /*
     * Decrypt data after encryption
     *
     * @access public
     * @param null
     * @return string
     */
    public function decrypt($data)
    {
        return Mage::helper('magebridge/encryption')->decrypt($data);
    }

    /*
     * Determine whether event forwarding is enabled
     *
     * @access public
     * @param null
     * @return bool
     */
    public function isEnabledEvents()
    {
        return $this->_enable_events;
    }

    /*
     * Disable event forwarding
     *
     * @access public
     * @param null
     * @return bool
     */
    public function disableEvents()
    {
        $this->_enable_events = false;
        return $this->_enable_events;
    }
}
