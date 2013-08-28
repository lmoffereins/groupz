<?php

/**
 * Groupz Edit and Posts Functions
 *
 * @package Groupz
 * @subpackage Posts
 */

/** Edit Groups **************************************************/

/**
 * Add is_edit param to default group params
 *
 * @since 0.x
 * 
 * @param array $params Group params
 * @return array $params
 */
function groupz_posts_is_edit_group_param( $params ) {
	$params['is_edit'] = array(
		'label'           => __('Edit group', 'groupz'),
		'description'     => __('Whether this group can be used for edit privilege.', 'groupz'),
		'field_callback'  => 'groupz_field_is_edit',
		'get_callback'    => 'groupz_get_is_edit',
		'update_callback' => 'groupz_update_is_edit'
	);

	return $params;
}

/**
 * Output group is edit group param field
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 */
function groupz_is_edit_field( $group_id ) {
	?>
		<input name="groupz_is_edit" type="checkbox" id="groupz_is_edit" value="1" <?php checked( groupz_get_is_edit( $group_id ) ); ?>/>
	<?php
}

/**
 * Return whether group is an edit group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return boolean Group is edit group
 */
function groupz_get_is_edit( $group_id ) {
	return (bool) get_group_meta( $group_id, 'is_edit' );
}

/**
 * Update whether group is an edit group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param boolean $is_edit Group is edit group
 * @return boolean Update success
 */
function groupz_update_is_edit( $group_id, $is_edit ) {
	if ( groupz_get_is_edit( $group_id ) == $is_edit )
		return;

	do_action( 'groupz_update_is_edit', $group_id, $is_edit );

	return update_group_meta( $group_id, 'is_edit', (bool) $is_edit );
}

/**
 * Add is_edit to the group setup parameters
 *
 * @since 0.x
 * 
 * @param array $params Group params
 * @return array $params
 */
function groupz_posts_is_edit_filter_property( $params ) {
	return array_merge( $params, array( 'is_edit' ) );
}

/**
 * Return whether given group is an edit group
 *
 * @since 0.1
 * 
 * @param int $id Term ID
 * @return boolean ID is edit group
 */
function groupz_is_edit_group( $id ) {
	$group = get_group( (int) $id );
	return $group->is_edit;
}

/**
 * Return all edit groups
 *
 * @since 0.1
 *
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_edit_groups( $ids = true ) {
	return get_groups( array( 'is_edit' => true, 'ids' => $ids ) );
}

/**
 * Return an unordered list of edit groups
 * 
 * @since 0.1
 * 
 * @param array $args See get_groups()
 * @return string $list
 */
function list_edit_groups( $args = array() ) {
	$defaults = array(
		'ul_class' => 'list_groups edit_groups',
		'is_edit' => true, 'echo' => 1
	);
	$args = wp_parse_args( $args, $defaults );

	return list_groups( $args );
}

/** Post Groups **************************************************/

/**
 * Return whether the given post has any group assigned
 *
 * @since 0.1
 * 
 * @param int $post_id The post ID
 * @param boolean $hier Whether to walk the post ancestor tree
 * @return boolean Post has any group
 */
function groupz_post_has_group( $post_id = 0, $hier = false ) {
	if ( empty( $post_id ) )
		$post_id = null;

	// Has post any group
	$has = has_term( '', groupz_get_group_tax_id(), $post_id );

	// Walk post hierarchy when required
	if ( ! $has && ! empty( $post_id ) && $hier ) {
		foreach ( get_post_ancestors( $post_id ) as $ancestor_id ) {
			$has = has_term( '', groupz_get_group_tax_id(), $ancestor_id );

			// Break the loop when group was found
			if ( $has )
				break;
		}
	}

	return apply_filters( 'groupz_post_has_group', $has, $post_id, $hier );
}

/**
 * Return whether the given post is assigned to the given group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param int $post_id Post ID
 * @param boolean $hier Whether to walk the post ancestor tree
 * @return boolean Post is in the given group
 */
function groupz_post_in_group( $group_id, $post_id = 0, $hier = true ) {
	if ( empty( $post_id ) )
		$post_id = null;

	$group_id = (int) $group_id;

	// Is post in the group
	$found = has_term( $group_id, groupz_get_group_tax_id(), $post_id );

	// Walk post hierarchy
	if ( ! $found && ! empty( $post_id ) && $hier ) {
		foreach ( get_post_ancestors( $post_id ) as $ancestor_id ) {
			$found = has_term( $group_id, groupz_get_group_tax_id(), $ancestor_id );

			// Break the loop when group was found
			if ( $found )
				break;
		}
	}

	return apply_filters( 'groupz_post_in_group', $found, $group_id, $post_id, $hier );
}

/**
 * Return all groups of given post
 *
 * @since 0.1
 * 
 * @param int|array $post_id The post ID. Defaults to current post
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_post_groups( $post_id = 0, $ids = true ) {
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	return groupz_get_post_groups( (int) $post_id, $ids );
}

/**
 * Return an unordered list of groups for given post
 *
 * @since 0.1
 * 
 * @param int $post_id Post ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_post_groups( $post_id, $args = array() ) {
	$defaults = array(
		'class' => 'list_groups post_groups',
		'echo' => 1,
	);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['post_id'] = (int) $post_id;

	// Handle echoing
	if ( $args['echo'] ) {
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
 * @since 0.1
 * 
 * @param array $terms The found groups
 * @param array $taxonomies Requested taxonomies
 * @param array $args The get_terms() arguments
 * @return array $terms
 */
function list_post_groups_filter( $terms, $taxonomies, $args ) {

	// Bail if no post ID
	if ( ! isset( $args['post_id'] ) )
		return $terms;

	// Get post groups IDs
	$includes = get_post_groups( $args['post_id'] );

	foreach ( $terms as $k => $term ) {
		if ( is_object( $term ) && ! in_array( $term->term_id, $includes ) )
			unset( $terms[$k] );
		elseif ( ! is_object( $term ) && ! in_array( (int) $term, $includes ) )
			unset( $terms[$k] );
	}

	return $terms;
}

/**
 * Return the edit groups of the given post
 *
 * @since 0.1
 * 
 * @param int|array $post_id The post ID. Defaults to current post
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups
 */
function get_post_edit_groups( $post_id = 0, $ids = true ) {
	if ( empty( $post_id ) )
		$post_id = get_the_ID();

	return groupz_get_post_edit_groups( (int) $post_id, $ids );
}

/**
 * Return an unordered list of item groups
 *
 * @since 0.1
 * 
 * @param int $post_id Post ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_post_edit_groups( $post_id, $args = array() ) {
	$defaults = array(
		'class' => 'list_groups post_edit_groups',
		'echo' => 1
	);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['post_id'] = (int) $post_id;

	// Handle echoing
	if ( $args['echo'] ) {
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
 * @since 0.1
 * 
 * @param array $terms The found groups
 * @param array $taxonomies Requested taxonomies
 * @param array $args The get_terms() arguments
 * @return array $terms
 */
function list_post_edit_groups_filter( $terms, $taxonomies, $args ) {

	// Bail if no post ID
	if ( ! isset( $args['post_id'] ) )
		return $terms;

	// Get post edit groups IDs
	$includes = get_post_edit_groups( $args['post_id'] );

	foreach ( $terms as $k => $term ) {
		if ( is_object( $term ) && ! in_array( $term->term_id, $includes ) )
			unset( $terms[$k] );
		elseif ( ! is_object( $term ) && ! in_array( (int) $term, $includes ) )
			unset( $terms[$k] );
	}

	return $terms;
}

/**
 * Return whether given post type has read privilege enabled
 *
 * Returns also true if post type equals 'any'. An empty string
 * will be handled like WP_Query does and will eventually default 
 * to 'post' if no WP_Query object was given.
 *
 * @since 0.1
 * 
 * @param string $post_type The post type name
 * @param WP_Query $query Optional
 * @return boolean
 */
function groupz_is_read_post_type( $post_type = '', $query = '' ) {

	// Default param if post type is empty
	if ( empty( $post_type ) && is_object( $query ) && is_a( $query, 'WP_Query' ) ) {
		if ( $query->is_attachment )
			$post_type = 'attachment';
		elseif ( $query->is_page )
			$post_type = 'page';
		else
			$post_type = 'post';
	} elseif ( empty( $post_type ) ) {
		$post_type = 'post';
	}

	// Add 'any' as accepted read post type
	$read_post_types = array_merge( array( 'any' ), groupz()->get_read_post_types() );

	return in_array( $post_type, $read_post_types );
}

/**
 * Return whether given post type has edit privilege enabled
 * 
 * @since 0.1
 * 
 * @param string $post_type The post type name
 * @return boolean
 */
function groupz_is_edit_post_type( $post_type = '' ) {
	if ( empty( $post_type ) )
		$post_type = 'post';

	return in_array( $post_type, groupz()->get_edit_post_types() );
}

/**
 * Return whether given post type or one of an array of post types
 * has read privilege enabled
 *
 * @since 0.1
 * 
 * @param string|array $maybe_array The post type(s)
 * @param WP_Query $query Optional
 * @return boolean
 */
function groupz_is_read_post_type_maybe_array( $maybe_array, $query = '' ) {
	foreach ( (array) $maybe_array as $post_type ) {
		if ( groupz_is_read_post_type( $post_type, $query ) )
			return true;
	}
	return false;
}

/** Manage Post Groups *******************************************/

/**
 * Return all groups for the given item
 *
 * @since 0.1
 *
 * @uses get_the_terms() To get the associated groups
 * 
 * @param int $post_id The requested post ID
 * @param boolean $ids Whether to return group ids or terms
 * @return array $groups
 */
function groupz_get_post_groups( $post_id, $ids = false ) {

	// Get the post groups as terms
	$groups = wp_get_post_terms( 
		(int) $post_id, 
		groupz_get_group_tax_id(),
		array( 'fields' => $ids ? 'ids' : 'all' )
	);

	// Force integers
	if ( $ids )
		$groups = array_map( 'intval', $groups );

	return apply_filters( 'groupz_get_post_groups', $groups, $post_id, $ids );
}

/**
 * Update the post groups relationships
 *
 * Assumes permissions checks before executing this function.
 *
 * @since 0.1
 *
 * @uses wp_set_post_terms() To update the post groups
 * 
 * @param int $post_id The post ID
 * @param array $groups The new associated group ids
 */
function groupz_update_post_groups( $post_id, $groups ) {

	// Sanitize group ids
	$group_ids = array_map( 'intval', $groups );

	// Hook before
	do_action( 'groupz_update_post_groups', $post_id, $group_ids );

	// Update
	wp_set_post_terms( $post_id, $group_ids, groupz_get_group_tax_id() );

	// Hook after
	do_action( 'groupz_updated_post_groups', $post_id, $group_ids );
}

/**
 * Post-term relationships are automatically deleted on 
 * wp_delete_post() with wp_delete_object_term_relationships(),
 * so we don't have to.
 *
 * To hook into post group relationship changes use
 * 'groupz_added_post_group' or 'groupz_removed_post_groups'
 * actions.
 *
 * @since 0.1
 * 
 * @param int $post_id The post ID
 * @return int $post_id
 */
function groupz_remove_post_groups( $post_id ) {
	return $post_id;
}

/**
 * Return posts that have the given group assigned
 *
 * @since 0.1
 * 
 * @param int|array $group_id The group ID
 * @param boolean $ids Whether to return ids or terms
 * @return array $posts
 */
function groupz_get_group_posts( $group_id, $ids = false ) {

	// Setup query args
	$args = array(
		'post_type' => 'any',
		'fields'    => $ids ? 'ids' : 'all',
		'tax_query' => array(
			array(
				'taxonomy' => groupz_get_group_tax_id(),
				'field'    => 'id',
				'terms'    => is_array( $group_id ) ? array_map( 'intval', $group_id ) : (int) $group_id
			)
		)
	);

	// Setup query
	$posts = new WP_Query;

	// Return the query result
	return $posts->query( $args );
}

/**
 * Hook after a new post group relationship
 *
 * @since 0.1
 *
 * @uses groupz_is_group() To check if we're handling a group
 * @uses do_action() To call 'groupz_added_post_group'
 * 
 * @param int $post_id Post ID
 * @param int $term_id Term ID
 */
function groupz_added_post_group( $post_id, $term_id ) {

	// Bail for revisions
	if ( wp_is_post_revision( $post_id ) ) 
		return;

	if ( groupz_is_group( (int) $term_id ) )
		do_action( 'groupz_added_post_group', $post_id, (int) $term_id );
}

/**
 * Create a hook for a removed post group relationship
 *
 * @since 0.1
 *
 * @uses groupz_is_group() To check if we're handling a group
 * @uses do_action() To call 'groupz_removed_post_groups'
 * 
 * @param int $post_id Post ID
 * @param int $removed_terms Term ids
 */
function groupz_removed_post_groups( $post_id, $removed_terms ) {

	// Bail for revisions
	if ( wp_is_post_revision( $post_id ) ) 
		return;

	// Setup remove groups
	$remove_groups = array();

	// Loop removed terms
	foreach ( $removed_terms as $term_id ) {
		if ( groupz_is_group( (int) $term_id ) )
			$remove_groups[] = (int) $term_id;
	}

	// Act if removed terms are groups
	if ( ! empty( $remove_groups ) )
		do_action( 'groupz_removed_post_groups', $post_id, $remove_groups );
}

/** Manage Post Edit Groups **************************************/

/**
 * Return all edit groups for the given post
 *
 * NOTE: edit groups are not stored as terms of the
 * post, but as post meta. Therefor we're not using 
 * get_the_terms() here, but instead fetch the
 * stored edit groups post meta.
 *
 * @since 0.1
 * 
 * @param int $post_id The requested post ID
 * @param boolean $ids Whether to return ids or terms
 * @return array $groups Objects or IDs
 */
function groupz_get_post_edit_groups( $post_id, $ids = false ) {

	// Get stored post edit groups
	$group_ids = (array) get_post_meta( $post_id, groupz()->pre_meta . 'edit_groups', true );

	// Return terms
	if ( ! $ids ) {

		// One SQL call for all groups instead of mutiple calls with get_term_by()
		$groups = get_edit_groups(); 

		// Remove not found groups from return var
		foreach ( $groups as $k => $group ) {
			if ( ! in_array( $group->term_id, $group_ids ) )
				unset( $groups[$k] );
		}

	// Return ids
	} else { 
		$groups = $group_ids;
	}

	return $groups;
}

/**
 * Update the post edit groups relationships
 *
 * @since 0.1
 *
 * @uses update_post_meta() To store the new post edit groups
 * @uses do_action() To call 'groupz_update_post_edit_groups' and
 *                    'groupz_updated_edit_groups'
 * 
 * @param int $post_id The post ID
 * @param array $groups The groups to store, array of ids
 */
function groupz_update_post_edit_groups( $post_id, $groups ) {

	// Sanitize post ID
	$post_id = (int) $post_id;

	// Sanitize group ids
	$group_ids = array_map( 'intval', $groups );

	// Hook before
	do_action( 'groupz_update_post_edit_groups', $post_id, $group_ids );

	// Groups to keep
	$keep_groups = array();

	// Hook for previous user groups
	foreach ( get_user_groups( $user_id ) as $group_id ) {

		/**
		 * NB: This does not apply to posts being saved with 
		 * wp_insert_post() or aliases of it. Existing posts will
		 * be stored as a revision whilst the udpated post becomes 
		 * a 'new' post without previous assigned groups. So there
		 * is nothing to remove.
		 */
		if ( ! in_array( $group_id, $group_ids ) ) {
			do_action( 'groupz_remove_post_edit_group', $post_id, $group_id );

		// Note if group is to keep
		} else {
			$keep_groups[] = $group_id;
		}
	}

	// Hook for new groups
	foreach ( $group_ids as $group_id ) {
		if ( ! in_array( $group_id, $keep_groups ) ) {
			do_action( 'groupz_add_post_edit_group', $post_id, $group_id );
		}
	}

	// Update
	update_post_meta( $post_id, groupz()->pre_meta . 'edit_groups', $group_ids );

	// Hook after
	do_action( 'groupz_updated_post_edit_groups', $post_id, $group_ids );
}

/**
 * Remove all post edit groups relationships
 *
 * @since 0.1
 * 
 * @uses delete_post_meta() To delete the post edit groups data
 * @uses do_action() To call 'groupz_remove_post_edit_groups'
 * 
 * @param int $post_id The post ID
 * @return boolean Success or fail of deleting post meta
 */
function groupz_remove_post_edit_groups( $post_id ) {
	
	// Hook before
	do_action( 'groupz_remove_post_edit_groups', $post_id );

	// Delete
	return delete_post_meta( $post_id, groupz()->pre_meta . 'edit_groups' );
}

/**
 * Return requested posts having edit groups meta stored
 *
 * Defaults 'fields' to 'ids'. Set the argument to 'all'
 * to return post objects.
 *
 * @since 0.1
 *
 * @param array $args Optional. Query arguments
 * @return array $posts
 */
function groupz_get_posts_with_edit_groups( $args = array() ) {
	
	// Setup query args
	$defaults = array(
		'post_type' => groupz()->get_edit_post_types(),
		'fields'    => 'ids',
		'meta_key'  => groupz()->pre_meta . 'edit_groups' // Make sure post has at least one edit group
	);
	$args = wp_parse_args( $args, $defaults );

	// Setup query
	$posts = new WP_Query;

	// Return the query result
	return $posts->query( $args );
}

/**
 * Return all posts that have given group as edit group
 *
 * @since 0.1
 * 
 * @param int $group_id The edit group ID
 * @param array $query_args Optional WP_Query arguments
 * @return array $posts
 */
function groupz_get_edit_group_posts( $group_id, $query_args = array() ) {

	// The posts with edit groups
	$posts = groupz_get_posts_with_edit_groups( $query_args );

	// Loop over all posts
	foreach ( $posts as $k => $post ) {
		$ids = groupz_get_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, true );

		// Remove post from array if edit groups do not contain group ID
		if ( !in_array( $group_id, $ids ) )
			unset( $posts[$k] );
	}

	return $posts;
}

/**
 * Remove a group from all posts edit groups
 *
 * Runs when a group is deleted and its reference 
 * to all posts as edit group has to be removed.
 *
 * @since 0.1
 * 
 * @uses Groups_Core::get_posts_with_edit_groups()
 * @uses Groups_Core::get_post_edit_groups()
 * @uses Groups_Core::update_post_edit_groups()
 * 
 * @param int $group_id The group ID to remove
 */
function groupz_remove_edit_group_from_posts( $group_id ) {

	// The posts with edit groups meta key
	$posts = groupz_get_posts_with_edit_groups();

	// Loop over all posts
	foreach ( $posts as $post ) {
		$ids = groupz_get_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, true );

		// Update post edit groups with group ID removed
		if ( in_array( $group_id, $ids ) )
			groupz_update_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, array_diff( $ids, array( $group_id ) ) );
	}
}

/** Users ********************************************************/

/**
 * Return whether given user is in group of given post
 *
 * Read privilege can be inherited through group hierarchy.
 * Read privilege can be inherited through post hierarchy.
 *
 * @since 0.1
 * 
 * @param int $post_id Post ID. Defaults to current post.
 * @param int $user_id User ID. Defaults to current user.
 * @param boolean $hier Whether to evaluate including group
 *                        hierarchy. Defaults to true.
 * @return boolean User is in post groups
 */
function groupz_user_in_post_groups( $post_id = 0, $user_id = 0, $hier = true ) {
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
		return apply_filters( 'groupz_user_in_post_groups', $found, $post_id, $user_id, $hier );

	// Loop over all post groups
	foreach ( $post_groups as $group ){

		// Is user in a group
		if ( in_array( $user_id, $group->users ) )
			$found = true;

		// Look for user in child groups if not found yet
		if ( ! $found && $hier ){
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
	if ( $found && 0 != $_post->post_parent ) {
		$found = call_user_func_array( __FUNCTION__, array( $_post->post_parent, $user_id, $hier ) );
	}

	return apply_filters( 'groupz_user_in_post_groups', $found, $post_id, $user_id, $hier );
}

/**
 * Return whether given user is in edit group of given post
 *
 * Edit permissions can be inherited through group hierarchy.
 * Edit permissions can NOT be inherited through post hierarchy.
 *
 * @since 0.1
 * 
 * @param int $post_id Post ID. Defaults to current post.
 * @param int $user_id User ID. Defaults to current user.
 * @param boolean $hier Whether to evaluate including group
 *                       hierarchy. Defalts to true.
 * @return boolean User is in post edit groups
 */
function groupz_user_in_post_edit_groups( $post_id = 0, $user_id = 0, $hier = true ){
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
		return apply_filters( 'groupz_user_in_post_edit_groups', $found, $post_id, $user_id, $hier );

	// Loop over all post edit groups
	foreach ( get_post_edit_groups( $post_id, false ) as $group ) {

		// Is user in a group
		if ( in_array( $user_id, $group->users ) )
			$found = true;

		// Check child groups if not found yet
		if ( ! $found && $hier ){
			foreach ( get_term_children( $group->term_id, groupz_get_group_tax_id() ) as $group_child ){
				if ( in_array( $user_id, get_group_users( $group_child ) ) ) {
					$found = true; 
					break;
				}
			}
		}

		if ( $found )
			break;
	}

	return apply_filters( 'groupz_user_in_post_edit_groups', $found, $post_id, $user_id, $hier );
}

