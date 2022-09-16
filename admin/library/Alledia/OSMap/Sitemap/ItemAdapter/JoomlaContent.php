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

namespace Alledia\OSMap\Sitemap\ItemAdapter;

use Alledia\OSMap\Factory;
use Joomla\Registry\Registry;

defined('_JEXEC') or die();

abstract class JoomlaContent extends GenericPro
{
    /**
     * @var string
     */
    protected $tableName = '#__table_name';

    /**
     * Returns the generic robots settings
     *
     * @return string
     */
    protected function getRobotsSettings()
    {
        $db = Factory::getDbo();

        // Get the robots params from the row
        $query = $db->getQuery(true)
            ->select('metadata')
            ->from($db->quoteName($this->tableName))
            ->where($db->quoteName('id') . '=' . (int) $this->item->id);
        $row = $db->setQuery($query)->loadObject();

        if (!is_null($row)) {
            $metadata = new Registry($row->metadata);

            $this->robots = $metadata->get('robots');
        }

        // There is no params, so lets check the global settings
        if (is_null($this->robots)) {
            parent::getRobotsSettings();
        }

        return $this->robots;
    }
}
