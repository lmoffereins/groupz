<?php

/**
 * Groupz Extend Functions
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Extend Simple History for Groupz
 *
 * Hooks in sh-extender_load_modules action, so this function 
 * will fire only if Simple History is active, making a check 
 * with is_plugin_active() not required.
 *
 * @since 0.1
 */
function groupz_extend_simple_history(){

	// Load the Simple History component
	require( groupz()->includes_dir . 'extend/simple-history.php' );

	// Instantiate Simple History Extender for Groupz
	groupz()->extend->simple_history = new Groupz_Simple_History();
}
