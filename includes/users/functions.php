<?php

/**
 * Groupz User Functions
 *
 * @package Groupz
 * @subpackage Users
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/*****************************************************************/

/**
 * Helper function hooked to 'profile_update' action to save or
 * update user groups.
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
	if ( !isset( $_POST['groupz_user_groups_nonce'] ) )
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
 * @uses get_user_groups()
 * 
 * @param int $user_id User ID. Defaults to current user.
 * @return boolean User has group
 */
function user_has_group( $user_id = 0 ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$user_id     = (int) $user_id;
	$user_groups = get_user_groups( $user_id );
	$has         = !empty( $user_groups );

	return apply_filters( 'groupz_user_has_group', $has, $user_id );
}

/**
 * Return whether given user is in given group
 *
 * Checks if user is member of given group
 *
 * @uses get_user_groups()
 * @uses get_ancestors() To get the group ancestors
 * 
 * @param int $group_id Group ID
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $check_hierarchy Whether to evaluate including group
 *                                  hierarchy. Defaults to true.
 * @return boolean User is in group
 */
function user_in_group( $group_id, $user_id = 0, $check_hierarchy = true ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$group_id     = (int) $group_id;
	$user_id      = (int) $user_id;
	$_user_groups = get_user_groups( $user_id );
	$user_groups  = array();

	// Check for group ancestry
	if ( $check_hierarchy ){
		foreach ( $_user_groups as $group ){
			// Add found group ancestors to user groups
			$user_groups = array_unique( array_merge( $user_groups, get_ancestors( $group, groupz_get_group_tax_id() ) ) );
		}

		// Merge user ancestor groups with user groups
		$user_groups = array_unique( array_merge( $user_groups, $_user_groups ) );
	} else {
		$user_groups = $_user_groups;
	}

	$found = in_array( $group_id, $user_groups );
	return apply_filters( 'groupz_user_in_group', $found, $group_id, $user_id, $check_hierarchy );
}

/**
 * Return whether given user can read given post through groups
 *
 * Read privilege can be inherited through group hierarchy.
 * Read privilege can be inherited through post hierarchy.
 * 
 * @param int $post_id Post ID. Defaults to current post.
 * @param int $user_id User ID. Defaults to current user.
 * @param boolean $check_hierarchy Whether to evaluate including group
 *                                  hierarchy. Defaults to true.
 * @return boolean User is in post groups
 */
function user_in_post_groups( $post_id = 0, $user_id = 0, $check_hierarchy = true ){
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$user_id     = (int) $user_id;
	$post_id     = (int) $post_id;
	$post_groups = get_post_groups( $post_id, false );

	// Assume no membership
	$found       = false;

	// Post has no groups so any user has access to this post
	if ( empty( $post_groups ) )
		$found = true;

	// Not logged in users are never in a group
	if ( 0 == $user_id && !$found )
		return apply_filters( 'groupz_user_in_post_groups', $found, $post_id, $user_id, $check_hierarchy );

	// Loop over all post groups
	foreach ( $post_groups as $group ){

		// Is user in a group
		if ( in_array( $user_id, $group->users ) )
			$found = true;

		// Look for user in child groups if not found yet
		if ( !$found && $check_hierarchy ){
			foreach ( get_term_children( $group->term_id, groupz_get_group_tax_id() ) as $group_id ){
				if ( in_array( $user_id, get_group_users( $group_id ) ) )
					$found = true; break;
			}
		}

		if ( $found )
			break;
	}
	
	// Search read privilege for post ancestors if user has still access
	$_post = get_post( $post_id );
	if ( $found && 0 != $_post->post_parent ){
		$found = call_user_func_array( __FUNCTION__, array( $_post->post_parent, $user_id, $check_hierarchy ) );
	}

	return apply_filters( 'groupz_user_in_post_groups', $found, $post_id, $user_id, $check_hierarchy );
}

/**
 * Return whether given user can edit given post through groups
 *
 * Edit permissions can be inherited through group hierarchy.
 * Edit permissions can NOT be inherited through post hierarchy.
 * 
 * @param int $post_id Post ID. Defaults to current post.
 * @param int $user_id User ID. Defaults to current user.
 * @return boolean User is in post edit groups
 */
function user_in_post_edit_groups( $post_id = 0, $user_id = 0, $check_hierarchy = true ){
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	$user_id = (int) $user_id;
	$post_id = (int) $post_id;
	
	// Assume no membership
	$found   = false;

	// Not logged in users are never in a group
	if ( 0 == $user_id )
		return apply_filters( 'groupz_user_in_post_edit_groups', $found, $post_id, $user_id, $check_hierarchy );

	// Loop over all post edit groups
	foreach ( get_post_edit_groups( $post_id, false ) as $group ){

		// Is user in a group
		if ( in_array( $user_id, $group->users ) )
			$found = true;

		// Check child groups if not found yet
		if ( !$found && $check_hierarchy ){
			foreach ( get_term_children( $group->term_id, groupz_get_group_tax_id() ) as $group_child ){
				if ( in_array( $user_id, get_group_users( $group_child ) ) )
					$found = true; break;
			}
		}

		if ( $found )
			break;
	}

	return apply_filters( 'groupz_user_in_post_edit_groups', $found, $post_id, $user_id );
}

/**
 * Adds create group link to the admin bar
 *
 * @uses WP_Admin_Bar::add_node()
 * @param object $wp_admin_bar
 */
function groupz_admin_bar( $wp_admin_bar ){
	if ( !current_user_can( 'manage_groups' ) )
		return;

	// Add menu item to admin bar
	$wp_admin_bar->add_node( array(
		'parent' => 'new-content', // Add to new-content menu node
		'id'     => 'new-group',
		'title'  => __('Group', 'groupz'),
		'href'   => groupz_get_admin_page_url()
		) );
}

