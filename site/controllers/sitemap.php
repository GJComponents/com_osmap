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

use Alledia\OSMap;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;

defined('_JEXEC') or die();

class OSMapControllerSitemap extends BaseController
{
    /**
     * Execute a task by triggering a method in the derived class.
     *
     * @param string $task The task to perform. If no matching task is found, the '__default' task is executed, if
     *                     defined.
     *
     * @return void
     *
     * @since   12.2
     * @throws  Exception
     */
    public function execute($task)
    {

        die(__FILE__ .' '. __LINE__ );


        if (strpos($task, '.') !== false) {
            list($type, $task) = explode('.', $task);
        } else {
            $type = '';
        }



        


        // Call plugins to execute extended tasks
        PluginHelper::importPlugin('osmap');

        $eventParams = array($type, $task);
        $results     = OSMap\Factory::getApplication()->triggerEvent('osmapOnBeforeExecuteTask', $eventParams);

        // Check if any of the plugins returned the exit signal
        if (is_array($results) && in_array('exit', $results, true)) {
            OSMap\Factory::getApplication()->enqueueMessage(
                Text::_('COM_OSMAP_MSG_TASK_STOPPED_BY_PLUGIN'),
                'warning'
            );

            return;
        }

        $result = parent::execute($task);

        // Runs the event after the task was executed
        $eventParams[] = &$result;
        OSMap\Factory::getApplication()->triggerEvent('osmapOnAfterExecuteTask', $eventParams);
    }
}
