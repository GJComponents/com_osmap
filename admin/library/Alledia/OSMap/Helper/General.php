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

namespace Alledia\OSMap\Helper;

use Alledia\OSMap\Factory;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\File as JFile;
use Joomla\CMS\HTML\Helpers\Sidebar;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Version;
use Joomla\Registry\Registry;
use osmap_com_virtuemart;
use PlgOSMapJoomla;


defined('_JEXEC') or die();

abstract class General
{
    protected static $plugins = [];

    /**
     * Build the submenu in admin if needed. Triggers the
     * onAdminSubmenu event for component addons to attach
     * their own admin screens.
     *
     * The expected response must be an array
     * [
     *    "text" => Static language string,
     *    "link" => Link to the screen
     *    "view" => unique view name
     * ]
     *
     * @param string $viewName
     *
     * @return void
     * @throws \Exception
     */
    public static function addSubmenu(string $viewName)
    {
        if (Version::MAJOR_VERSION > 3) {
            // Not needed for Joomla 4+
            return;
        }

        $submenus = [
            [
                'text' => 'COM_OSMAP_SUBMENU_SITEMAPS',
                'link' => 'index.php?option=com_osmap&view=sitemaps',
                'view' => 'sitemaps'
            ],
            [
                'text' => 'COM_OSMAP_SUBMENU_EXTENSIONS',
                'link' => 'index.php?option=com_plugins&view=plugins&filter_folder=osmap',
                'view' => 'extensions'
            ]
        ];

        Factory::getApplication()->triggerEvent('onOSMapAddAdminSubmenu', [&$submenus]);

        if (!empty($submenus)) {
            foreach ($submenus as $submenu) {
                if (is_array($submenu)) {
                    Sidebar::addEntry(
                        Text::_($submenu['text']),
                        $submenu['link'],
                        $viewName == $submenu['view']
                    );
                }
            }
        }
    }

    /**
     * Returns the sitemap type checking the input.
     * The expected types:
     *   - standard
     *   - images
     *   - news
     *
     * @return string
     * @throws \Exception
     */
    public static function getSitemapTypeFromInput(): string
    {
        $input = Factory::getPimpleContainer()->input;

        if ($input->getBool('images', false)) {
            return 'images';

        } elseif ($input->getBool('news', false)) {
            return 'news';

        } else {
            return 'standard';
        }
    }

    /**
     * Возвращает список плагинов из базы данных. Устаревшие плагины от XMap
     * Сначала будут возвращены *, затем плагины OSMap. Всегда соблюдая порядок.
     *
     * Returns a list of plugins from the database. Legacy plugins from XMap
     * will be returned first, then OSMap plugins. Always respecting the
     * ordering.
     *
     * @return array Массив обектов плагинов из таблицы #__extensions
     * @since 3.9
     * @throws \Exception
     * TODO - Выбираем плагины из ДБ
     */
    public static function getPluginsFromDatabase(): array
    {
        $app = \Joomla\CMS\Factory::getApplication();
        $db = Factory::getPimpleContainer()->db;
        $params   = ComponentHelper::getParams('com_osmap');
        $backgroundCreation = $params->get('background_creation' , 0 );
        
        
        // Получить все подключаемые модули OSMap и XMap. Сначала получите плагины XMap затем OSMap. Всегда соблюдая порядок.
        // Get all the OSMap and XMap plugins. Get XMap plugins first then OSMap. Always respecting the ordering.
        $query = $db->getQuery(true)
            ->select([
                'folder',
                'params',
                'element'
            ])
            ->from('#__extensions')
            ->where('type = ' . $db->quote('plugin'))
            ->where(
                sprintf(
                    'folder IN (%s)',
                    join(',', $db->quote(['osmap', 'xmap']))
                )
            )
            ->where('enabled = 1')
            ->order('folder DESC, ordering');



        // Если включено фоновое создание
        if ( $backgroundCreation && $app->input->get('task' , false ) == 'background_map'  )
        {
            $componentName = $app->input->get('component' , false , 'RAW' ) ;
            if ( !$componentName )
            {
                return [];
            }#END IF
            if ($componentName == 'com_content' )
            {
                $componentName = 'joomla' ;
            }#END IF


            $query->where('element = ' . $db->quote( $componentName ) );
        }#END IF
//	    echo $query->dump();
//
//		die(__FILE__ .' '. __LINE__ );


        return $db->setQuery($query)->loadObjectList();
    }

    /**
     * Возвращает true, если плагин совместим с данной опцией.
     * 
     * Returns true if the plugin is compatible with the given option
     *
     * @param object  $plugin   - Объект плагна из таблицы #__extensions
     * @param ?string $option   - Параметр option - для команента etc/ com_content
     * @param object  $params   - Параметры компонента
     * @param array   $queryArr - URI Query - Параметры запроса - etc/ ?component=com_content
     *
     * @return bool
     * @since 3.9
     */
    protected static function checkPluginCompatibilityWithOption(object $plugin, ?string $option  , $params  , $queryArr =  [] ): bool
    {
        /**
         * START - Проверяем - нужно ли обрабатывать этот плагин если включено фоновое создание карты
         *
         */
        $backgroundCreation = $params->get('background_creation' , 0 );
        if ( $backgroundCreation && array_key_exists(   'component' , $queryArr ) && $queryArr['component'] != $option )
        {
            return false;
        }#END IF

        if (empty($option)) {
            return false;
        }

        $path       = JPATH_PLUGINS . '/' . $plugin->folder . '/' . $plugin->element . '/' . $plugin->element . '.php';
        $compatible = false;

        if (File::exists($path)) {
            /*
             * У старых плагинов есть опция element ==.
             * Но может по-прежнему быть несовместимым с
             * текущий контент/опция
             *
             * Legacy plugins have element == option.
             * But may still not be compatible with
             * the current content/option
             */
            $isLegacy = $plugin->element === $option;

            $className = $isLegacy
                ? ($plugin->folder . '_' . $option)
                : ('Plg' . ucfirst($plugin->folder) .  ucfirst($plugin->element));



            if (!class_exists($className)) {
                require_once $path;
            }

            // Создайте экземпляр плагина, если класс существует
            // Instantiate the plugin if the class exists
            if (class_exists($className)) {
                $dispatcher = Factory::getDispatcher();
                /**
                 * @var PlgOSMapJoomla|osmap_com_virtuemart $instance
                 */
                $instance   = method_exists($className, 'getInstance') ?
                    $className::getInstance() : new $className($dispatcher);

                // Если это наследие, мы знаем, что оно совместимо, поскольку элемент и параметр уже были проверены.
                // If is legacy, we know it is compatible since the element and option were already validated
                $compatible = $isLegacy
                    || (
                        method_exists($instance, 'getComponentElement')
                        && $instance->getComponentElement() === $option
                    );

                if ($compatible) {
                    $plugin->instance  = $instance;
                    $plugin->className = $className;
                    $plugin->isLegacy  = $isLegacy;
                    $plugin->params    = new Registry($plugin->params);
                }
            }
        }


        return $compatible;
    }

    /**
     * Получает плагины в соответствии с заданной  etc/ ?option=com_content
     *
     * Gets the plugins according to the given option/component
     *
     * @param ?string $option Название компонента - etc/  com_content, com_virtuemart
     *
     * @return object[]
     * @since 3.9
     */
    public static function getPluginsForComponent(?string $option): array
    {
        // Проверьте, есть ли кешированный список плагинов для этой опции.
        // Check if there is a cached list of plugins for this option
        if ($option && empty(static::$plugins[$option])) {

            $queryArr = [] ;
            $params   = ComponentHelper::getParams('com_osmap');
            $backgroundCreation = $params->get('background_creation' , 0 );

            if ($backgroundCreation)
            {
                $uri = Uri::getInstance();
                $uriLink = $uri::getInstance();
                $queryArr = $uriLink->getQuery(true);
            }#END IF


            $compatiblePlugins = [];

            $plugins = static::getPluginsFromDatabase();

            if ($plugins) {
                foreach ($plugins as $plugin) {
                    if (static::checkPluginCompatibilityWithOption($plugin, $option  , $params , $queryArr  ) ) {
                        $compatiblePlugins[] = $plugin;
                    }
                }
            }

            static::$plugins[$option] = $compatiblePlugins;
        }




        return static::$plugins[$option] ?? [];
    }

    /**
     * Извлекает разрывы страниц из заданного текста. Возвращает список подузлов
     * относится к каждому разрыву страницы.
     *
     * Extracts pagebreaks from the given text. Returns a list of subnodes
     * related to each pagebreak.
     *
     * @param string  $text
     * @param string  $baseLink
     * @param ?string $uid
     *
     * @return object[]
     * @since 3.9
     */
    public static function getPagebreaks(string $text, string $baseLink, ?string $uid = ''): array
    {
        $matches = $subNodes = [];

        if (preg_match_all(
            '/<hr\s*[^>]*?(?:(?:\s*alt="(?P<alt>[^"]+)")|(?:\s*title="(?P<title>[^"]+)"))+[^>]*>/i',
            $text,
            $matches,
            PREG_SET_ORDER
        )) {
            $i = 2;
            foreach ($matches as $match) {
                if (strpos($match[0], 'class="system-pagebreak"') !== false) {
                    $link = $baseLink . '&limitstart=' . ($i - 1);

                    $subNode = (object)[
                        'name'       => $match['alt'] ?? $match['title'] ?? Text::sprintf('Page #', $i),
                        'uid'        => $uid . '.' . ($i - 1),
                        'expandible' => false,
                        'link'       => $link,
                    ];

                    $subNodes[] = $subNode;

                    $i++;
                }
            }

        }

        return $subNodes;
    }

    /**
     * Returns true if the given date is empty, considering not only as string,
     * but integer, boolean or date.
     *
     * @param mixed $date
     *
     * @return bool
     */
    public static function isEmptyDate($date): bool
    {
        $db = Factory::getPimpleContainer()->db;

        $invalidDates = [
            '',
            null,
            false,
            0,
            '0',
            -1,
            '-1',
            $db->getNullDate(),
            '0000-00-00'
        ];

        return in_array($date, $invalidDates);
    }

    /**
     * Returns an array or string with the authorised view levels for public or
     * guest users. If the param $asString is true, it returns a string as CSV.
     * If false, an array. If the current view was called by the admin to edit
     * the sitemap links, we return all access levels to give access for everything.
     *
     * @param bool $asString
     *
     * @return string|string[]
     */
    public static function getAuthorisedViewLevels(bool $asString = true)
    {
        $container = Factory::getPimpleContainer();
        $levels    = [];

        // Check if we need to return all levels, if it was called from the admin to edit the link list
        if ($container->input->get('view') === 'adminsitemapitems') {
            $db = $container->db;

            // Get all access levels
            $query = $db->getQuery(true)
                ->select('id')
                ->from($db->quoteName('#__viewlevels'));
            $rows = $db->setQuery($query)->loadRowList();

            foreach ($rows as $row) {
                $levels[] = $row[0];
            }

        } else {
            // Only shows returns the level for the current user
            $levels = Factory::getUser()->getAuthorisedViewLevels();
        }

        if ($asString) {
            $levels = implode(',', $levels);
        }

        return $levels;
    }

    /**
     * Убедитесь, что загружены соответствующие языковые файлы компонентов.
     * Make sure the appropriate component language files are loaded
     *
     * @param string $option
     * @param string $adminPath
     * @param string $sitePath
     *
     * @return void
     * @throws \Exception
     * @since 3.9
     */
    public static function loadOptionLanguage(
        string $option = 'com_osmap',
        string $adminPath = OSMAP_ADMIN_PATH,
        string $sitePath = OSMAP_SITE_PATH
    ) {
        $app = Factory::getApplication();

        switch ($app->getName()) {
            case 'administrator':
                Factory::getLanguage()->load($option, $adminPath);
                break;

            case 'site':
                Factory::getLanguage()->load($option, $sitePath);
                break;
        }
    }

    /**
     * Проверьте, является ли нужный метод статическим или нет, и вызовите его в правильном
     * способ, позволяющий избежать строгих предупреждений в сторонних плагинах. Он возвращает
     * результат вызванного метода.
     *
     * Check if the needed method is static or not and call it in the proper
     * way, avoiding Strict warnings in 3rd party plugins. It returns the
     * result of the called method.
     *
     * @param string $class
     * @param object $instance
     * @param string $method
     * @param array  $params
     *
     * @return mixed
     * @since 3.9
     */
    public static function callUserFunc(string $class, object $instance, string $method, array $params = [])
    {
        try {
            $reflection = new \ReflectionMethod($class, $method);

            return $reflection->isStatic()
                ? call_user_func_array([$class, $method], $params)
                : call_user_func_array([$instance, $method], $params);

        } catch (\Exception $error) {
            // Just ignore this?
        }

        return null;
    }

    /**
     * Создать файл map.xml для выбранного компонента
     * @param string $mapData
     * @param string $component - название компонента com_menu | com_content
     * @return string[]|void
     * @since 3.9
     */
    public static function createFileMapComponent( string $mapData , string $component = 'com_menu' ){
        $paramsComponent = ComponentHelper::getComponent('com_osmap', $strict = false);

		$app = \Joomla\CMS\Factory::getApplication();
		$lang = $app->input->get('lang' , false  , 'STRING');
	    if ( $lang )
	    {
		    $component .= '-'.$lang ;
	    }#END IF



//		echo'<pre>';print_r( $component );echo'</pre>'.__FILE__.' '.__LINE__;
//		die(__FILE__ .' '. __LINE__ );



        $fileName = 'sitemap-'.$component.'-1.xml';
        $pathFile = JPATH_SITE .'/'. $fileName ;

        /*echo'<pre>';print_r( $paramsComponent );echo'</pre>'.__FILE__.' '.__LINE__;
        echo'<pre>';print_r( $mapData );echo'</pre>'.__FILE__.' '.__LINE__;
        echo'<pre>';print_r( $pathFile );echo'</pre>'.__FILE__.' '.__LINE__;

        die(__FILE__ .' '. __LINE__ );*/


        try
        {
            // Code that may throw an Exception or Error.
            JFile::write( $pathFile , $mapData);
            // throw new \Exception('Code Exception '.__FILE__.':'.__LINE__) ;
        }
        catch (\Exception $e)
        {
            // Executed only in PHP 5, will not be reached in PHP 7
            echo 'Выброшено исключение: ',  $e->getMessage(), "\n";
            echo'<pre>';print_r( $e );echo'</pre>'.__FILE__.' '.__LINE__;
            die(__FILE__ .' '. __LINE__ );
        }

        $returnData = [
            'fileName' => $fileName,
            'pathFile' => $pathFile,
        ];
        return $returnData;

    }
}
