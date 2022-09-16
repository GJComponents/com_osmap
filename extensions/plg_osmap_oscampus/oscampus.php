<?php
/**
 * @package   OSMap-Pro
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2021 Joomlashack.com. All rights reserved
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
use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die('Restricted access');

class PlgOsmapOscampus extends Base
{
    /*
    * @var array view types to add links too
    */
    protected $views = ['pathways', 'course'];

    /**
     * @var bool
     */
    protected static $enabled = null;

    /**
     * @var array
     */
    public $params = null;

    public function getComponentElement()
    {
        return 'com_oscampus';
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

        if (
            !$this->isEnabled()
            || !in_array($uri->getVar('view'), $this->views)
        ) {
            return;
        }

        $paramsArray = $params->toArray();

        $paramsArray['include_links'] = ArrayHelper::getValue($paramsArray, 'include_links', 1);
        $paramsArray['include_links'] = ($paramsArray['include_links'] == 1
            || ($paramsArray['include_links'] == 2 && $collector->view == 'xml')
            || ($paramsArray['include_links'] == 3 && $collector->view == 'html')
        );

        $paramsArray['cat_priority'] = ArrayHelper::getValue(
            $paramsArray,
            'cat_priority',
            $parent->priority
        );
        if ($paramsArray['cat_priority'] == -1) {
            $paramsArray['cat_priority'] = $parent->priority;
        }

        $paramsArray['cat_changefreq'] = ArrayHelper::getValue(
            $paramsArray,
            'cat_changefreq',
            $parent->changefreq
        );
        if ($paramsArray['cat_changefreq'] == -1) {
            $paramsArray['cat_changefreq'] = $parent->changefreq;
        }

        $paramsArray['link_priority'] = ArrayHelper::getValue($paramsArray, 'link_priority', $parent->priority);
        if ($paramsArray['link_priority'] == -1) {
            $paramsArray['link_priority'] = $parent->priority;
        }

        $paramsArray['link_changefreq'] = ArrayHelper::getValue(
            $paramsArray,
            'link_changefreq',
            $parent->changefreq
        );
        if ($paramsArray['link_changefreq'] == -1) {
            $paramsArray['link_changefreq'] = $parent->changefreq;
        }

        $this->params = $paramsArray;

        switch ($uri->getVar('view')) {
            case 'pathways':
                $this->printPathwayLinks($collector, $parent);
                break;

            case 'course':
                $this->getCourseLinks($collector, $parent);
                break;
        }
    }

    /**
     * Prints Links that are associated with pathways to osmap
     *
     * @param Collector $collector
     * @param Item      $parent (current osmap link)
     *
     * @return void
     * @throws Exception
     */
    protected function printPathwayLinks(Collector $collector, Item $parent)
    {
        if (!$this->params['include_links']) {
            return;
        }

        $db            = Factory::getDbo();
        $pathwaysQuery = $db->getQuery(true)
            ->select([
                'id',
                'title',
                'modified'
            ])
            ->from('#__oscampus_pathways')
            ->where('published = 1');

        $user = Factory::getUser();
        if (!$user->authorise('core.manager')) {
            $viewLevels = Factory::getUser()->getAuthorisedViewLevels();
            $pathwaysQuery->where('access IN (' . join(',', $viewLevels) . ')');
        }

        $pathwayItems = $db->setQuery($pathwaysQuery)->loadObjectList();

        if (empty($pathwayItems)) {
            return;
        }

        foreach ($pathwayItems as $pathwayItem) {
            $collector->changeLevel(1);
            $node = (object)[
                'id'         => $parent->id,
                'name'       => $pathwayItem->title,
                'uid'        => $parent->uid . '_' . $pathwayItem->id,
                'modified'   => $pathwayItem->modified,
                'browserNav' => $parent->browserNav,
                'priority'   => $this->params['cat_priority'],
                'changefreq' => $this->params['cat_changefreq'],
                'link'       => 'index.php?option=com_oscampus&view=pathways&pid=' . $pathwayItem->id
            ];

            $collector->printNode($node);
            $collector->changeLevel(-1);
        }
    }

    /**
     * Gets course links (along with lesson links)
     * that need to be printed to osmap
     *
     * @param Collector $collector
     * @param Item      $parent (current osmap link)
     *
     * @return void
     * @throws Exception
     */
    protected function getCourseLinks($collector, $parent)
    {
        if (!$this->params['include_links']) {
            return;
        }

        $db                = Factory::getDbo();
        $viewLevels        = Factory::getUser()->getAuthorisedViewLevels();
        $courseLessonQuery = $db->getQuery(true)
            ->select([
                'cp.courses_id',
                'course.title AS courseTitle',
                'course.created AS courseCreated',
                'course.modified AS courseModified',
                'lesson.id',
                'lesson.title AS lessonTitle',
                'lesson.created AS lessonCreated',
                'lesson.modified AS lessonModified',
                'lesson.published'
            ])
            ->from('#__oscampus_pathways AS pathway')
            ->innerJoin('#__oscampus_courses_pathways AS cp ON cp.pathways_id = pathway.id')
            ->innerJoin('#__oscampus_courses AS course ON course.id = cp.courses_id')
            ->innerJoin('#__oscampus_modules AS module ON module.courses_id = course.id')
            ->innerJoin('#__oscampus_lessons AS lesson ON lesson.modules_id = module.id')
            ->where([
                'pathway.published = 1',
                'course.published = 1',
                'course.access IN (' . join(',', $viewLevels) . ')',
                'course.released <= NOW()'
            ]);

        $courseItems = $db->setQuery($courseLessonQuery)->loadObjectList();

        if (empty($courseItems)) {
            return;
        }

        $classItems  = [];
        $lessonItems = [];
        foreach ($courseItems as $courseItem) {
            $cid          = $courseItem->courses_id;
            $lessonPub    = $courseItem->published;
            $lid          = $courseItem->id;
            $classItems[] = [
                'option'   => 'com_oscampus',
                'view'     => 'course',
                'cid'      => $cid,
                'cTitle'   => $courseItem->courseTitle,
                'modified' => $courseItem->courseModified ?: $courseItem->courseCreated,
            ];

            if ($lessonPub === '1') {
                $lessonItems[] = [
                    'option'   => 'com_oscampus',
                    'view'     => 'lesson',
                    'cid'      => $cid,
                    'lid'      => $lid,
                    'lTitle'   => $courseItem->lessonTitle,
                    'modified' => $courseItem->lessonModified ?: $courseItem->lessonCreated
                ];
            }
        }

        $this->printCourseLinks($collector, $parent, $classItems, $lessonItems);
    }

    /**
     * Prints links that are classes and lessons to osmap
     * Also orders them in class and then lessons associated with that class
     *
     * @param Collector $collector
     * @param Item      $parent      (current osmap link)
     * @param array     $classItems  (values needed to create link node)
     * @param array     $lessonItems (values needed to create link node)
     *
     * @return void
     * @throws Exception
     */
    protected function printCourseLinks($collector, $parent, $classItems, $lessonItems)
    {
        //making sure there is no duplicate classes and lessons printed
        $classItems  = array_unique($classItems, SORT_REGULAR);
        $lessonItems = array_unique($lessonItems, SORT_REGULAR);

        foreach ($classItems as $classItem) {
            $classQuery = $classItem;
            unset($classQuery['cTitle'], $classQuery['modified']);
            $collector->changeLevel(1);
            $node = (object)[
                'id'         => $parent->id,
                'name'       => $classItem['cTitle'],
                'uid'        => sprintf('%s_%s', $parent->uid, $classItem['cid']),
                'modified'   => $classItem['modified'],
                'browserNav' => $parent->browserNav,
                'priority'   => $this->params['link_priority'],
                'changefreq' => $this->params['link_changefreq'],
                'link'       => 'index.php?' . http_build_query($classQuery)
            ];
            $collector->printNode($node);

            foreach ($lessonItems as $lessonItem) {
                $lessonQuery = $lessonItem;
                unset($lessonQuery['lTitle'], $lessonQuery['modified']);
                $collector->changeLevel(1);
                if ($classItem['cid'] == $lessonItem['cid']) {
                    $node = (object)[
                        'id'         => $parent->id,
                        'name'       => $lessonItem['lTitle'],
                        'uid'        => sprintf('%s_%s_%s', $parent->uid, $lessonItem['cid'], $lessonItem['lid']),
                        'modified'   => $lessonItem['modified'],
                        'browserNav' => $parent->browserNav,
                        'priority'   => $this->params['link_priority'],
                        'changefreq' => $this->params['link_changefreq'],
                        'link'       => 'index.php?' . http_build_query($lessonQuery)
                    ];
                    $collector->printNode($node);
                }
                $collector->changeLevel(-1);
            }
            $collector->changeLevel(-1);
        }
    }

    /**
     * Checks if OSCampus is installed and enabled
     *
     * @return bool
     */
    protected function isEnabled()
    {
        if (static::$enabled === null) {
            static::$enabled = JComponentHelper::getComponent('com_oscampus')->enabled;
        }

        return static::$enabled;
    }
}
