<?php
/**
 * @package   OSMap
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2007-2014 XMap - Joomla! Vargas - Guillermo Vargas. All rights reserved.
 * @copyright 2016-2021 Joomlashack.com. All rights reserved.
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of OSMap.
 *
 * OSMap is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSMap is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSMap.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace Alledia\OSMap\Plugin;

use Alledia\OSMap\Sitemap\Collector;
use Alledia\OSMap\Sitemap\Item;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();


interface ContentInterface
{
    /**
     * Возвращает уникальный экземпляр плагина
     *
     * Returns the unique instance of the plugin
     *
     * @return object
     * @since 3.9
     */
    public static function getInstance();

    /**
     * Возвращает элемент компонента, который поддерживает этот плагин.
     *
     * Returns the element of the component which this plugin supports.
     *
     * @return string
     * @since 3.9
     */
    public function getComponentElement();

    /**
     * Эта функция вызывается перед использованием пункта меню. Мы используем его для установки
     * правильный уникальный идентификатор для предмета
     *
     * This function is called before a menu item is used. We use it to set the
     * proper uniqueid for the item
     *
     * @param Item     $node   Menu item to be "prepared"
     * @param Registry $params The extension params
     *
     * @return void
     * @since  1.2
     */
    public static function prepareMenuItem($node, $params);

    /**
     * Раскрывает пункт меню com_content
     *
     * Expands a com_content menu item
     *
     * @param Collector $collector
     * @param Item      $parent
     * @param Registry  $params
     *
     * @return void
     * @since  1.0
     */
    public static function getTree($collector, $parent, $params);
}
