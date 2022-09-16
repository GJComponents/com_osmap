<?php
/**
 * @package   OSMap-Pro
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2007-2014 XMap - Joomla! Vargas - Guillermo Vargas. All rights reserved.
 * @copyright 2016-2021 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSMap-Pro.
 *
 * OSMap-Pro is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSMap-Pro is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSMap-Pro.  If not, see <https://www.gnu.org/licenses/>.
 */

defined('_JEXEC') or die('Restricted access');

use Alledia\OSMap\Plugin\Base;
use Alledia\OSMap\Plugin\ContentInterface;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

if (!class_exists('VmConfig')) {
    $vmPath = JPATH_ADMINISTRATOR . '/components/com_virtuemart/helpers/config.php';
    if (is_file($vmPath)) {
        require_once $vmPath;
    }
}
if (class_exists('VmConfig')) {
    // Make sure Virtuemart is installed and initialized
    VmConfig::loadConfig();
}

class osmap_com_virtuemart extends Base implements ContentInterface
{
    /**
     * @var array
     */
    protected static $categoriesCache = [];

    /**
     * @var static
     */
    protected static $instance = null;

    /**
     * @var VirtueMartModelProduct
     */
    protected static $productModel = null;

    /**
     * @var VirtueMartModelCategory
     */
    protected static $categoryModel = null;

    public static function getInstance()
    {
        if (empty(static::$instance)) {
            $dispatcher = JEventDispatcher::getInstance();
            $instance   = new static($dispatcher);

            static::$instance = $instance;
        }

        return static::$instance;
    }

    /**
     * Возвращает элемент компонента, который поддерживает этот плагин.
     *
     * Returns the element of the component which this plugin supports.
     *
     * @return string
     * @since 3.9
     */
    public function getComponentElement(): string
    {
        return 'com_virtuemart';
    }

    /**
     * This function is called before a menu item is used. We use it to set the
     * proper uniqueid for the item
     *
     * @param Item     $node
     * @param Registry $params
     *
     * @return bool
     * @throws Exception
     */
    public static function prepareMenuItem($node, $params)
    {
        $app = Factory::getApplication();

        $linkQuery = parse_url($node->link);

        parse_str(html_entity_decode($linkQuery['query']), $linkVars);

        $view = ArrayHelper::getValue($linkVars, 'view', '');

        $catId  = ArrayHelper::getValue($linkVars, 'virtuemart_category_id', 0);
        $prodId = ArrayHelper::getValue($linkVars, 'virtuemart_product_id', 0);

        if (!in_array($view, ['categories', 'category'])) {
            if (empty($catId)) {
                $menu       = $app->getMenu();
                $menuParams = $menu->getParams($node->id);
                $catId      = $menuParams->get('virtuemart_category_id', 0);
            }

            if (empty($prodId)) {
                $menu       = $app->getMenu();
                $menuParams = $menu->getParams($node->id);
                $prodId     = $menuParams->get('virtuemart_product_id', 0);
            }

            if ($prodId && $catId) {
                $node->uid        = 'com_virtuemartc' . $catId . 'p' . $prodId;
                $node->expandible = false;

            } elseif ($catId) {
                $node->uid        = 'com_virtuemartc' . $catId;
                $node->expandible = true;
            }

        } else {
            $node->uid        = 'com_virtuemart.' . $view;
            $node->expandible = true;
        }

        return true;
    }

    /**
     * Expand a com_virtuemart menu item
     *
     * @param Collector $collector
     * @param Item      $parent
     * @param Registry  $params
     *
     * @return void
     * @throws Exception
     */
    public static function getTree($collector, $parent, $params)
    {
        if (!class_exists('VmConfig')) {
            return;
        }

        $linkQuery = parse_url($parent->link);

        parse_str(html_entity_decode($linkQuery['query']), $linkVars);

        if ($parent->type == 'alias') {
            $parentId = $parent->params->get('aliasoptions');
        } else {
            $parentId = $parent->id;
        }
        $params['Itemid'] = intval(ArrayHelper::getValue($linkVars, 'Itemid', $parentId));

        $categories = [];
        if (isset($linkVars['virtuemart_category_id'])) {
            $categories[] = intval(ArrayHelper::getValue($linkVars, 'virtuemart_category_id'));
        } else {
            // We don't have a category set for the current menu/view. Let's use the global setting
            $categories = ArrayHelper::getValue($params, 'global_categories', []);
        }

        if (empty($categories)) {
            return;
        }

        $params['include_products']          = (int)ArrayHelper::getValue($params, 'include_products', 1);
        $params['include_product_images']    = ArrayHelper::getValue($params, 'include_product_images', 1);
        $params['product_image_license_url'] = trim(ArrayHelper::getValue($params, 'product_image_license_url', ''));
        $params['product_image_limit']       = (int)ArrayHelper::getValue($params, 'product_image_limit', 1);

        $priority   = ArrayHelper::getValue($params, 'cat_priority', $parent->priority);
        $changefreq = ArrayHelper::getValue($params, 'cat_changefreq', $parent->changefreq);

        if ($priority == '-1') {
            $priority = $parent->priority;
        }

        if ($changefreq == '-1') {
            $changefreq = $parent->changefreq;
        }

        $params['cat_priority']   = $priority;
        $params['cat_changefreq'] = $changefreq;

        $priority   = ArrayHelper::getValue($params, 'prod_priority', $parent->priority);
        $changefreq = ArrayHelper::getValue($params, 'prod_changefreq', $parent->changefreq);

        if ($priority == '-1') {
            $priority = $parent->priority;
        }

        if ($changefreq == '-1') {
            $changefreq = $parent->changefreq;
        }

        $params['prod_priority']   = $priority;
        $params['prod_changefreq'] = $changefreq;

        foreach ($categories as $catId) {
            self::getCategoryTree($collector, $parent, $params, $catId);
        }

        self::$categoriesCache = [];
    }

    /**
     * @param Collector $collector
     * @param Item      $parent
     * @param Registry  $params
     * @param int       $catId
     *
     * @return void
     * @throws Exception
     */
    public static function getCategoryTree($collector, $parent, $params, $catId = null)
    {
        $children = self::getChildCategories($catId);

        if (!empty($children)) {
            $collector->changeLevel(1);

            foreach ($children as $row) {
                $linkQuery = [
                    'option'                 => 'com_virtuemart',
                    'view'                   => 'category',
                    'virtuemart_category_id' => $row->virtuemart_category_id,
                    'Itemid'                 => $params['Itemid']
                ];

                $node = (object)[
                    'id'         => $params['Itemid'],
                    'uid'        => $parent->uid . 'c' . $row->virtuemart_category_id,
                    'browserNav' => $parent->browserNav,
                    'name'       => htmlspecialchars_decode(stripslashes($row->category_name)),
                    'priority'   => $params['cat_priority'],
                    'changefreq' => $params['cat_changefreq'],
                    'expandible' => true,
                    'link'       => 'index.php?' . http_build_query($linkQuery, null, '&amp;')
                ];

                if ($params['include_product_images']) {
                    $node->images = [];
                }

                if ($collector->printNode($node) !== false) {
                    self::getCategoryTree($collector, $parent, $params, $row->virtuemart_category_id);
                }

                $node = null;
            }

            $children = null;

            $collector->changeLevel(-1);
        }

        if ($params['include_products'] > 0 && !is_null($catId)) {
            $collector->changeLevel(1);

            $products = self::getProducts($catId);

            foreach ($products as $row) {
                $linkQuery = [
                    'option'                 => 'com_virtuemart',
                    'view'                   => 'productdetails',
                    'virtuemart_product_id'  => $row->virtuemart_product_id,
                    'virtuemart_category_id' => $row->virtuemart_category_id,
                    'Itemid'                 => $parent->id
                ];

                $node = (object)[
                    'id'             => $parent->id,
                    'uid'            => "{$parent->uid}c{$row->virtuemart_category_id}p{$row->virtuemart_product_id}",
                    'browserNav'     => $parent->browserNav,
                    'priority'       => $params['prod_priority'],
                    'changefreq'     => $params['prod_changefreq'],
                    'name'           => htmlspecialchars_decode($row->product_name),
                    'modified'       => strtotime($row->modified_on),
                    'expandible'     => false,
                    'link'           => 'index.php?' . http_build_query($linkQuery, null, '&amp;'),
                    'visibleForXML'  => in_array($params['include_products'], [1, 2]),
                    'visibleForHTML' => in_array($params['include_products'], [1, 3]),
                    'images'         => []
                ];

                if ($params['include_product_images']) {
                    $images = self::getProductImages($row->virtuemart_product_id, $params['product_image_limit']);

                    foreach ($images as $image) {
                        if (isset($image->file_url)) {
                            $node->images[] = (object)[
                                'src'     => Uri::base() . $image->file_url,
                                'title'   => htmlspecialchars_decode($row->product_name),
                                'license' => $params['product_image_license_url']
                            ];
                        }
                    }
                }

                $collector->printNode($node);
            }

            $collector->changeLevel(-1);
        }
    }

    /**
     * @param int $catId
     *
     * @return object[]
     * @throws Exception
     */
    protected static function getChildCategories($catId)
    {
        if (!isset(self::$categoriesCache[$catId])) {
            static::checkMemory();

            try {
                $categoryModel = static::getCategoryModel();

                static::$categoriesCache[$catId] = $categoryModel->getCategories(true, $catId);

                return static::$categoriesCache[$catId];

            } catch (Exception $e) {
                JFactory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            }
        }

        return [];
    }

    /**
     * @param int $productId
     * @param int $limit
     *
     * @return object[]
     */
    protected static function getProductImages($productId, $limit)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                'm.file_url'
            ])
            ->from('#__virtuemart_product_medias AS pm')
            ->innerJoin('#__virtuemart_medias AS m ON (pm.virtuemart_media_id = m.virtuemart_media_id)')
            ->where([
                'pm.virtuemart_product_id = ' . $db->quote((int)$productId),
            ]);

        $db->setQuery($query, 0, (int)$limit);

        return $db->loadObjectList();
    }

    /**
     * @param int $catId
     *
     * @return object[]
     * @throws Exception
     */
    protected static function getProducts($catId)
    {
        try {
            $productModel = static::getProductModel();
            $ids          = $productModel->sortSearchListQuery(true, $catId);

            return $productModel->getProducts($ids);

        } catch (Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');

        }

        return [];
    }

    /**
     * @return VirtueMartModelProduct
     */
    protected static function getProductModel()
    {
        if (static::$productModel === null) {
            if (static::$productModel = VmModel::getModel('product')) {
                static::$productModel->set('_noLimit', true);
            }
        }

        return static::$productModel;
    }

    /**
     * @return VirtueMartModelCategory
     */
    protected static function getCategoryModel()
    {
        if (static::$categoryModel === null) {
            if (static::$categoryModel = VmModel::getModel('Category')) {
                static::$categoryModel->set('_noLimit');
            }
        }

        return static::$categoryModel;
    }
}
