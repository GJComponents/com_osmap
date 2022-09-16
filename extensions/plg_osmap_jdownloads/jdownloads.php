<?php
/**
 * @package   OSMap-Pro
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2007-2014 XMap - Joomla! Vargas - Guillermo Vargas. All rights reserved.
 * @copyright 2016-2021 Joomlashack.com. All rights reserved
 * @license   http://www.gnu.org/licenses/gpl.html GNU/GPL
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
 * along with OSMap-Pro.  If not, see <http://www.gnu.org/licenses/>.
 */

use Alledia\OSMap\Plugin\Base;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die();

/** Adds support for JDownloads to OSMap */
class PlgOsmapJdownloads extends Base
{
    /*
    * @var array view types to add links too
    */
    protected $views = ['categories', 'category', 'downloads'];

    /**
     * @var bool
     */
    protected static $enabled = null;

    /**
     * @return string
     */
    public function getComponentElement()
    {
        return 'com_jdownloads';
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

        $viewLevels            = Factory::getUser()->getAuthorisedViewLevels();
        $paramsArray['groups'] = implode(',', array_unique($viewLevels));

        $paramsArray['include_downloads'] = ArrayHelper::getValue($paramsArray, 'include_downloads', 1);
        $paramsArray['include_downloads'] = (
            $paramsArray['include_downloads'] == 1
            || ($paramsArray['include_downloads'] == 2 && $collector->view == 'xml')
            || ($paramsArray['include_downloads'] == 3 && $collector->view == 'html')
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

        $paramsArray['download_priority']   = ArrayHelper::getValue(
            $paramsArray,
            'download_priority',
            $parent->priority
        );
        $paramsArray['download_changefreq'] = ArrayHelper::getValue(
            $paramsArray,
            'download_changefreq',
            $parent->changefreq
        );

        if ($paramsArray['download_priority'] == -1) {
            $paramsArray['download_priority'] = $parent->priority;
        }

        if ($paramsArray['download_changefreq'] == -1) {
            $paramsArray['download_changefreq'] = $parent->changefreq;
        }

        switch ($uri->getVar('view')) {
            case 'categories':
                static::getCategoryTree($collector, $parent, $paramsArray, 1);
                break;

            case 'category':
                static::getDownloads($collector, $parent, $paramsArray, $uri->getVar('catid'));
                break;

            case 'downloads':
                static::getDownloads($collector, $parent, $paramsArray);
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
    protected static function getCategoryTree(Collector $collector, Item $parent, array $params, $parent_id)
    {
        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                'c.id',
                'c.title',
                'c.parent_id'
            ])
            ->from('#__jdownloads_categories AS c')
            ->where([
                'c.parent_id = ' . $db->quote($parent_id),
                'c.published = 1'
            ])
            ->order('c.ordering');

        if (!$params['show_unauth']) {
            $query->where(sprintf('c.access IN (%s)', $params['groups']));
        }

        $rows = $db->setQuery($query)->loadObjectList();

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
                'link'       => 'index.php?' . http_build_query([
                        'option' => 'com_jdownloads',
                        'view'   => 'category',
                        'catid'  => $row->id,
                        'Itemid' => $parent->id,
                    ])
            ];

            if ($collector->printNode($node) !== false) {
                static::getDownloads($collector, $parent, $params, $row->id);
            }
        }

        $collector->changeLevel(-1);
    }

    /**
     * @param Collector $collector
     * @param Item      $parent
     * @param array     $params
     * @param ?int      $catid
     *
     * @return void
     * @throws Exception
     */
    protected static function getDownloads(Collector $collector, Item $parent, array $params, $catid = null)
    {
        if (!is_null($catid)) {
            static::getCategoryTree($collector, $parent, $params, $catid);
        }

        if (!$params['include_downloads']) {
            return;
        }

        $db = Factory::getDbo();

        $query = $db->getQuery(true)
            ->select([
                'd.id AS file_id',
                'd.title AS file_title',
                'd.alias AS file_alias',
                'd.catid'
            ])
            ->from('#__jdownloads_files AS d')
            ->where('d.published = 1')
            ->order('d.ordering');

        if (!is_null($catid)) {
            $query->where('d.catid = ' . $db->quote($catid));
        }

        if (!$params['show_unauth']) {
            $query->where(sprintf('d.access IN (%s)', $params['groups']));
        }

        $rows = $db->setQuery($query)->loadObjectList();

        if (empty($rows)) {
            return;
        }

        $collector->changeLevel(1);

        foreach ($rows as $row) {
            $row->slug = !empty($row->file_alias) ? ($row->file_id . ':' . $row->file_alias) : $row->file_id;

            $node = (object)[
                'id'         => $parent->id,
                'name'       => $row->file_title,
                'uid'        => $parent->uid . '_' . $row->file_id,
                'browserNav' => $parent->browserNav,
                'priority'   => $params['download_priority'],
                'changefreq' => $params['download_changefreq'],
                'link'       => 'index.php?' . http_build_query([
                        'option' => 'com_jdownloads',
                        'view'   => 'download',
                        'id'     => $row->slug,
                        'catid'  => $row->catid,
                        'Itemid' => $parent->id,
                    ])
            ];

            $collector->printNode($node);
        }

        $collector->changeLevel(-1);
    }
}
