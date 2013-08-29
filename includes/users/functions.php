<?php

/**
 * Groupz User Functions
 *
 * @package Groupz
 * @subpackage Users
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** User Groups **************************************************/

/**
 * Return the groups of the given user
 *
 * @since 0.1
 * 
 * @param int $user_id User ID. Defaults to current user
 * @param array $ids Optional query arguments
 * @param boolean $include_ancestors Whether to insert groups from the users group ancestor tree
 * @return array $groups
 */
function groupz_get_user_groups( $user_id, $args = array(), $include_ancestors = false ) {
	$defaults = array( 'user_id' => (int) $user_id );

	// Get the groups
	$user_groups = get_groups( wp_parse_args( $args, $defaults ) );

	// Setup return var
	$groups = array();

	// When ancestors are requested
	if ( $include_ancestors ) {
		foreach ( $user_groups as $group_id ) {
			$ancestors = get_ancestors( is_object( $group_id ) ? $group_id->term_id : $group_id, groupz_get_group_tax_id() );

			// Return terms if requested
			if ( is_object( $group_id ) && !empty( $ancestors ) ) {
				foreach ( $ancestors as $k => $anc_id ) {
					$ancestors[$k] = get_group( $anc_id );
				}
			}

			$groups   = array_merge( $groups, $ancestors );
			$groups[] = $group_id;
		}
	} else {

		// Return the groups
		$groups = array_merge( $groups, $user_groups );
	}

	return apply_filters( 'groupz_get_user_groups', $groups );
}

/**
 * Update the groups of the given user
 * 
 * @param int $user_id User id
 * @param array $groups Groups
 */
function groupz_update_user_groups( $user_id, $groups ) {

	// Sanitize user ID
	$user_id = (int) $user_id;

	// Sanitize group ids
	$group_ids = array_map( 'intval', $groups );

	// Hook before
	do_action( 'groupz_update_user_groups', $user_id, $group_ids );

	// Groups to keep
	$keep_groups = array();

	// Remove previous user groups
	foreach ( get_user_groups( $user_id ) as $group_id ) {

		if ( ! in_array( $group_id, $group_ids ) ) {
			groupz_remove_users( $group_id, $user_id );

		// Note if group is to keep
		} else {
			$keep_groups[] = $group_id;
		}
	}

	// Update new groups
	foreach ( $group_ids as $group_id ) {
		if ( ! in_array( $group_id, $keep_groups ) ) {
			groupz_add_users( $group_id, $user_id );
		}
	}

	// Hook after
	do_action( 'groupz_updated_user_groups', $user_id, $group_ids );
}

/**
 * Remove user from all groups
 *
 * @since 0.1
 * 
 * @param int $user_id User id
 */
function groupz_remove_user_groups( $user_id ) {

	// Sanitize user ID
	$user_id = (int) $user_id;

	// Hook before
	do_action( 'groupz_remove_user_groups', $user_id );

	// Remove user from groups
	foreach ( groupz_get_user_groups( $user_id ) as $group ) {
		groupz_update_users( $group->term_id, array_diff( $group->users, array( $user_id ) ) );
	}
}

/**
 * Return groups given user is not in
 * 
 * @since 0.1
 *
 * @uses get_groups() 
 * 
 * @param int $user_id User id
 * @param array $args Optional. Group query args
 * @return array Groups user is not in
 */
function groupz_get_not_user_groups( $user_id, $args ) {
	$defaults = array( 'not_user_id' => (int) $user_id );

	return get_groups( wp_parse_args( $args, $defaults ) );
}

/*****************************************************************/

/**
 * Helper function hooked to 'profile_update' action to save or
 * update user groups.
 *
 * @since 0.1
 *
 * @param int $user_id
 * @uses groupz_update_user_groups()
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
		groupz_update_user_groups( $user_id, $new_groups );
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

/** Admin Bar ****************************************************/

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

