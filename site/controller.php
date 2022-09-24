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

use Joomla\CMS\MVC\Controller\BaseController;

defined('_JEXEC') or die();

class OSMapController extends BaseController
{
    protected $default_view = 'xml';

    public function display($cachable = true, $urlparams = array())
    {

        // TODO : Отключение Кеша - Временно для разработки
        $cachable = false;

        $urlparams = array_merge(
            $urlparams,
            array(
                'news' => 'INT',
                'images' => 'INT',
                'lang' => 'CMD'
            )
        );
        return parent::display($cachable, $urlparams);
    }
    public function background_map ( ){

        $this->display();

    }
}
