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
 * MageBridge API-model for product resources
 */
class Yireo_MageBridge_Model_Product_Api extends Mage_Catalog_Model_Api_Resource
{
    /**
     * Search for products 
     *
     * @access public
     * @param array $options
     * @return array
     */
    public function search($options = array())
    {
        $collection = Mage::getModel('magebridge/search')->getResult($options['text']);

        $result = array();
        if(empty($collection)) {
            return $result;
        }

        $i = 0;
        foreach($collection as $item) {

            if($i == $options['search_limit']) break;
            $product = Mage::getModel('catalog/product')->load($item->getId());
                
            $product_price_tax = Mage::helper('tax')->getProductPrice($product);

            $result[] = array( // Basic product data
                'product_id'        => $product->getId(),
                'sku'               => $product->getSku(),
                'name'              => $product->getName(),
                'description'       => $product->getDescription(),
                'short_description' => $product->getShortDescription(),
                'label'             => htmlentities($product->getName()),
                'author'            => $product->getAuthor(),
                'url_key'           => $product->getUrlKey(),
                'url_path'          => $product->getUrlPath(),
                'url_store'         => $product->getUrlInStore(),
                'url'               => $product->getProductUrl(false),
                'category_ids'      => $product->getCategoryIds(),
                'thumbnail'         => $product->getThumbnailUrl(),
                'image'             => $product->getImageUrl(),
                'small_image'       => $product->getSmallImageUrl(),
                'price'             => Mage::app()->getStore()->formatPrice($product->getPrice()),
                'price_tax'         => Mage::app()->getStore()->formatPrice($product_price_tax),
                'price_tax_raw'     => $product_price_tax,
                'price_raw'         => $product->getPrice(),
                'price_tier'        => $product->getTierPrice(1),
                'special_price'     => Mage::app()->getStore()->formatPrice($product->getSpecialPrice()),
                'special_price_raw' => $product->getSpecialPrice(),
                'special_from_date' => $product->getSpecialFromDate(),
                'special_to_date'   => $product->getSpecialToDate(),
                'is_active'         => 1,
            );

            $i++;
        }

        return $result;
    }

    /**
     * Retrieve list of products with basic info (id, sku, type, set, name)
     *
     * @access public
     * @param array $filters
     * @return array
     */
    public function items($arguments = null)
    {
        // Initialize arguments with default values
        if(!isset($arguments['website'])) $arguments['website'] = Mage::app()->getStore()->getWebsiteId();
        if(!isset($arguments['store'])) $arguments['store'] = Mage::app()->getStore()->getStoreId();

        // Initializing caching
        if(Mage::app()->useCache('collections')) {
            $cacheId = 'magebridge_product_api_items_'.md5(serialize($arguments));
            if($cache = Mage::app()->loadCache($cacheId)) {
                $results = unserialize($cache);
                if(!empty($results)) return $results;
            }
        }

        // Fetch the collection
        $collection = $this->getCollection($arguments);

        // Loop through the collection
        $result = array();
        foreach ($collection as $product) {

            if(!empty($arguments['custom_image_size'])) {
                $product->setCustomImageSize($arguments['custom_image_size']);
            }

            // Correct the price for Grouped Products, by grabbing the first price (credits to Luke Collymore)
            if($product->getTypeId() == Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
                $childProductIds = $product->getTypeInstance()->getChildrenIds($product->getId());
                $prices = array();
                foreach ($childProductIds as $ids) {
                    foreach ($ids as $id) {
                        $childProduct = Mage::getModel('catalog/product')->load($id);
                        $prices[] = $childProduct->getPriceModel()->getPrice($childProduct);
                    }
                }
                sort($prices);
                $product->setPrice(array_shift($prices));
            }

            $result[] = Mage::helper('magebridge/product')->export($product, $arguments);
        }

        // Save to cache
        if(Mage::app()->useCache('collections')) {
            Mage::app()->saveCache(serialize($result), $cacheId, array('collections'), 86400);
        }

        return $result;
    }

    /**
     * Retrieve the count of a listing of products
     *
     * @access public
     * @param array $arguments
     * @return int
     */
    public function count($arguments = null)
    {
        $collection = $this->getCollection($arguments);
        return $collection->count();
    }

    /**
     * Retrieve a product collection
     *
     * @access public
     * @param array $arguments
     * @return collection
     */
    protected function getCollection($arguments = null)
    {
        // Initialize arguments with default values
        if(!isset($arguments['website'])) $arguments['website'] = Mage::app()->getStore()->getWebsiteId();
        if(!isset($arguments['store'])) $arguments['store'] = Mage::app()->getStore()->getStoreId();

        // Set the visibility
        if(isset($arguments['visibility'])) {
            $visibility = $arguments['visibility'];
        } else {
            $visibility = array(
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
                Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            );
        }

        // Get the product-collection
        $collection = Mage::getModel('catalog/product')->getCollection()
            ->addAttributeToFilter('visibility', $visibility)
            ->addAttributeToSelect('*')
            ->addFieldToFilter('status', 1)
            ->addWebsiteFilter($arguments['website'])
            ->addStoreFilter($arguments['store'])
        ;

        // Add the minimal price
        if(!isset($arguments['minimal_price']) || $arguments['minimal_price'] == 1) {
            $collection->addMinimalPrice();
        }

        // Add the category by its URL Key
        if(isset($arguments['category_url_key']) && !isset($arguments['category_id'])) {
            $categories = Mage::getModel('catalog/category')->getCollection()->addAttributeToFilter('url_key', $arguments['category_url_key']);
            if(!empty($categories)) {
                foreach($categories as $category) {
                    $arguments['category_id'] = $category->entity_id;
                    break;
                }
            }
        }

        // Add the category by its ID
        if(isset($arguments['category_id']) && $arguments['category_id'] > 0) {
            $collection->addCategoryFilter(Mage::getModel('catalog/category')->load($arguments['category_id']));
        }

        // Add a filter
        // Example 1: array('title' => array('nlike' => array('%a', '%b')))
        // Example 2: array( array('attribute'=>'name', 'like'=>'P%')))
        // Example 3: array(
        //      array('attribute'=>'price','lt'=>'20'),
        //      array('attribute'=>'name','like'=>'Product C')
        //      ));
        if (isset($arguments['filters']) && is_array($arguments['filters'])) {
            $filters = $arguments['filters'];
            try {
                foreach ($filters as $field => $value) {
                    $collection->addFieldToFilter($field, $value);
                }
            } catch (Mage_Core_Exception $e) {
                Mage::getSingleton('magebridge/debug')->error('Invalid search filter', $e->getMessage());
            }
        }

        // Set ordering
        if(isset($arguments['ordering'])) {
            switch($arguments['ordering']) {
                case 'newest':
                    $collection->setOrder('created_at', 'desc');
                    break;
                case 'oldest':
                    $collection->setOrder('created_at', 'asc');
                    break;
                case 'popular':
                    $collection->setOrder('ordered_qty', 'desc');
                    break;
                case 'random':
                    $collection->getSelect()->order('rand()');
                    break;
            }
        } else {
            $collection->setOrder('created_at', 'desc');
        }

        // Add a list limit
        if(isset($arguments['count'])) {
            $collection->setPageSize($arguments['count']);
        }

        // Add a page number
        if(isset($arguments['page']) && $arguments['page'] > 0) {
            $collection->setCurPage($arguments['page']);
        }

        return $collection;
    }
}
