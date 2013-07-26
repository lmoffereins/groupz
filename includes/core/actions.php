<?php

/**
 * Groupz Actions
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/** Core *********************************************************/

add_action( 'plugins_loaded', 'groupz_core'          );
add_action( 'admin_bar_menu', 'groupz_admin_bar', 90 );

/** Admin ********************************************************/

if ( is_admin() ){
	add_action( 'plugins_loaded', 'groupz_admin' );
}

/** Capabilities *************************************************/

add_action( 'groupz_activation',   'groupz_add_caps'    );
add_action( 'groupz_deactivation', 'groupz_remove_caps' );

/** Access *******************************************************/

add_action( 'plugins_loaded', 'groupz_access' );

/** Extend *******************************************************/

add_action( 'she_load_modules', 'groupz_extend_she' ); // Only loads if hook exists
