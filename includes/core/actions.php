<?php

/**
 * Groupz Core Actions
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Groupz *******************************************************/

add_action( 'plugins_loaded',      'groupz_loaded'          );
add_action( 'init',                'groupz_init',       0   );
add_action( 'admin_init',          'groupz_admin_init', 10  );

/** Main *********************************************************/

add_action( 'groupz_init',         'groupz_core'            );
add_action( 'groupz_init',         'groupz_ready',      999 );
add_action( 'admin_bar_menu',      'groupz_admin_bar',  90  );

/** Plugin *******************************************************/

add_action( 'groupz_activation',   'groupz_add_caps'        );
add_action( 'groupz_deactivation', 'groupz_remove_caps'     );
add_action( 'groupz_uninstall',    'groupz_remove_settings' );
add_action( 'groupz_uninstall',    'groupz_remove_all_meta' );

/** Admin ********************************************************/

if ( is_admin() )
	add_action( 'groupz_init',     'groupz_admin'           );

/** Extend *******************************************************/

add_action( 'she_load_modules',    'groupz_extend_simple_history' ); 

/*****************************************************************/

/**
 * Act on plugin activation
 *
 * @since 0.1
 *
 * @uses do_action() Calls 'groupz_activation'
 */
function groupz_activation() {
	do_action( 'groupz_activation' );
}

/**
 * Act on plugin deactivation
 * 
 * @since 0.1
 * 
 * @uses do_action() Calls 'groupz_deactivation'
 */
function groupz_deactivation() {
	do_action( 'groupz_deactivation' );
}

/**
 * Act on plugin uninstallation
 * 
 * @since 0.1
 * 
 * @uses do_action() Calls 'groupz_uninstall'
 */
function groupz_uninstall() {
	do_action( 'groupz_uninstall' );
}

/**
 * Act on plugins loaded
 *
 * @since 0.x
 *
 * @uses do_action() Calls 'groupz_loaded'
 */
function groupz_loaded() {
	do_action( 'groupz_loaded' );
}

/**
 * Act on plugin initiation
 *
 * @since 0.x
 *
 * @uses do_action() Calls 'groupz_init'
 */
function groupz_init() {
	do_action( 'groupz_init' );
}

/**
 * Act on plugin admin initiation
 *
 * @since 0.x
 *
 * @uses do_action() Calls 'groupz_admin_init'
 */
function groupz_admin_init() {
	do_action( 'groupz_admin_init' );
}

/**
 * Act on plugin readiness
 *
 * @since 0.x
 *
 * @uses do_action() Calls 'groupz_ready'
 */
function groupz_ready() {
	do_action( 'groupz_ready' );
}

