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

use Alledia\OSMap\Plugin\Base;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die('Restricted access');

JLoader::register(
    'PhocaGalleryRoute',
    JPATH_ADMINISTRATOR . '/components/com_phocagallery/libraries/phocagallery/path/route.php'
);

/** Adds support for Phocagallery to OSMap */
class PlgOsmapPhocagallery extends Base
{
    /*
    * @var array view types to add links too
    */
    protected $views = ['categories', 'category'];

    /**
     * @var bool
     */
    protected static $enabled = null;

    /**
     * @return string
     */
    public function getComponentElement()
    {
        return 'com_phocagallery';
    }

    /**
     * Checks if component is installed and enabled
     *
     * @return bool
     */
    protected function isEnabled()
    {
        if (static::$enabled === null) {
            static::$enabled = ComponentHelper::isEnabled(static::getComponentElement());
        }

        return static::$enabled;
    }

    /**
     * Runs through tree, checks if view is equal with
     * current link then runs method for that view
     *
     * @param Collector $collector
     * @param Item      $parent
     * @param Registry  $params
     *
     * @return void
     * @throws Exception
     */
    public function getTree(Collector $collector, Item $parent, Registry $params)
    {
        $uri = new Uri($parent->link);

        if (!$this->isEnabled() || !in_array($uri->getVar('view'), $this->views)) {
            return;
        }

        $paramsArray = $params->toArray();

        $paramsArray['groups'] = implode(',', Factory::getUser()->getAuthorisedViewLevels());

        $paramsArray['language_filter'] = Factory::getApplication()->getLanguageFilter();

        $paramsArray['enable_imagemap'] = ArrayHelper::getValue($paramsArray, 'enable_imagemap', 0);

        $paramsArray['image_type'] = ArrayHelper::getValue($paramsArray, 'image_type', 'original');

        $paramsArray['include_images'] = ArrayHelper::getValue($paramsArray, 'include_images', 1);
        $paramsArray['include_images'] = (
            $paramsArray['include_images'] == 1
            || ($paramsArray['include_images'] == 2 && $collector->view == 'xml')
            || ($paramsArray['include_images'] == 3 && $collector->view == 'html')
        );

        $paramsArray['show_unauth'] = ArrayHelper::getValue($paramsArray, 'show_unauth', 0);
        $paramsArray['show_unauth'] = (
            $paramsArray['show_unauth'] == 1
            || ($paramsArray['show_unauth'] == 2 && $collector->view == 'xml')
            || ($paramsArray['show_unauth'] == 3 && $collector->view == 'html')
        );

        $paramsArray['category_priority']   = ArrayHelper::getValue(
            $paramsArray,
            'category_priority',
            $parent->priority
        );
        $paramsArray['category_changefreq'] = ArrayHelper::getValue(
            $paramsArray,
            'category_changefreq',
            $parent->changefreq
        );

        if ($paramsArray['category_priority'] == -1) {
            $paramsArray['category_priority'] = $parent->priority;
        }

        if ($paramsArray['category_changefreq'] == -1) {
            $paramsArray['category_changefreq'] = $parent->changefreq;
        }

        $paramsArray['image_priority']   = ArrayHelper::getValue($paramsArray, 'image_priority', $parent->priority);
        $paramsArray['image_changefreq'] = ArrayHelper::getValue(
            $paramsArray,
            'image_changefreq',
            $parent->changefreq
        );

        if ($paramsArray['image_priority'] == -1) {
            $paramsArray['image_priority'] = $parent->priority;
        }

        if ($paramsArray['image_changefreq'] == -1) {
            $paramsArray['image_changefreq'] = $parent->changefreq;
        }

        switch ($uri->getVar('view')) {
            case 'categories':
                static::getCategoryTree($collector, $parent, $paramsArray, 0);
                break;

            case 'category':
                $db = Factory::getDbo();

                $query = $db->getQuery(true)
                    ->select([
                        'c.id',
                        'c.alias',
                        'c.title',
                        'c.parent_id'
                    ])
                    ->from('#__phocagallery_categories AS c')
                    ->where('c.id = ' . $db->quote($uri->getVar('id', 0)))
                    ->where('c.published = 1')
                    ->order('c.ordering');
                $db->setQuery($query);
                $category = $db->loadObject();

                if (empty($category)) {
                    return;
                }

                static::getImages($collector, $parent, $paramsArray, $category->id, $category->alias);
                break;
        }
    }

    /**
     * @param Collector $collector
     * @param Item      $parent
     * @param array     $params
     * @param int       $parent_id
     *
     * @return void
     * @throws Exception
     */
    protected static function getCategoryTree(Collector $collector, Item $parent, array &$params, $parent_id)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                'c.id',
                'c.alias',
                'c.title',
                'c.parent_id'
            ])
            ->from('#__phocagallery_categories AS c')
            ->where('c.parent_id = ' . $db->quote($parent_id))
            ->where('c.published = 1')
            ->order('c.ordering');

        if (!$params['show_unauth']) {
            $query->where('c.access IN(' . $params['groups'] . ')');
        }

        if ($params['language_filter']) {
            $query->where(
                sprintf(
                    'c.language IN (%s)',
                    join(',', [
                        $db->quote(Factory::getLanguage()->getTag()),
                        $db->quote('*')
                    ])
                )
            );
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $collector->changeLevel(1);

        foreach ($rows as $row) {
            $node = (object)[
                'id'         => $parent->id,
                'name'       => $row->title,
                'uid'        => $parent->uid . '_cid_' . $row->id,
                'browserNav' => $parent->browserNav,
                'priority'   => $params['category_priority'],
                'changefreq' => $params['category_changefreq'],
                'pid'        => $row->parent_id,
                'link'       => PhocaGalleryRoute::getCategoryRoute($row->id, $row->alias),
            ];

            if ($collector->printNode($node) !== false) {
                static::getCategoryTree($collector, $parent, $params, $row->id);
                if ($params['include_images']) {
                    static::getImages($collector, $parent, $params, $row->id, $row->alias);
                }
            }
        }

        $collector->changeLevel(-1);
    }

    /**
     * @param Collector $collector
     * @param Item      $parent
     * @param array     $params
     * @param int       $catid
     * @param string    $catAlias
     *
     * @return void
     * @throws Exception
     */
    protected static function getImages(Collector $collector, Item $parent, array $params, $catid, $catAlias)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                'g.id',
                'g.alias',
                'g.title',
                'g.filename'
            ])
            ->from('#__phocagallery AS g')
            ->where('g.catid = ' . $db->quote($catid))
            ->where('g.published = 1')
            ->order('g.ordering');

        if ($params['language_filter']) {
            $query->where('g.language IN(' . $db->quote(Factory::getLanguage()
                    ->getTag()) . ', ' . $db->quote('*') . ')');
        }

        $db->setQuery($query);
        $rows = $db->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $collector->changeLevel(1);

        $root = Uri::root() . 'images/phocagallery/';

        foreach ($rows as $row) {
            $node = (object)[
                'id'         => $parent->id,
                'name'       => $row->title,
                'uid'        => $parent->uid . '_' . $row->id,
                'browserNav' => $parent->browserNav,
                'priority'   => $params['image_priority'],
                'changefreq' => $params['image_changefreq'],
                'link'       => PhocaGalleryRoute::getImageRoute($row->id, $catid, $row->alias, $catAlias),
            ];

            if ($params['enable_imagemap']) {
                $node->isImages = 1;
                // $node->images can be a array with more than one image
                $node->images[0] = (object)[
                    'src'   => $root . static::setImageSrc($row->filename, $params),
                    'title' => $row->title
                ];
            }

            $collector->printNode($node);
        }

        $collector->changeLevel(-1);
    }

    /**
     * @param string $filename
     * @param array  $params
     *
     * @return string
     */
    protected static function setImageSrc($filename, array $params)
    {
        $path = '';
        if (strpos($filename, '/') !== false) {
            $path     = explode('/', $filename);
            $filename = array_pop($path);
            $path     = implode('/', $path) . '/';
        }

        switch ($params['image_type']) {
            case 'thumb_s':
                $path .= 'thumbs/phoca_thumb_s_' . $filename;
                break;

            case 'thumb_m':
                $path .= 'thumbs/phoca_thumb_m_' . $filename;
                break;

            case 'thumb_l':
                $path .= 'thumbs/phoca_thumb_l_' . $filename;
                break;

            default:
                $path .= $filename;
                break;
        }

        return $path;
    }
}
