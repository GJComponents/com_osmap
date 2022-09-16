<?php
/**
 * @package   OSMap-Pro
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2021 Joomlashack.com. All rights reserved
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

use Alledia\OSMap\Factory;
use Alledia\OSMap\Plugin\Base;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();

class PlgOsmapShacklocations extends Base
{
    /**
     * @var object[]
     */
    protected static $menus = null;

    /**
     * @var bool
     */
    protected static $registerModel = true;

    /**
     * @return string
     */
    public function getComponentElement()
    {
        return 'com_focalpoint';
    }

    /**
     * @param Collector $collector
     * @param Item      $parent
     * @param Registry  $params
     *
     * @return void
     */
    public static function getTree(Collector $collector, Item $parent, Registry $params)
    {
        if (static::getViewFromUrl($parent->link) !== 'map') {
            return;
        }

        $mapId     = $parent->params->get('item_id');
        $locations = static::getLocations($parent->id, $mapId);

        if ($locations) {
            $collector->changeLevel(1);

            foreach ($locations as $location) {
                switch ($location->linktype) {
                    case 0:
                    case 1:
                        // URL and 'Own Page'
                        $link = $location->link;
                        break;

                    case 2:
                        // Specific Map
                        if ($location->maplinkid == $mapId) {
                            $link = $location->link;

                        } else {
                            /*
                             * This looks like a bug in Shack Locations.
                             * In any case, we can't handle it.
                             */
                            continue 2;
                        }
                        break;

                    case 3:
                        // No Link
                    case 4:
                        // Menu Item
                    default:
                        continue 2;
                }
                $node = (object)[
                    'id'         => $location->id,
                    'name'       => $location->title,
                    'uid'        => 'shacklocations.location.' . $location->id,
                    'browserNav' => $parent->browserNav,
                    'priority'   => $params['priority'],
                    'changefreq' => $params['changefreq'],
                    'link'       => ltrim($link, '/')
                ];

                $collector->printNode($node);
            }

            $collector->changeLevel(-1);
        }
    }

    /**
     * We have to instantiate the Shack Locations model because of
     * some sticky caching that doesn't permit reuse.
     *
     * @TODO: Fix the Shack Locations Map Model
     *
     * @param int $menuId
     * @param int $mapId
     *
     * @return object[]
     */
    protected static function getLocations($menuId, $mapId)
    {
        if (static::$registerModel) {
            BaseDatabaseModel::addIncludePath(JPATH_SITE . '/components/com_focalpoint/models');
            static::$registerModel = false;
        }
        try {
            /** @var FocalpointModelMap $model */
            $model = BaseDatabaseModel::getInstance('Map', 'FocalpointModel');

            if ($model) {
                $model->getState();
                $model->setState('menu.id', $menuId);
                $model->setState('map.id', $mapId);

                $mapData = $model->getData();
                if (!empty($mapData->markerdata)) {
                    return $mapData->markerdata;
                }
            }

        } catch (Exception $error) {
            // Ignore
        }

        return [];
    }

    /**
     * @param string $view
     * @param int    $id
     *
     * @return bool
     */
    protected static function isMenuitem($view, $id)
    {
        if (static::$menus === null) {
            $db = Factory::getDbo();

            $query = $db->getQuery(true)
                ->select([
                    'menu.id',
                    'menu.link',
                    'menu.params'
                ])
                ->from('#__extensions AS extension')
                ->innerJoin('#__menu AS menu ON menu.component_id = extension.extension_id')
                ->where([
                    'extension.element = ' . $db->quote('com_focalpoint'),
                    'menu.client_id = 0',
                    'published = 1'
                ]);

            $menus = $db->setQuery($query)->loadObjectList();

            static::$menus = [
                'map'      => [],
                'location' => []
            ];

            foreach ($menus as $menu) {
                if ($menuView = static::getViewFromUrl($menu->link)) {
                    $params = new Registry($menu->params);

                    $itemId = (int)$params->get('item_id');

                    static::$menus[$menuView][$itemId] = true;
                }
            }
        }

        if (isset(static::$menus[$view])) {
            return isset(static::$menus[$view][$id]);
        }

        return false;
    }
}
