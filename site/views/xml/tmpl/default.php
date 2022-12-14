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

use Alledia\OSMap\Factory;
use Joomla\CMS\Component\ComponentHelper;

defined('_JEXEC') or die();

$app = \Joomla\CMS\Factory::getApplication();

$this->countLines = 0 ;




ob_start();

echo sprintf('<?xml version="1.0" encoding="%s"?>' . "\n", $this->_charset);

if (empty($this->message)) {
	// $this->type == standard
    echo $this->loadTemplate( $this->type );

} else {
    echo '<message>' . $this->message . '</message>';
}
$mapData = ob_get_contents();
ob_end_clean();

//echo'<pre>';print_r( $this->type );echo'</pre>'.__FILE__.' '.__LINE__;
//echo'<pre>';print_r( $mapData );echo'</pre>'.__FILE__.' '.__LINE__;
//die(__FILE__ .' '. __LINE__ );


/**
 * Если работает фоновое создание карты
 */
if ($app->input->get('task', false, 'RAW') == 'background_map' )
{
    $paramsComponent = ComponentHelper::getComponent('com_osmap', $strict = false);
    $component = $app->input->get('component', 'com-menu', 'RAW') ;

    $mapFileResult = \Alledia\OSMap\Helper\General::createFileMapComponent( $mapData , $component  ) ;

    if ( $paramsComponent->params->get('gzip_on') )
    {

    }#END IF
    $returnData = [
        'countLines' => $this->countLines ,
        'component' => $component ,
        'textNote'  => 'Для компонента '. $component . ' создано ' . $this->countLines .' ссылок' ,
        'mapFileResult' => $mapFileResult ,
    ];

    echo new JResponseJson( $returnData , \Joomla\CMS\Language\Text::_('Завершено') , false );
    die();


}#END IF

echo $mapData ;
