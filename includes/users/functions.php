<?php

/**
 * Groupz User Functions
 *
 * @package Groupz
 * @subpackage Users
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/*****************************************************************/

/**
 * Helper function hooked to 'profile_update' action to save or
 * update user groups.
 *
 * @since 0.1
 *
 * @param int $user_id
 * @uses bbp_reset_user_caps() to reset caps
 * @uses bbp_save_user_caps() to save caps
 */
function groupz_user_groups_update( $user_id = 0 ) {

	// Bail if no user ID was passed
	if ( empty( $user_id ) )
		return;

	// Bail if no nonce
	if ( ! isset( $_POST['groupz_user_groups_nonce'] ) )
		return;

	// User groups we want the user to have
	$new_groups = array_map( 'intval', $_POST['groupz-user-groups'] );
	$old_groups = get_user_groups( $user_id );

	// Set the new user groups or delete if all empty
	if ( $new_groups != $old_groups ) {
		groupz()->core->update_user_groups( $user_id, $new_groups );
	}
}
add_action( 'profile_update', 'groupz_user_groups_update' );

/**
 * Return whether given or current user has any group
 *
 * @since 0.1
 *
 * @uses get_user_groups()
 * 
 * @param int $user_id User ID. Defaults to current user.
 * @return boolean User has group
 */
function groupz_user_has_group( $user_id = 0 ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$user_id     = (int) $user_id;
	$user_groups = get_user_groups( $user_id );
	$has         = ! empty( $user_groups );

	return apply_filters( 'groupz_user_has_group', $has, $user_id );
}

/**
 * Return whether given user is in given group
 *
 * Checks if user is member of given group
 *
 * @since 0.1
 *
 * @uses get_user_groups()
 * @uses get_ancestors() To get the group ancestors
 * 
 * @param int $group_id Group ID
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $hier Whether to evaluate including group
 *                       hierarchy. Defaults to true.
 * @return boolean User is in group
 */
function groupz_user_in_group( $group_id, $user_id = 0, $hier = true ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$group_id     = (int) $group_id;
	$user_id      = (int) $user_id;
	$_user_groups = get_user_groups( $user_id );
	$user_groups  = array();

	// Check for group ancestry
	if ( $hier ) {
		// Add found group ancestors to user groups
		foreach ( $_user_groups as $group ) {
			$user_groups = array_unique( array_merge( $user_groups, get_ancestors( $group, groupz_get_group_tax_id() ) ) );
		}

		// Merge user ancestor groups with user groups
		$user_groups = array_unique( array_merge( $user_groups, $_user_groups ) );
	} else {
		$user_groups = $_user_groups;
	}

	$found = in_array( $group_id, $user_groups );
	return apply_filters( 'groupz_user_in_group', $found, $group_id, $user_id, $hier );
}

/**
 * Add links to the admin bar
 *
 * @since 0.1
 *
 * @uses WP_Admin_Bar::add_node()
 * @param object $wp_admin_bar
 */
function groupz_admin_bar( $wp_admin_bar ) {
	if ( ! current_user_can( 'manage_groups' ) )
		return;

	// New Group
	$wp_admin_bar->add_node( array(
		'parent' => 'new-content', // Add to new-content menu node
		'id'     => 'new-group',
		'title'  => __('Group', 'groupz'),
		'href'   => groupz_get_admin_page_url()
	) );
}

