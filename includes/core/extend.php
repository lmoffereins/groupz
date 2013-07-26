<?php

/**
 * Groupz Extend Functions
 *
 * @package Groupz
 * @subpackage Core
 */

/**
 * Extend Simple History for Groupz
 *
 * Hooks in sh-extender_load_modules action, so this function 
 * will fire only if SHE is active, making a check for plugin 
 * activity not required.
 */
function groupz_extend_she(){

	// Load the SHE component
	require( groupz()->includes_dir . 'extend/simple-history.php' );

	// Instantiate Simple History Extender for Groupz
	groupz()->extend->simple_history = new Groupz_Simple_History();
}
