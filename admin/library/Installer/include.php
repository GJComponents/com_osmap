<?php
/**
 * @package   ShackInstaller
 * @contact   www.joomlashack.com, help@joomlashack.com
 * @copyright 2016-2021 Joomlashack.com. All rights reserved
 * @license   https://www.gnu.org/licenses/gpl.html GNU/GPL
 *
 * This file is part of ShackInstaller.
 *
 * ShackInstaller is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * ShackInstaller is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with ShackInstaller.  If not, see <https://www.gnu.org/licenses/>.
 */

use Alledia\Installer\AutoLoader;

defined('_JEXEC') or die();

if (!defined('SHACK_INSTALLER_BASE')) {
    define('SHACK_INSTALLER_BASE', __DIR__);
}

if (class_exists('\\Alledia\\Installer\\AutoLoader') === false) {
    require_once SHACK_INSTALLER_BASE . '/AutoLoader.php';
}
AutoLoader::register('Alledia\\Installer', SHACK_INSTALLER_BASE, true);

if (!defined('SHACK_INSTALLER_VERSION')) {
    define('SHACK_INSTALLER_VERSION', '2.1.4');
    define('SHACK_INSTALLER_COMPATIBLE', '2.1.0');
}
