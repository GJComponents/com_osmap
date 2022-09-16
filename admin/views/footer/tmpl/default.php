<?php
/**
 * @package   ShackDefaultFiles
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2015-2021 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of ShackDefaultFiles.
 *
 * ShackDefaultFiles is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ShackDefaultFiles is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ShackDefaultFiles.  If not, see <https://www.gnu.org/licenses/>.
 */

use Joomla\CMS\HTML\HTMLHelper;

defined('_JEXEC') or die();

?>
<div class="row-fluid">
    <div id="footer" class="span12">
        <div>
            <a href="https://www.joomlashack.com">
                <?php
                echo HTMLHelper::_(
                    'image',
                    $this->option . '/joomlashack-logo.png',
                    'Joomlashack',
                    ['width' => '150'],
                    true
                );
                ?>
            </a>
        </div>
        <br/>
        <div>
            Powered by
            <?php echo HTMLHelper::_(
                'link',
                'https://www.joomlashack.com',
                'Joomlashack',
                ['target' => '_blank']
            ); ?>
        </div>
    </div>
</div>
