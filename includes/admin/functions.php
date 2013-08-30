<?php

/**
 * Groupz Admin Functions
 *
 * @package Groupz
 * @subpackage Administration
 */

/*****************************************************************/

/**
 * Returns whether the use chosen setting is set
 *
 * @since 0.1
 *
 * @return boolean
 */
function groupz_use_chosen() {
	return groupz()->admin->use_chosen;
}

/**
 * Return whether the given or current admin page displays
 * groups.
 *
 * Used for enabling chosen.js or custom group list styling.
 *
 * @since 0.1
 * 
 * @param string $hook
 * @return boolean
 */
function groupz_admin_page_with_groups( $hook = '' ) {
	return groupz()->admin->admin_page_with_groups( $hook );
}

/**
 * Return whether we are on the groupz admin page
 *
 * @since 0.1
 * 
 * @return boolean
 */
function groupz_is_admin_page() {
	return is_admin() && 'edit-'. groupz_get_group_tax_id() == get_current_screen()->id;
}

/**
 * Return whether we are on the groupz settings page
 *
 * @since 0.1
 * 
 * @return boolean
 */
function groupz_is_settings_page() {
	return is_admin() && 'settings_page_groupz' == get_current_screen()->id;
}

/** Tooltip ******************************************************/

/**
 * Return users tooltip content string
 *
 * @since 0.x
 * 
 * @param array $users User IDs
 * @return string Tooltip
 */
function groupz_users_tooltip( $users ) {
	$many = 0 < ( count( $users ) - 20 ); // More than 20 users?

	$tooltip = implode( '<br>', array_map( 'display_name', $many ? array_slice( $users, 0, 20 ) : $users ) );
	if ( $many )
		$tooltip .= '<br>' . sprintf( __('and another %d', 'groupz'), count( $users ) - 20 );

	return apply_filters( 'groupz_users_tooltip', $tooltip, $users );
}

/**
 * Return user display name from given user ID
 *
 * @since 0.x
 * 
 * @param int $user_id User IDs
 * @return string User display name
 */
function display_name( $user_id ) {
	$user = get_userdata( (int) $user_id );

	if ( ! $user )
		return false;

	return apply_filters( 'groupz_display_name', $user->display_name, (int) $user_id );
}

