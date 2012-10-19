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
 * MageBridge rewrite of the default breadcrumbs-block
 */
class Yireo_MageBridge_Block_Breadcrumbs extends Mage_Page_Block_Html_Breadcrumbs
{
    /*
     * Extra helper method to get the current breadcrumbs
     *
     * @access public
     * @param null
     * @return array
     */
    public function getCrumbs()
    {
        return $this->_crumbs;
    }
}
