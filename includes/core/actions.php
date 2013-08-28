<?php

/**
 * Groupz Core Actions
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Core *********************************************************/

add_action( 'init',                'groupz_core'          );
add_action( 'admin_bar_menu',      'groupz_admin_bar', 90 );

/** Capabilities *************************************************/

add_action( 'groupz_activation',   'groupz_add_caps'      );
add_action( 'groupz_deactivation', 'groupz_remove_caps'   );

/** Admin ********************************************************/

if ( is_admin() ) {
	add_action( 'plugins_loaded',  'groupz_admin'         );
}

/** Extend *******************************************************/

add_action( 'she_load_modules',    'groupz_extend_simple_history' ); 
