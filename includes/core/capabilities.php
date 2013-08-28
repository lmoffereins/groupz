<?php

/**
 * Groupz Capabilities
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Mapping ******************************************************/

/**
 * Returns an array of capabilities based on the role that is being requested
 *
 * @since 0.1
 *
 * @param string $role Optional. Defaults to The role to load caps for
 * @uses apply_filters() Allow return value to be filtered
 *
 * @return array Capabilities for $role
 */
function groupz_get_caps_for_role( $role = '' ) {

	// Which role are we looking for?
	switch ( $role ) {

		// Administrator and Editor
		case 'administrator' :
		case 'editor'        :
			$caps = array(

				// Primary caps
				'ignore_groups'             => true,
				'see_invisible_groups'      => true,
				'view_group_markings'       => true,
				'manage_group_users'        => true,

				// Manage group caps
				'manage_groups'             => true,
				'edit_groups'               => true,
				'delete_groups'             => true,
				'assign_groups'             => true,
				'assign_others_groups'      => true,
			);

			break;

		// Author
		case 'author' :
			$caps = array(

				// Primary caps
				'ignore_groups'             => false,
				'see_invisible_groups'      => false,
				'view_group_markings'       => false,
				'manage_group_users'        => false,

				// Manage group caps
				'manage_groups'             => false,
				'edit_groups'               => false,
				'delete_groups'             => false,
				'assign_groups'             => true,
				'assign_others_groups'      => false,
			);

			break;

		// Default
		default :

			// Ignore other roles. Prevents Groupz caps to be
			// assigned to bbPress-like dynamic roles that are
			// stored differently.
			$caps = array();

			break;
	}

	return apply_filters( 'groupz_get_caps_for_role', $caps, $role );
}

/**
 * Adds capabilities to WordPress user roles.
 *
 * @since 0.1
 *
 * @uses groupz_get_wp_roles()
 * @uses groupz_get_caps_for_role()
 * @uses do_action() Calls 'groupz_add_caps'
 */
function groupz_add_caps() {

	// Loop through available roles and add caps
	foreach ( groupz_get_wp_roles()->role_objects as $name => $role ){
		foreach ( groupz_get_caps_for_role( $name ) as $cap => $value ){
			$role->add_cap( $cap, $value );
		}
	}

	do_action( 'groupz_add_caps' );
}

/**
 * Removes capabilities from WordPress user roles
 *
 * @since 0.1
 *
 * @uses groupz_get_wp_roles()
 * @uses groupz_get_caps_for_role()
 * @uses do_action() Calls 'groupz_remove_caps'
 */
function groupz_remove_caps() {

	// Loop through available roles and remove caps
	foreach ( groupz_get_wp_roles()->role_objects as $name => $role ){
		foreach ( array_keys( groupz_get_caps_for_role( $name ) ) as $cap ){
			$role->remove_cap( $cap );
		}
	}

	do_action( 'groupz_remove_caps' );
}

/**
 * Get the $wp_roles global without needing to declare it everywhere
 *
 * @since 0.1
 * 
 * @global WP_Roles $wp_roles
 * @return WP_Roles
 */
function groupz_get_wp_roles() {
	global $wp_roles;

	// Load roles if not set
	if ( ! isset( $wp_roles ) )
		$wp_roles = new WP_Roles();

	return $wp_roles;
}
