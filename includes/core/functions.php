<?php

/**
 * Groupz Functions
 *
 * @package Groupz
 * @subpackage Core
 */

/*****************************************************************/

/**
 * Act on plugin activation
 */
function groupz_activation(){
	do_action( 'groupz_activation' );
}

/**
 * Act on plugin deactivation
 */
function groupz_deactivation(){
	do_action( 'groupz_deactivation' );
}

/**
 * Act on plugin uninstallation
 */
function groupz_uninstall(){
	do_action( 'groupz_uninstall' );
}

/*****************************************************************/

/**
 * Return group taxonomy id
 *
 * @return string Group taxonomy ID
 */
function groupz_get_group_tax_id(){
	return groupz()->group_tax_id;
}

/**
 * Return whether given ID is a group ID
 * 
 * @param int $id Term ID
 * @return boolean ID is a group
 */
function groupz_is_group( $id ){
	return term_exists( $id, groupz_get_group_tax_id() );
}

/**
 * Return whether given group is an edit group
 * 
 * @param int $id Term ID
 * @return boolean ID is edit group
 */
function groupz_is_edit_group( $id ){
	$group = get_group( (int) $id );
	return $group->is_edit;
}

/**
 * Return requested group object
 *
 * @param int $id Group ID
 * @return object Group
 */
function get_group( $id ){
	return get_term( (int) $id, groupz_get_group_tax_id() );
}

/**
 * Return requested group name
 * 
 * @param int $id Group ID
 * @return string Group name
 */
function get_group_name( $id ){
	return get_group( $id )->name;
}

/**
 * Return requested group users
 * 
 * @param int $id Group ID
 * @return array Group users
 */
function get_group_users( $id ){
	return get_group( $id )->users;
}

/**
 * Return all groups. The base function
 *
 * @uses get_terms() To find the groups
 * 
 * @param array $args Optional. Arguments for get_terms()
 * @return array $groups
 */
function get_groups( $args = array() ){
	return get_terms( groupz_get_group_tax_id(), wp_parse_args( $args, array( 'hide_empty' => 0 ) ) );
}

/**
 * Return all edit groups
 *
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_edit_groups( $ids = true ){
	return get_groups( array( 'is_edit' => true, 'ids' => $ids ) );
}

/**
 * Return the groups of the given user
 * 
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $ids Whether to return ids or terms
 * @param boolean $include_ancestors Whether to insert groups from the users group ancestor tree
 * @return array $groups
 */
function get_user_groups( $user_id = 0, $ids = true, $include_ancestors = false ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	return groupz()->core->get_user_groups( (int) $user_id, array( 'fields' => $ids ? 'ids' : 'all' ), $include_ancestors );	
}

/**
 * Return the groups not containing the given user
 * 
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $ids Whether to return ids or terms
 * @param boolean $exclude_ancestors Whether to withhold groups from the users group ancestor tree
 * @return array $groups
 */
function get_not_user_groups( $user_id = 0, $ids = true, $exclude_ancestors = false ){
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	return groupz()->core->get_not_user_groups( (int) $user_id, array( 'fields' => $ids ? 'ids' : 'all' ), $exclude_ancestors );
}

/**
 * Return the groups of the given post
 * 
 * @param int|array $post_id The post ID. Defaults to current post
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_post_groups( $post_id = 0, $ids = true ){
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	return groupz()->core->get_post_groups( (int) $post_id, $ids );
}

/**
 * Return the edit groups of the given post
 * 
 * @param int|array $post_id The post ID. Defaults to current post
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_post_edit_groups( $post_id = 0, $ids = true ){
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	return groupz()->core->get_post_edit_groups( (int) $post_id, $ids );
}

/** List Groups **************************************************/

/**
 * Return an unordered list of edit groups
 * 
 * @param array $args See get_groups()
 * @return string $list
 */
function list_edit_groups( $args = array() ){
	$defaults = array(
		'ul_class' => 'list_groups edit_groups',
		'is_edit' => true, 'echo' => 1
		);
	$args = wp_parse_args( $args, $defaults );

	return list_groups( $args );
}

/**
 * Return an unordered list of user groups
 * 
 * @param int $user_id User ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_user_groups( $user_id, $args = array() ){
	$defaults = array(
		'ul_class' => 'list_groups user_groups',
		'echo' => 1
		);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['user_id'] = (int) $user_id;

	return list_groups( $args );
}

/**
 * Return an unordered list of groups for given post
 * 
 * @param int $post_id Post ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_post_groups( $post_id, $args = array() ){
	$defaults = array(
		'class' => 'list_groups post_groups',
		'echo' => 1,
		);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['post_id'] = (int) $post_id;

	// Handle echoing
	if ( $args['echo'] ){
		$echo = true;
		$args['echo'] = false;
	} else {
		$echo = false;
	}

	add_filter( 'get_terms', 'list_post_groups_filter', 10, 3 );
	$list = list_groups( $args );
	remove_filter( 'get_terms', 'list_post_groups_filter' );

	if ( $echo )
		echo $list;
	else
		return $list;
}

/**
 * Filter get terms for post groups only
 *
 * Assumes we're only handling groups here.
 * 
 * @param array $terms The found groups
 * @param array $taxonomies Requested taxonomies
 * @param array $args The get_terms() arguments
 * @return array $terms
 */
function list_post_groups_filter( $terms, $taxonomies, $args ){

	// Bail if no post ID
	if ( !isset( $args['post_id'] ) )
		return $terms;

	// Get post groups IDs
	$includes = get_post_groups( $args['post_id'] );

	foreach ( $terms as $k => $term ){
		if ( is_object( $term ) && !in_array( $term->term_id, $includes ) )
			unset( $terms[$k] );
		elseif ( !is_object( $term ) && !in_array( (int) $term, $includes ) )
			unset( $terms[$k] );
	}

	return $terms;
}

/**
 * Return an unordered list of item groups
 * 
 * @param int $post_id Post ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_post_edit_groups( $post_id, $args = array() ){
	$defaults = array(
		'class' => 'list_groups post_edit_groups',
		'echo' => 1
		);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['post_id'] = (int) $post_id;

	// Handle echoing
	if ( $args['echo'] ){
		$echo = true;
		$args['echo'] = false;
	} else {
		$echo = false;
	}

	add_filter( 'get_terms', 'list_post_edit_groups_filter', 10, 3 );
	$list = list_groups( $args );
	remove_filter( 'get_terms', 'list_post_edit_groups_filter' );

	if ( $echo )
		echo $list;
	else
		return $list;
}

/**
 * Filter get terms for post edit groups only
 *
 * Assumes we're only handling groups here.
 * 
 * @param array $terms The found groups
 * @param array $taxonomies Requested taxonomies
 * @param array $args The get_terms() arguments
 * @return array $terms
 */
function list_post_edit_groups_filter( $terms, $taxonomies, $args ){

	// Bail if no post ID
	if ( !isset( $args['post_id'] ) )
		return $terms;

	// Get post edit groups IDs
	$includes = get_post_edit_groups( $args['post_id'] );

	foreach ( $terms as $k => $term ){
		if ( is_object( $term ) && !in_array( $term->term_id, $includes ) )
			unset( $terms[$k] );
		elseif ( !is_object( $term ) && !in_array( (int) $term, $includes ) )
			unset( $terms[$k] );
	}

	return $terms;
}

/** Select Groups ************************************************/

/**
 * Output input element to select groups
 *
 * @uses get_select_groups()
 * 
 * @param array $args Additional arguments
 */
function select_groups( $args = array() ){
	echo get_select_groups( $args );
}

	/**
	 * Return input element to select groups
	 *
	 * When the Fancy Select option is selected this
	 * returns a select element - else checkboxes
	 * 
	 * @param array $args Additional arguments
	 * @return string Input element
	 */
	function get_select_groups( $args = array() ){

		// Do multiple select
		if ( groupz()->admin->use_chosen ){
			$defaults = array(
				'name'  => 'select_groups[]',
				'echo'  => false, 'multiple' => true, 'hierarchical' => true,
				'style' => sprintf( 'data-placeholder="%s"', __('Select a group', 'groupz') ), 'width' => 0, // !
				'class' => 'select_groups dropdown_groups chzn-select' // With chosen class
				);
			$args = wp_parse_args( $args, $defaults );

			// ! Can do better
			if ( $args['width'] )
				$args['style'] .= sprintf( ' style="width:%s;"', is_int( $args['width'] ) ? (string) $args['width'] .'px' : $args['width'] );

			add_filter( 'list_groups', 'groupz_dropdown_show_parent', 10, 3 );
			$dropdown = dropdown_groups( $args );
			remove_filter( 'list_groups', 'groupz_dropdown_show_parent' );

			return $dropdown;
		} 

		// Do checkboxes
		else {
			$defaults = array(
				'name'  => 'select_groups[]',
				'echo'  => false, 
				'class' => 'select_groups list_groups checkbox_groups'
				);
			$args = wp_parse_args( $args, $defaults );

			return checkbox_groups( $args );
		}
	}

/**
 * Return dropdown option label with appended parent name when given
 * 
 * @param string $label Group label
 * @param object $group Group
 * @param array $args Optional
 * @return string $label
 */
function groupz_dropdown_show_parent( $label, $group, $args = array() ){

	// Bail if setting not set
	if ( !get_option( 'groupz_dropdown_show_parent' ) )
		return $label;
	
	// Amend group label with group parent
	if ( 0 != $group->parent )
		$label .= sprintf( ' (%s)', get_group_name( $group->parent ) );

	return $label;
}

/** Misc *********************************************************/

/**
 * Return group admin page url
 * 
 * @return string Url
 */
function groupz_get_admin_page_url(){
	return add_query_arg( 'taxonomy', groupz_get_group_tax_id(), admin_url( 'edit-tags.php' ) );
}

/** Post *********************************************************/

/**
 * Return whether the given post has any group assigned
 * 
 * @param int $post_id The post ID
 * @param boolean $check_hierarchy Whether to walk the post ancestor tree
 * @return boolean Post has any group
 */
function groupz_post_has_group( $post_id = 0, $check_hierarchy = false ){
	if ( empty( $post_id ) )
		$post_id = null;

	// Has post any group
	$has = has_term( '', groupz_get_group_tax_id(), $post_id );

	// Walk post hierarchy when required
	if ( !$has && !empty( $post_id ) && $check_hierarchy ){
		foreach ( get_post_ancestors( $post_id ) as $ancestor_id ){
			$has = has_term( '', groupz_get_group_tax_id(), $ancestor_id );

			// Break the loop when group was found
			if ( $has )
				break;
		}
	}

	return apply_filters( 'groupz_post_has_group', $has, $post_id, $check_hierarchy );
}

/**
 * Return whether the given post is assigned to the given group
 * 
 * @param int $group_id Group ID
 * @param int $post_id Post ID
 * @param boolean $check_hierarchy Whether to walk the post ancestor tree
 * @return boolean Post is in the given group
 */
function groupz_post_in_group( $group_id, $post_id = 0, $check_hierarchy = true ){
	if ( empty( $post_id ) )
		$post_id = null;

	$group_id = (int) $group_id;

	// Is post in the group
	$found = has_term( $group_id, groupz_get_group_tax_id(), $post_id );

	// Walk post hierarchy
	if ( !$found && !empty( $post_id ) && $check_hierarchy ){
		foreach ( get_post_ancestors( $post_id ) as $ancestor_id ){
			$found = has_term( $group_id, groupz_get_group_tax_id(), $ancestor_id );

			// Break the loop when group was found
			if ( $found )
				break;
		}
	}

	return apply_filters( 'groupz_post_in_group', $found, $group_id, $post_id, $check_hierarchy );
}

/** Capabilities *************************************************/

/**
 * Return whether given post type has read privilege enabled
 *
 * Returns also true if post type equals 'any'. An empty string
 * will be handled like WP_Query does and will eventually default 
 * to 'post' if no WP_Query object was given.
 * 
 * @param string $post_type The post type name
 * @param WP_Query $query Optional
 * @return boolean
 */
function groupz_is_read_post_type( $post_type = '', $query = '' ){

	// Default param if post type is empty
	if ( empty( $post_type ) && is_object( $query ) && is_a( $query, 'WP_Query' ) ){
		if ( $query->is_attachment )
			$post_type = 'attachment';
		elseif ( $query->is_page )
			$post_type = 'page';
		else
			$post_type = 'post';
	} elseif ( empty( $post_type ) ){
		$post_type = 'post';
	}

	// Add 'any' as accepted read post type
	$read_post_types = array_merge( array( 'any' ), groupz()->get_read_post_types() );

	return in_array( $post_type, $read_post_types );
}

/**
 * Return whether given post type has edit privilege enabled
 * 
 * @param string $post_type The post type name
 * @return boolean
 */
function groupz_is_edit_post_type( $post_type = '' ){
	if ( empty( $post_type ) )
		$post_type = 'post';

	return in_array( $post_type, groupz()->get_edit_post_types() );
}

/**
 * Return whether given post type or one of an array of post types
 * has read privilege enabled
 * 
 * @param string|array $maybe_array The post type(s)
 * @param WP_Query $query Optional
 * @return boolean
 */
function groupz_is_read_post_type_maybe_array( $maybe_array, $query = '' ){
	foreach ( (array) $maybe_array as $post_type ){
		if ( groupz_is_read_post_type( $post_type, $query ) )
			return true;
	}
	return false;
}

