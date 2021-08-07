<?php
/**
 * Plugin Name: RedBrick
 * Plugin URI: https://wordpress.org/plugins/redbrick/
 * Description: Simple anti-spam plugin for WordPress blogs.
 * Version: 1.0.4
 * Requires: 5.5
 * Author: Luigi Cavalieri
 * Author URI: https://luigicavalieri.com
 * License: GPLv3
 * License URI: https://opensource.org/licenses/GPL-3.0
 *
 *
 * @package RedBrick
 * @version 1.0.4
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 * 
 * 
 * Copyright 2021 Luigi Cavalieri.
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * ************************************************************************* */


include( 'library/base-plugin.class.php' );
include( 'includes/core.class.php' );

\RedBrick\Core::launch( __DIR__ );
?>