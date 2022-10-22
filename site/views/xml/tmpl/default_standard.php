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

use Alledia\OSMap\Helper\General;
use Alledia\OSMap\Sitemap\Item;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die();

$showExternalLinks    = (int)$this->osmapParams->get('show_external_links', 0);
$ignoreDuplicatedUIDs = (int)$this->osmapParams->get('ignore_duplicated_uids', 1);
$debug                = $this->params->get('debug', 0) ? "\n" : '';


/**
 * Метод для печати строки URL - Выполняется как Callback
 * @param Item $node
 * @return bool
 * @since 3.9
 */
$printNodeCallback = function (Item $node) use ($showExternalLinks, $ignoreDuplicatedUIDs, $debug) {

    $display = !$node->ignore
        && $node->published
        && (!$node->duplicate || !$ignoreDuplicatedUIDs)
        && $node->visibleForRobots
        && $node->parentIsVisibleForRobots
        && $node->visibleForXML
        && trim($node->fullLink) != '';
    try
    {
        // Code that may throw an Exception or Error.

//         throw new \Exception('Code Exception '.__FILE__.':'.__LINE__) ;
    }
    catch (\Exception $e)
    {
        // Executed only in PHP 5, will not be reached in PHP 7
        echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
        echo'<pre>';print_r( $e );echo'</pre>'.__FILE__.' '.__LINE__;
        die(__FILE__ .' '. __LINE__ );
    }


    if ($display && !$node->isInternal) {
        // Show external links
        $display = $showExternalLinks === 1;
    }


    /**
     * ================================================================================================================
     * TODO : ERROR THIS -> Ошибка в этом месте  - что то связанное с языками
     * При установке или обновлении - нужно проверить что бы не было переопределения в шаблоне сайта
     * /public_html/components/com_osmap/views/xml/tmpl/default_standard.php:63
     */
    if (!$node->hasCompatibleLanguage()) {
        $display = false;
    }
    // TODO : Установлено принудительно из за ошибки
    $display = true;
    /**
     * ================================================================================================================
     */

    /**
     * START - Background Creation For Menu
     * - Только для пунктов меню. Что бы они не падали в map.xml - созданные плагинами
     * TODO : Пункты меню будут записываться в отдельный файл menu-map.xml
     * Проверяем - если включено фоновое создание и
     */
    $backgroundCreation = $this->osmapParams->get('background_creation' , 0 );
    if ( $backgroundCreation )
    {
        $uri = Uri::getInstance();
        $uriLink = $uri::getInstance();
        $queryArr = $uriLink->getQuery(true);



        // Если в URL -> ?component=com_content и это пункт меню  - пропускаем
        if ( array_key_exists(   'component' , $queryArr) && $node->isMenuItem == 1 )
        {
            return false;
        }#END IF

        if ( !array_key_exists(   'component' , $queryArr) && $node->isMenuItem !=  1  )
        {
            return false;
        }#END IF




    }#END IF
    /**
     * END - Background Creation For Menu
     */



    if (!$display) {
        return false;
    }
    
    echo $debug;







    echo '<url>';
    echo '<loc>' . $node->fullLink . '</loc>';

    //    if (!General::isEmptyDate($node->modified)) {
    //        echo '<lastmod>' . $node->modified . '</lastmod>';
    //    }
    //    echo '<changefreq>' . $node->changefreq . '</changefreq>';
    //    echo '<priority>' . $node->priority . '</priority>';
    echo '</url>';

    echo $debug;
    $this->countLines ++ ;
    return true;
};

$app = \Joomla\CMS\Factory::getApplication();
if ($app->input->get('task', false, 'RAW') !== 'background_map'  )  echo $this->addStylesheet(); #END IF


echo $debug . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . $debug;

$this->sitemap->traverse( $printNodeCallback );

echo '</urlset>';


