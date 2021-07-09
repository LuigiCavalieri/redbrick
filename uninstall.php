<?php
namespace RedBrick;

/**
 * @package RedBrick
 * @copyright Copyright 2021 Luigi Cavalieri.
 * @license https://opensource.org/licenses/GPL-3.0 GPL v3.0
 * ************************************************************ */


if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    include( 'library/base-plugin.class.php' );
    include( 'includes/core.class.php' );

    global $wpdb;

    Core::launch( __DIR__ );

    $db = Core::invoke()->db();

    delete_option( $db->optionsID() );
}
?>