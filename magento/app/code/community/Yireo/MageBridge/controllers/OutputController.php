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

/**
 * MageBridge output tests
 */
class Yireo_MageBridge_OutputController extends Mage_Core_Controller_Front_Action
{
    /**
     * Output test 1
     *
     * @access public
     * @param null
     * @return null
     */
    public function test1Action()
    {
        echo 'test1';
    }

    /**
     * Output test 2
     *
     * @access public
     * @param null
     * @return null
     */
    public function test2Action()
    {
        echo 'test2';
        exit;
    }

    /**
     * Output test 3
     *
     * @access public
     * @param null
     * @return null
     */
    public function test3Action()
    {
        $result = array('test3' => 'yes');
        $this->getResponse()->setBody(Zend_Json::encode($result));
    }

    /**
     * Output test 4
     *
     * @access public
     * @param null
     * @return null
     */
    public function test4Action()
    {
        $this->loadLayout(false);
        $this->renderLayout();
    }

    /**
     * Output test 5
     *
     * @access public
     * @param null
     * @return null
     */
    public function test5Action()
    {
        header('Content-Type: text/xml');
        echo '<html>test5</html>';
        exit;
    }

    /**
     * Output test 6
     *
     * @access public
     * @param null
     * @return null
     */
    public function test6Action()
    {
        if($this->getRequest()->isXmlHttpRequest()) {
            echo 'test6 is xml';
        } else {
            echo 'test6 is not xml';
        }
        exit;
    }

    /**
     * Output test 7
     *
     * @access public
     * @param null
     * @return null
     */
    public function test7Action()
    {
        Mage::getSingleton('core/session')->addError('Test7: Adding an error and then redirect');
        return $this->_redirect('customer/account/login');
    }

    /**
     * Output test 8
     *
     * @access public
     * @param null
     * @return null
     */
    public function test8Action()
    {
        Mage::getSingleton('magebridge/core')->setForcePreoutput(true);
        echo 'test8';
    }

    /**
     * Output test 9
     *
     * @access public
     * @param null
     * @return null
     */
    public function test9Action()
    {
        $url = Mage::getModel('core/url')->getUrl('customer/account');
        $this->getResponse()->setRedirect($url);
    }
}
