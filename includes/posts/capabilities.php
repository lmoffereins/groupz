<?php

/**
 * Groupz Posts Capabilities
 *
 * @package Groupz
 * @subpackage Posts
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Mapping ******************************************************/

/**
 * Add groups capabilities for posts
 *
 * @since 0.x
 *
 * @param string $role Optional. Defaults to The role to load caps for
 * @uses apply_filters() Allow return value to be filtered
 *
 * @return array Capabilities for $role
 */
function groupz_posts_caps_for_role( $caps, $role ) {

	// Which role are we looking for?
	switch ( $role ) {

		// Administrator and Editor
		case 'administrator' :
		case 'editor'        :
			$caps = array_merge( $caps, array(

				// Manage edit group caps
				'assign_edit_groups'        => true,
				'assign_others_edit_groups' => true

			) );

			break;

		// Author
		case 'author' :
			$caps = array_merge( $caps, array(

				// Manage edit group caps
				'assign_edit_groups'        => true,
				'assign_others_edit_groups' => false
				
			) );

			break;
	}

	return apply_filters( 'groupz_posts_caps_for_role', $caps, $role );
}

