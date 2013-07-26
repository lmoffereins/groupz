<?php

/**
 * Groupz Core Functionality
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Groupz_Core' ) ) :

/**
 * Plugin class
 */
class Groupz_Core {

	public function __construct(){
		$this->setup_globals();
		$this->setup_actions();
	}

	private function setup_actions(){

		// Return Group
		add_filter( 'get_terms',           array( $this, 'get_terms'        ), 10, 3 );
		add_filter( 'get_'. $this->tax,    array( $this, 'get_term'         ), 10, 2 );
		add_filter( 'wp_get_object_terms', array( $this, 'get_object_terms' ), 10, 4 );

		// Handle Group
		add_action( 'created_'. $this->tax, array( $this, 'update_term' ), 10, 2 );
		add_action( 'edited_'. $this->tax,  array( $this, 'update_term' ), 10, 2 );
		add_action( 'delete_term_taxonomy', array( $this, 'delete_term' )        );

		// Uninstall
		add_action( 'groupz_uninstall', array( $this, 'remove_all_meta' ) );

		// Post Groups
		add_action( 'added_term_relationship',    array( $this, 'added_post_group'    ), 10, 2 );
		add_action( 'deleted_term_relationships', array( $this, 'removed_post_groups' ), 10, 2 );

		// Filters
		add_filter( 'get_terms_args', array( $this, 'force_groups_as_objects' ),  1, 2 );
		add_filter( 'get_terms',      array( $this, 'filter_user_groups'      ), 70, 3 );
		add_filter( 'get_terms',      array( $this, 'filter_group_properties' ), 80, 3 );
		add_filter( 'get_terms',      array( $this, 'unset_groups_as_objects' ), 90, 3 );
	}

	private function setup_globals(){
		$this->tax = groupz_get_group_tax_id();
	}

	/** Return Group *************************************************/

	/**
	 * Return the requested group terms with added properties
	 *
	 * Send the terms that are groups through self::setup_group().
	 *
	 * @uses self::setup_group() To add the group properties
	 * 
	 * @param array $terms The requested terms
	 * @param array $taxonomies The requested taxonomy or taxonomies
	 * @param array $args The request arguments
	 * @return array The terms
	 */
	public function get_terms( $terms, $taxonomies, $args ){

		// Bail if not a group
		if ( !in_array( $this->tax, (array) $taxonomies ) )
			return $terms;

		// Setup groups
		foreach ( $terms as $k => $term ){
			if ( isset( $term->taxonomy ) // Is term taxonomy object
				&& $this->tax == $term->taxonomy // Has required taxonomy property
				&& in_array( $args['fields'], array( 'all', 'all_with_object_id' ) ) // If all fields requested
				)
				$terms[$k] = $this->setup_group( $term );
		}

		return $terms;
	}

	/**
	 * Return a single group term as group object
	 *
	 * Send group through self::setup_group().
	 *
	 * @uses self::setup_group()
	 * 
	 * @param object $term The group
	 * @param string $taxonomy The taxonomy name
	 * @return object Group
	 */
	public function get_term( $term, $taxonomy ){
		return $this->setup_group( $term );
	}

	/**
	 * Return group objects for wp_get_object_terms()
	 *
	 * Runs through all terms to see if they are a group 
	 * and if so, send them through self::setup_group().
	 *
	 * The $taxonomies argument is passed through as an array 
	 * of quoted taxonomy names from wp_get_object_terms().
	 * Therefor it first needs to be decomposed.
	 *
	 * @uses self::get_terms() To setup groups
	 * 
	 * @param array $terms The found terms
	 * @param array $object_ids The requested object IDs
	 * @param string $taxonomies The requested taxonomies
	 * @param array $args The query args
	 * @return array The terms
	 */
	public function get_object_terms( $terms, $object_ids, $taxonomies, $args ){

		// Decompose quoted taxonomies string var to force array
		if ( !is_array( $taxonomies ) )
			$taxonomies = explode( "','", substr( $taxonomies, 1, -1 ) );

		return $this->get_terms( $terms, (array) $taxonomies, $args );
	}

	/**
	 * Return a group term object with added properties
	 *
	 * @uses call_user_func_array() To call the get callback for the properties value
	 * 
	 * @param object $group The group term to setup
	 * @return object $group
	 */
	public function setup_group( $group ){

		// Bail if not an object
		if ( !is_object( $group ) )
			return $group;

		// Add properties
		foreach ( $this->group_params() as $param => $args ){	
			if ( !isset( $group->$param ) && isset( $args['get_callback'] ) )
				$group->$param = call_user_func_array( $args['get_callback'], array( $group->term_id ) );
		}

		return $group;
	}

	/**
	 * Return the group parameters with respective values
	 *
	 * @uses apply_filters() To call 'groupz_group_params' filter
	 * @return array $params
	 */
	public function group_params(){
		return apply_filters( 'groupz_group_params', array(

			// Users
			'users'     => array(
				'label'           => __('Users', 'groupz'),
				'description'     => __('The users that are bound to this group.', 'groupz'),
				'field_callback'  => array( $this, 'field_users' ),
				'get_callback'    => array( $this, 'get_users' ),
				'update_callback' => array( $this, 'update_users' )
				),

			// Edit group
			'is_edit'   => array(
				'label'           => __('Edit group', 'groupz'),
				'description'     => __('Whether this group can be selected for edit privilege.', 'groupz'),
				'field_callback'  => array( $this, 'field_is_edit' ),
				'get_callback'    => array( $this, 'get_is_edit' ),
				'update_callback' => array( $this, 'update_is_edit' )
				),

			// Visibility
			'invisible'   => array(
				'label'           => __('Invisible', 'groupz'),
				'description'     => __('Whether this group is hidden for uncapable users.', 'groupz'),
				'field_callback'  => array( $this, 'field_invisible' ),
				'get_callback'    => array( $this, 'get_invisible' ),
				'update_callback' => array( $this, 'update_invisible' ),
				'inverse'         => true // Parameter works other way round
				)

			) );
	}

	/** Handle Group *************************************************/

	/**
	 * Call 'groupz_create_group' on creation of new group
	 *
	 * @param int $group_id The term ID
	 * @param int $term_taxonomy_id The term_taxonomy ID
	 * @return void
	 */
	public function create_term( $group_id, $term_taxonomy_id ){
		do_action( 'groupz_create_group', $group_id );
	}

	/**
	 * Save group properties on updating its term
	 *
	 * @uses Groupz_Core::group_params() To get the group properties
	 * @uses call_user_func_array() To call the update callback for the property
	 * 
	 * @param int $group_id The term ID
	 * @param int $term_taxonomy_id The term_taxonomy ID
	 * @return void
	 */
	public function update_term( $group_id, $term_taxonomy_id ){

		// Loop over all params
		foreach ( $this->group_params() as $param => $args ){

			// Verify requirements and security
			if ( isset( $_POST["groupz_$param"] ) 
				&& isset( $args['update_callback'] ) 
				&& isset( $_POST["groupz_{$param}_nonce"] )
				&& wp_verify_nonce( $_POST["groupz_{$param}_nonce"], "groupz_$param" )
				){

				// Run update function
				call_user_func_array( $args['update_callback'], array( $group_id, $_POST["groupz_$param"] ) );
			}
		}

		// Hook created or update action
		if ( 'created_'. $this->tax == current_filter() )
			do_action( 'groupz_create_group', $group_id );
		else
			do_action( 'groupz_update_group', $group_id );
	}

	/**
	 * Delete the group properties on deleting its term
	 *
	 * @uses do_action() To call 'groupz_delete_group' action
	 * @uses Groupz_Core::group_params() To get the group properties
	 * @uses Groupz_Core::delete_meta() To delete all stored group meta
	 * @uses Groupz_Core::remove_edit_group_from_posts() 
	 * 
	 * @param object $group The deleted group
	 * @param int $group_id The group ID
	 * @return void
	 */
	public function delete_term( $term_id ){

		// Bail if not a group
		if ( !groupz_is_group( $term_id ) )
			return;

		// Hook
		do_action( 'groupz_delete_group', $term_id );

		// Delete all meta
		foreach ( $this->group_params() as $param => $args ){
			$this->delete_meta( $term_id, $param );
		}

		// Delete edit_groups associations
		if ( groupz_is_edit_group( $term_id ) )
			$this->remove_edit_group_from_posts( $term_id );
	}

	/** Group Meta ***************************************************/

	/**
	 * As long as there doesn't exist a Term Meta API in WP,
	 * we use a custom workaround within the wpdb->options table.
	 */

	public function get_meta( $group_id, $key, $default = false ){
		return get_option( groupz()->pre_meta . $group_id . '-' . $key, $default );
	}

	public function update_meta( $group_id, $key, $value ){
		return update_option( groupz()->pre_meta . $group_id . '-' . $key, $value );
	}

	public function delete_meta( $group_id, $key ){
		return delete_option( groupz()->pre_meta . $group_id . '-' . $key );
	}

	public function remove_all_meta(){
		foreach ( get_groups() as $group ){
			foreach ( $this->group_params() as $param => $cb ){
				$this->delete_meta( $group->term_id, $param );
			}
		}
	}

	/** Group Users **************************************************/

	public function get_users( $group_id ){
		return array_map( 'intval', $this->get_meta( $group_id, 'users', array() ) );
	}

	public function update_users( $group_id, $users ){
		return $this->update_meta( $group_id, 'users', array_map( 'intval', (array) $users ) );
	}

	public function add_users( $group_id, $user_id_or_ids ){
		do_action( 'groupz_add_users', $group_id, (array) $user_id_or_ids );

		return $this->update_users( $group_id, array_unique( array_merge( $this->get_users( $group_id ), (array) $user_id_or_ids ) ) );
	}

	public function remove_users( $group_id, $user_id_or_ids ){
		do_action( 'groupz_remove_users', $group_id, (array) $user_id_or_ids );

		return $this->update_users( $group_id, array_unique( array_diff( $this->get_users( $group_id ), (array) $user_id_or_ids ) ) );
	}

	public function field_users( $group_id ){
		$args = array(
			'id' => 'groupz_users', 'name' => 'groupz_users[]',
			'selected' => $this->get_users( $group_id ),
			'multiple' => 1, 'class' => 'chzn-select select_group_users',
			'style' => sprintf( ' data-placeholder="%s"', __('Select a user', 'groupz') ), // !
			'width' => '95%', // !
			'disabled' => !current_user_can( 'manage_group_users' )
			);

		// ! Can do better
		if ( isset( $args['width'] ) )
			$args['style'] .= sprintf( ' style="width:%s;"', is_int( $args['width'] ) ? (string) $args['width'] .'px' : $args['width'] );

		groupz_dropdown_users( $args );
	}

	/** Group Is Edit ************************************************/

	public function get_is_edit( $group_id ){
		return (bool) $this->get_meta( $group_id, 'is_edit' );
	}

	public function update_is_edit( $group_id, $is_edit ){
		if ( $this->get_is_edit( $group_id ) == $is_edit )
			return;

		do_action( 'groupz_update_is_edit', $group_id, $is_edit );

		return $this->update_meta( $group_id, 'is_edit', (bool) $is_edit );
	}

	public function field_is_edit( $group_id ){
		?>
			<input name="groupz_is_edit" type="checkbox" id="groupz_is_edit" value="1" <?php checked( $this->get_is_edit( $group_id ) ); ?>/>
		<?php
	}

	/** Group Visible ************************************************/

	public function get_invisible( $group_id ){
		return (bool) $this->get_meta( $group_id, 'invisible' );
	}

	public function update_invisible( $group_id, $invisible ){
		if ( $this->get_invisible( $group_id ) == $invisible )
			return;

		do_action( 'groupz_update_invisible', $group_id, $invisible );

		return $this->update_meta( $group_id, 'invisible', (bool) $invisible );
	}

	public function field_invisible( $group_id ){
		?>
			<input name="groupz_invisible" type="checkbox" id="groupz_invisible" value="1" <?php checked( $this->get_invisible( $group_id ) ); ?>/>
		<?php
	}

	/** Post Groups **************************************************/

	/**
	 * Return all groups for the given item
	 *
	 * @uses get_the_terms() To get the associated groups
	 * 
	 * @param int $post_id The requested post ID
	 * @param bool $ids Whether to return group ids or terms
	 * @return array $groups
	 */
	public function get_post_groups( $post_id, $ids = false ){

		// Get the post groups as terms
		$groups = wp_get_post_terms( 
			(int) $post_id, 
			$this->tax,
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
	 * @uses wp_set_post_terms() To update the post groups
	 * 
	 * @param int $post_id The post ID
	 * @param array $groups The new associated group ids
	 */
	public function update_post_groups( $post_id, $groups ){

		// Sanitize group ids
		$group_ids = array_map( 'intval', $groups );

		// Hook before
		do_action( 'groupz_update_post_groups', $post_id, $group_ids );

		// Update
		wp_set_post_terms( $post_id, $group_ids, $this->tax );

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
	 * @param int $post_id The post ID
	 * @return int $post_id
	 */
	public function remove_post_groups( $post_id ){
		return $post_id;
	}

	/**
	 * Return posts that have the given group assigned
	 * 
	 * @param int|array $group_id The group ID
	 * @param bool $ids Whether to return ids or terms
	 * @return array $posts
	 */
	public function get_group_posts( $group_id, $ids = false ){

		// Setup query args
		$args = array(
			'post_type' => 'any',
			'fields'    => $ids ? 'ids' : 'all',
			'tax_query' => array(
				array(
					'taxonomy' => $this->tax,
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
	 * @uses groupz_is_group() To check if we're handling a group
	 * @uses do_action() To call 'groupz_added_post_group'
	 * 
	 * @param int $post_id Post ID
	 * @param int $term_id Term ID
	 * @return void
	 */
	public function added_post_group( $post_id, $term_id ){

		// Bail for revisions
		if ( wp_is_post_revision( $post_id ) ) return;

		if ( groupz_is_group( (int) $term_id ) )
			do_action( 'groupz_added_post_group', $post_id, (int) $term_id );
	}

	/**
	 * Create a hook for a removed post group relationship
	 *
	 * @uses groupz_is_group() To check if we're handling a group
	 * @uses do_action() To call 'groupz_removed_post_groups'
	 * 
	 * @param int $post_id Post ID
	 * @param int $removed_terms Term ids
	 */
	public function removed_post_groups( $post_id, $removed_terms ){

		// Bail for revisions
		if ( wp_is_post_revision( $post_id ) ) return;

		// Setup remove groups
		$remove_groups = array();

		// Loop removed terms
		foreach ( $removed_terms as $term_id ){

			if ( groupz_is_group( (int) $term_id ) )
				$remove_groups[] = (int) $term_id;
		}

		// Act if removed terms are groups
		if ( ! empty( $remove_groups ) )
			do_action( 'groupz_removed_post_groups', $post_id, $remove_groups );
	}

	/** Post Edit Groups *********************************************/

	/**
	 * Return all edit groups for the given post
	 *
	 * NOTE: edit groups are not stored as terms of the
	 * post, but as post meta. Therefor we're not using 
	 * get_the_terms() here, but instead fetch the
	 * stored edit groups post meta.
	 * 
	 * @param int $post_id The requested post ID
	 * @param bool $ids Whether to return ids or terms
	 * @return array $groups Objects or IDs
	 */
	public function get_post_edit_groups( $post_id, $ids = false ){

		// Get stored post edit groups
		$group_ids = (array) get_post_meta( $post_id, groupz()->pre_meta .'edit_groups', true );

		// Return terms
		if ( !$ids ){

			// One SQL call for all groups instead of mutiple calls with get_term_by()
			$groups = get_edit_groups(); 

			// Remove not found groups from return var
			foreach ( $groups as $k => $group ){
				if ( !in_array( $group->term_id, $group_ids ) )
					unset( $groups[$k] );
			}
		} 

		// Return ids
		else { 
			$groups = $group_ids;
		}

		return $groups;
	}

	/**
	 * Update the post edit groups relationships
	 *
	 * @uses update_post_meta() To store the new post edit groups
	 * @uses do_action() To call 'groupz_update_post_edit_groups' and
	 *                    'groupz_updated_edit_groups'
	 * 
	 * @param int $post_id The post ID
	 * @param array $groups The groups to store, array of ids
	 * @return void
	 */
	public function update_post_edit_groups( $post_id, $groups ){

		// Sanitize post ID
		$post_id = (int) $post_id;

		// Sanitize group ids
		$group_ids = array_map( 'intval', $groups );

		// Hook before
		do_action( 'groupz_update_post_edit_groups', $post_id, $group_ids );

		// Groups to keep
		$keep_groups = array();

		// Hook for previous user groups
		foreach ( get_user_groups( $user_id ) as $group_id ){

			/**
			 * NB: This does not apply to posts being saved with 
			 * wp_insert_post() or aliases of it. Existing posts will
			 * be stored as a revision whilst the udpated post becomes 
			 * a 'new' post without previous assigned groups. So there
			 * is nothing to remove.
			 */
			if ( !in_array( $group_id, $group_ids ) ){
				do_action( 'groupz_remove_post_edit_group', $post_id, $group_id );
			}

			// Note if group is to keep
			else {
				$keep_groups[] = $group_id;
			}
		}

		// Hook for new groups
		foreach ( $group_ids as $group_id ){
			if ( !in_array( $group_id, $keep_groups ) ){
				do_action( 'groupz_add_post_edit_group', $post_id, $group_id );
			}
		}

		// Update
		update_post_meta( $post_id, groupz()->pre_meta .'edit_groups', $group_ids );

		// Hook after
		do_action( 'groupz_updated_post_edit_groups', $post_id, $group_ids );
	}

	/**
	 * Remove all post edit groups relationships
	 *
	 * @uses delete_post_meta() To delete the post edit groups data
	 * @uses do_action() To call 'groupz_remove_post_edit_groups'
	 * 
	 * @param int $post_id The post ID
	 * @return bool Success or fail of deleting post meta
	 */
	public function remove_post_edit_groups( $post_id ){
		
		// Hook before
		do_action( 'groupz_remove_post_edit_groups', $post_id );

		// Delete
		return delete_post_meta( $post_id, groupz()->pre_meta .'edit_groups' );
	}

	/**
	 * Return requested posts having edit groups meta stored
	 *
	 * Defaults 'fields' to 'ids'. Set the argument to 'all'
	 * to return post objects.
	 *
	 * @param array $args Optional. Query arguments
	 * @return array $posts
	 */
	public function get_posts_with_edit_groups( $args = array() ){
		
		// Setup query args
		$defaults = array(
			'post_type' => groupz()->get_edit_post_types(),
			'fields'    => 'ids',
			'meta_key'  => groupz()->pre_meta .'edit_groups' // Make sure post has at least one edit group
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
	 * @param int $group_id The edit group ID
	 * @param array $query_args Optional WP_Query arguments
	 * @return array $posts
	 */
	public function get_edit_group_posts( $group_id, $query_args = array() ){

		// The posts with edit groups
		$posts = $this->get_posts_with_edit_groups( $query_args );

		// Loop over all posts
		foreach ( $posts as $k => $post ){
			$ids = $this->get_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, true );

			// Remove post from array if edit groups do not contain group ID
			if ( !in_array( $group_id, $ids ) )
				unset( $posts[$k] );
		}

		return $posts;
	}

	/**
	 * Remove a group from all posts edit groups
	 *
	 * This will be run when a group is deleted and its reference 
	 * to all posts as edit group need to be removed.
	 *
	 * @uses Groups_Core::get_posts_with_edit_groups()
	 * @uses Groups_Core::get_post_edit_groups()
	 * @uses Groups_Core::update_post_edit_groups()
	 * 
	 * @param int $group_id The group ID to remove
	 */
	public function remove_edit_group_from_posts( $group_id ){

		// The posts with edit groups meta key
		$posts = $this->get_posts_with_edit_groups();

		// Loop over all posts
		foreach ( $posts as $post ){
			$ids = $this->get_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, true );

			// Update post edit groups with group ID removed
			if ( in_array( $group_id, $ids ) )
				$this->update_post_edit_groups( is_object( $post ) ? $post->ID : (int) $post, array_diff( $ids, array( $group_id ) ) );
		}
	}

	/** User Groups **************************************************/

	/**
	 * Return the groups of the given user
	 * 
	 * @param int $user_id User ID. Defaults to current user
	 * @param array $ids Optional query arguments
	 * @param boolean $include_ancestors Whether to insert groups from the users group ancestor tree
	 * @return array $groups
	 */
	public function get_user_groups( $user_id, $args = array(), $include_ancestors = false ){
		$defaults = array(
			'user_id' => (int) $user_id
			);

		// Get the groups
		$user_groups = get_groups( wp_parse_args( $args, $defaults ) );

		// Setup return var
		$groups = array();

		// When ancestors are requested
		if ( $include_ancestors ){
			foreach ( $user_groups as $group_id ){
				$ancestors = get_ancestors( is_object( $group_id ) ? $group_id->term_id : $group_id, $this->tax );

				// Return terms if requested
				if ( is_object( $group_id ) && !empty( $ancestors ) ){
					foreach ( $ancestors as $k => $anc_id ){
						$ancestors[$k] = get_group( $anc_id );
					}
				}

				$groups = array_merge( $groups, $ancestors );
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
	public function update_user_groups( $user_id, $groups ){

		// Sanitize user ID
		$user_id = (int) $user_id;

		// Sanitize group ids
		$group_ids = array_map( 'intval', $groups );

		// Hook before
		do_action( 'groupz_update_user_groups', $user_id, $group_ids );

		// Groups to keep
		$keep_groups = array();

		// Remove previous user groups
		foreach ( get_user_groups( $user_id ) as $group_id ){

			if ( !in_array( $group_id, $group_ids ) ){
				$this->remove_users( $group_id, $user_id );
			}

			// Note if group is to keep
			else {
				$keep_groups[] = $group_id;
			}
		}

		// Update new groups
		foreach ( $group_ids as $group_id ){
			if ( !in_array( $group_id, $keep_groups ) ){
				$this->add_users( $group_id, $user_id );
			}
		}

		// Hook after
		do_action( 'groupz_updated_user_groups', $user_id, $group_ids );
	}

	/**
	 * Remove user from all groups
	 * 
	 * @param int $user_id User id
	 */
	public function remove_user_groups( $user_id ){

		// Sanitize user ID
		$user_id = (int) $user_id;

		// Hook before
		do_action( 'groupz_remove_user_groups', $user_id );

		// Remove user from groups
		foreach ( $this->get_user_groups( $user_id ) as $group ){
			$this->update_users( $group->term_id, array_diff( $group->users, array( $user_id ) ) );
		}
	}

	/**
	 * Return groups given user is not in
	 *
	 * @uses get_groups() 
	 * 
	 * @param int $user_id User id
	 * @param array $args Optional. Group query args
	 * @return array Groups user is not in
	 */
	public function get_not_user_groups( $user_id, $args ){
		$defaults = array(
			'not_user_id' => (int) $user_id
			);

		return get_groups( wp_parse_args( $args, $defaults ) );
	}

	/** Filters ******************************************************/

	/**
	 * Force object retrieval when requesting groups
	 * 
	 * @param array $args Arguments for get_terms()
	 * @param array $taxonomies Requested taxonomies
	 * @return array $args
	 */
	public function force_groups_as_objects( $args, $taxonomies ){

		// Require group taxonomy
		if ( in_array( $this->tax, $taxonomies ) && 'all' != $args['fields'] ){
			$args['return_type'] = $args['fields'];

			// Force process objects
			$args['fields'] = 'all';
		}

		return $args;
	}

	/**
	 * Filter get_groups() for given user ID
	 *
	 * If $args['user_id'] is set returns only
	 * groups containing given user ID.
	 * If $args['not_user_id'] is set returns only
	 * groups NOT containing given user ID.
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function filter_user_groups( $terms, $taxonomies, $args ){

		// Require group taxonomy
		if ( !in_array( $this->tax, $taxonomies ) )
			return $terms;

		//  Require single user ID
		if ( isset( $args['user_id'] ) ){
			$user_id = (int) $args['user_id'];

			// Loop over all groups
			foreach ( $terms as $k => $term ) :

				// Require group
				if ( $term->taxonomy != $this->tax )
					continue;

				// Filter user ID
				if ( !in_array( $user_id, $term->users ) )
					unset( $terms[$k] );

			endforeach; // End groups loop
		}

		// Require single not user ID
		if ( isset( $args['not_user_id'] ) ){
			$user_id = (int) $args['not_user_id'];

			// Loop over all groups
			foreach ( $terms as $k => $term ) :

				// Require group
				if ( $term->taxonomy != $this->tax )
					continue;

				// Filter user ID
				if ( in_array( $user_id, $term->users ) )
					unset( $terms[$k] );

			endforeach; // End groups loop
		}		

		return $terms;
	}

	/**
	 * Filter get_groups() function for boolean group properties
	 *
	 * @uses apply_filters() To call 'filter_group_properties'
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function filter_group_properties( $terms, $taxonomies, $args ){

		// Require group taxonomy in query
		if ( !in_array( $this->tax, $taxonomies ) )
			return $terms;

		// Get group params
		$params = $this->group_params();

		// Loop over all groups
		foreach ( $terms as $k => $term ) :

			// Require group
			if ( $term->taxonomy != $this->tax )
				continue;

			// Filter boolean group properties
			foreach ( apply_filters( 'filter_group_properties', array( 'is_edit', 'invisible' ) ) as $filter ){

				// Whether to inverse behaviour
				$inverse = isset( $params[$filter]['inverse'] ) ? $params[$filter]['inverse'] : false;

				// Is filter present
				if ( isset( $args[$filter] ) ){
					if ( $args[$filter] ){	
						if ( ( !$term->$filter && !$inverse ) || ( $term->$filter && $inverse ) ){
							unset( $terms[$k] );
							continue 2;	// Group is unset so continue to next group
						}

					} else {
						if ( ( $term->$filter && !$inverse ) || ( !$term->$filter && $inverse ) ){
							unset( $terms[$k] );
							continue 2;	// Group is unset so continue to next group
						}
					}
				}
			}

		endforeach; // End groups loop

		return $terms;
	}

	/**
	 * Return groups in requested format from get_terms()
	 *
	 * Acts as the opposite of force_groups_as_objects()
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function unset_groups_as_objects( $terms, $taxonomies, $args ){

		// Require group taxonomy
		if ( !in_array( $this->tax, $taxonomies ) )
			return $terms;

		// Require return type set in force_groups_as_objects()
		if ( !isset( $args['return_type'] ) )
			return $terms;

		// Return count if requested
		if ( 'count' == $args['return_type'] )
			return count( $terms );

		// Setup return values
		$_terms = array();
		foreach ( $terms as $k => $term ){
			switch ( $args['return_type'] ){
				case 'ids' :
					$_terms[$k] = (int) $term->term_id;
					break;

				case 'id=>parent' :
					$_terms[$term->term_id] = $term->parent;
					break;

				case 'names' :
					$_terms[$k] = $term->name;
					break;
			}
		}

		return !empty( $_terms ) ? $_terms : $terms;
	}

}

/**
 * Hook core functions into Groupz
 */
function groupz_core(){
	groupz()->core = new Groupz_Core();
}

endif; // class_exists

