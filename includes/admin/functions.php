<?php

/**
 * Groupz Admin Functions
 *
 * @package Groupz
 * @subpackage Admin
 */

/*****************************************************************/

/**
 * Returns whether the use chosen setting is set
 *
 * @return boolean
 */
function groupz_use_chosen(){
	return groupz()->admin->use_chosen;
}

/**
 * Return whether the given or current admin page displays
 * groups.
 *
 * Used for enabling chosen.js or custom group list styling.
 * 
 * @param string $hook
 * @return boolean
 */
function groupz_admin_page_with_groups( $hook = '' ){
	return groupz()->admin->admin_page_with_groups( $hook );
}

/**
 * Return whether we are on the groupz admin page
 * 
 * @return boolean
 */
function groupz_is_admin_page(){
	return 'edit-'. groupz_get_group_tax_id() == get_current_screen()->id;
}

/**
 * Return whether we are on the groupz settings page
 * 
 * @return boolean
 */
function groupz_is_settings_page(){
	return 'settings_page_groupz' == get_current_screen()->id;
}

