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

use Alledia\OSMap\Helper\General;
use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\Registry\Registry;
use Joomla\Utilities\ArrayHelper;

defined('_JEXEC') or die();

class osmap_com_kunena
{
    /**
     * @var string
     */
    protected static $kunenaVersion = null;

    /**
     * @var object
     */
    protected static $profile = null;

    /**
     * @var object
     */
    protected static $config = null;

    /**
     * @param Item $node
     *
     * @return void
     */
    public static function prepareMenuItem($node)
    {
        $link_query = parse_url($node->link);
        parse_str(html_entity_decode($link_query['query']), $link_vars);

        $catid = intval(ArrayHelper::getValue($link_vars, 'catid', 0));
        $id    = intval(ArrayHelper::getValue($link_vars, 'id', 0));
        $func  = ArrayHelper::getValue($link_vars, 'func', '', '');

        if ($func == 'showcat' && $catid) {
            $node->uid        = 'com_kunenac' . $catid;
            $node->expandible = false;

        } elseif ($func == 'view' && $id) {
            $node->uid        = 'com_kunenaf' . $id;
            $node->expandible = false;
        }
    }

    /**
     * @param Collector $osmap
     * @param Item      $parent
     * @param Registry  $params
     *
     * @return void
     * @throws Exception
     */
    public static function getTree($osmap, $parent, $params)
    {
        // This component does not provide news content. don't waste time/resources
        if ($osmap->isNews) {
            return;
        }

        // Make sure that we can load the kunena api
        if (!static::loadKunenaApi()) {
            return;
        }

        if (self::$profile === null) {
            self::$config = KunenaFactory::getConfig();
            self::$profile = KunenaFactory::getUser();
        }

        $link_query = parse_url($parent->link);
        if (!isset($link_query['query'])) {
            return;
        }

        parse_str(html_entity_decode($link_query['query']), $link_vars);

        // Kubik-Rubik Solution - get the correct view in Kunena >= 2.0.1 - START
        $view       = ArrayHelper::getValue($link_vars, 'view', '');
        $layout     = ArrayHelper::getValue($link_vars, 'layout', '');
        $catid_link = ArrayHelper::getValue($link_vars, 'catid', 0);

        if ($view == 'category' and (!$layout or 'list' == $layout)) {
            if (!empty($catid_link)) {
                $link_query = parse_url($parent->link);

                parse_str(html_entity_decode($link_query['query']), $link_vars);

                $catid = ArrayHelper::getValue($link_vars, 'catid', 0);

            } else {
                $catid = 0;
            }

            // Get ItemID of the main menu entry of the component
            $component = ComponentHelper::getComponent('com_kunena');
            $app       = Factory::getApplication();

            $menus = $app->getMenu('site', []);
            $items = $menus->getItems('component_id', $component->id);

            foreach ($items as $item) {
                if (@$item->query['view'] == 'home') {
                    $parent->id = $item->id;
                    break;
                }
            }

        } else {
            return;
        }

        // Kubik-Rubik Solution - END
        $include_topics           = ArrayHelper::getValue($params, 'include_topics', 1);
        $include_topics           = ($include_topics == 1
            || ($include_topics == 2 && $osmap->view == 'xml')
            || ($include_topics == 3 && $osmap->view == 'html')
            || $osmap->view == 'navigator');
        $params['include_topics'] = $include_topics;

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
        $params['groups']         = General::getAuthorisedViewLevels();

        $priority   = ArrayHelper::getValue($params, 'topic_priority', $parent->priority);
        $changefreq = ArrayHelper::getValue($params, 'topic_changefreq', $parent->changefreq);

        if ($priority == '-1') {
            $priority = $parent->priority;
        }

        if ($changefreq == '-1') {
            $changefreq = $parent->changefreq;
        }

        $params['topic_priority']   = $priority;
        $params['topic_changefreq'] = $changefreq;

        if ($include_topics) {
            $ordering = ArrayHelper::getValue($params, 'topics_order', 'ordering');

            if (!in_array($ordering, ['id', 'ordering', 'time', 'subject', 'hits'])) {
                $ordering = 'ordering';
            }

            $params['topics_order']       = 't.`' . $ordering . '`';
            $params['include_pagination'] = ($osmap->view == 'xml');

            $params['limit'] = '';
            $params['days']  = '';

            // Kubik-Rubik Solution - limit must be only the number + check whether variable is numeric - START
            $limit = ArrayHelper::getValue($params, 'max_topics', '');

            if (is_numeric($limit)) {
                $params['limit'] = $limit;
            }

            $days           = ArrayHelper::getValue($params, 'max_age', '');
            $params['days'] = false;

            if (is_numeric($days)) {
                $params['days'] = (time() - (intval($days) * 86400));
            }
            // Kubik-Rubik Solution - END
        }

        $params['table_prefix'] = static::getTablePrefix();

        static::getCategoryTree($osmap, $parent, $params, $catid);
    }

    /**
     * @param Collector $osmap
     * @param Item      $parent
     * @param Registry  $params
     * @param int       $parentCat
     *
     * @return void
     */
    protected static function getCategoryTree($osmap, $parent, $params, $parentCat)
    {
        $db = Factory::getDbo();

        // Load categories
        if (self::getKunenaMajorVersion() >= '2.0') {
            // Kunena 2.0+
            $catlink = 'index.php?option=com_kunena&amp;view=category&amp;catid=%s&Itemid=' . $parent->id;
            $toplink = 'index.php?option=com_kunena&amp;view=topic&amp;catid=%s&amp;id=%s&Itemid=' . $parent->id;

            $categories = KunenaForumCategoryHelper::getChildren($parentCat);
        } else {
            $catlink = 'index.php?option=com_kunena&amp;func=showcat&amp;catid=%s&Itemid=' . $parent->id;
            $toplink = 'index.php?option=com_kunena&amp;func=view&amp;catid=%s&amp;id=%s&Itemid=' . $parent->id;

            if (self::getKunenaMajorVersion() >= '1.6') {
                // Kunena 1.6+
                kimport('session');
                $session = KunenaFactory::getSession();
                $session->updateAllowedForums();
                $allowed = $session->allowed;
                $query   = $db->getQuery(true)
                    ->select(['id', 'name'])
                    ->from('#__kunena_categories')
                    ->where([
                        'parent = ' . $parentCat,
                        sprintf('id IN (%s)', $allowed)
                    ])
                    ->order('ordering ASC');

            } else {
                // Kunena 1.0+
                $query = $db->getQuery(true)
                    ->select(['id', 'name'])
                    ->from(sprintf('%s_categories', $params['table_prefix']))
                    ->where([
                        'parent = ' . $parentCat,
                        'published=1',
                        'pub_access=0'
                    ])
                    ->order('ordering ASC');
            }

            $categories = $db->setQuery($query)->loadObjectList();
        }

        $osmap->changeLevel(1);
        foreach ($categories as $cat) {
            $node = (object)[
                'id'         => $parent->id,
                'browserNav' => $parent->browserNav,
                'uid'        => 'com_kunenac' . $cat->id,
                'name'       => $cat->name,
                'priority'   => $params['cat_priority'],
                'changefreq' => $params['cat_changefreq'],
                'link'       => sprintf($catlink, $cat->id),
                'expandible' => true,
                'secure'     => $parent->secure
            ];

            if ($osmap->printNode($node) !== false) {
                static::getCategoryTree($osmap, $parent, $params, $cat->id);
            }
        }

        if ($params['include_topics']) {
            if (self::getKunenaMajorVersion() >= '2.0') {
                // Kunena 2.0+
                // @TODO: orderby parameter is missing:
                $topics = KunenaForumTopicHelper::getLatestTopics(
                    $parentCat,
                    0,
                    ($params['limit'] ? (int)$params['limit'] : PHP_INT_MAX),
                    ['starttime', $params['days']]
                );

            } else {
                // Kunena 1.0+
                $access = KunenaFactory::getAccessControl();
                $hold   = $access->getAllowedHold(self::$profile, $parentCat);

                $query = $db->getQuery(true)
                    ->select([
                        't.id',
                        't.catid',
                        't.subject',
                        'MAX(m.time) AS time',
                        'COUNT(m.id) AS msgcount'
                    ])
                    ->from(sprintf('%s_messages AS t', $params['table_prefix']))
                    ->innerJoin(sprintf('%s_messages AS m ON t.id = m.thread', $params['table_prefix']))
                    ->where([
                        't.catid = ' . $parentCat,
                        't.parent = 0',
                        sprintf('t.hold IN (%s)', $hold)
                    ])
                    ->group($db->quoteName('m.thread'))
                    ->order($params['topics_order'] . ' DESC');

                if ($params['days']) {
                    $query = $db->getQuery(true)
                        ->select('*')
                        ->from(sprintf('%s AS topics', $query))
                        ->where('time >= ' . $params['days']);
                }

                $db->setQuery($query, 0, $params['limit']);
                $topics = $db->loadObjectList();
            }

            foreach ($topics[1] as $topic) {
                $node = (object)[
                    'id'         => $parent->id,
                    'browserNav' => $parent->browserNav,
                    'uid'        => 'com_kunenat' . $topic->id,
                    'name'       => $topic->subject,
                    'priority'   => $params['topic_priority'],
                    'changefreq' => $params['topic_changefreq'],
                    'modified'   => intval($topic->last_post_time),
                    'link'       => sprintf($toplink, $topic->category_id, $topic->id),
                    'expandible' => false,
                    'secure'     => $parent->secure
                ];

                if ($osmap->printNode($node) !== false) {
                    // Pagination will not work with K2.0, revisit this when that version is out and stable
                    if ($params['include_pagination'] && isset($topic->msgcount) && $topic->msgcount > self::$config->messages_per_page) {
                        $msgPerPage  = self::$config->messages_per_page;
                        $threadPages = ceil($topic->msgcount / $msgPerPage);

                        for ($i = 2; $i <= $threadPages; $i++) {
                            $subnode = (object)[
                                'id'         => $node->id,
                                'uid'        => $node->uid . 'p' . $i,
                                'name'       => "[{$i}]",
                                'seq'        => $i,
                                'link'       => $node->link
                                    . sprintf('&limit=%s&limitstart=%s', $msgPerPage, (($i - 1) * $msgPerPage)),
                                'browserNav' => $node->browserNav,
                                'priority'   => $node->priority,
                                'changefreq' => $node->changefreq,
                                'modified'   => $node->modified,
                                'secure'     => $node->secure
                            ];
                            $osmap->printNode($subnode);
                        }
                    }
                }
            }
        }

        $osmap->changeLevel(-1);
    }

    /**
     * @return bool
     */
    protected static function loadKunenaApi()
    {
        if (!defined('KUNENA_LOADED')) {
            if (!ComponentHelper::isEnabled('com_kunena')) {
                return false;
            }

            $kunenaAPI = JPATH_ADMINISTRATOR . '/components/com_kunena/api.php';

            if (!is_file($kunenaAPI)) {
                return false;
            }

            // Load Kunena API
            require_once $kunenaAPI;
        }

        return true;
    }

    /**
     * Based on Matias' version (Thanks)
     * See: http://docs.kunena.org/index.php/Developing_Kunena_Router
     *
     * @return string
     */
    protected static function getKunenaMajorVersion()
    {
        if (static::$kunenaVersion === null) {
            if (class_exists('KunenaForum')) {
                static::$kunenaVersion = KunenaForum::versionMajor();

            } elseif (class_exists('Kunena')) {
                static::$kunenaVersion = substr(Kunena::version(), 0, 3);

            } elseif (is_file(JPATH_ROOT . '/components/com_kunena/lib/kunena.defines.php')) {
                static::$kunenaVersion = '1.5';

            } elseif (is_file(JPATH_ROOT . '/components/com_kunena/lib/kunena.version.php')) {
                static::$kunenaVersion = '1.0';

            } else {
                static::$kunenaVersion = '';
            }
        }

        return static::$kunenaVersion;
    }

    /**
     * @return string
     */
    protected static function getTablePrefix()
    {
        $version = self::getKunenaMajorVersion();

        if ($version <= 1.5) {
            return '#__fb';
        }

        return '#__kunena';
    }
}
