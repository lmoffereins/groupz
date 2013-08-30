<?php

/**
 * Groupz Extension for Simple History
 *
 * @package Groupz
 * @subpackage Extend
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Simple_History' ) ) :

/**
 * Plugin class
 */
class Groupz_Simple_History extends SH_Extend {

	public function __construct() {
		parent::__construct( array(
			'id'     => 'groupz',
			'title'  => __('Groupz', 'groupz'),
			'plugin' => 'groupz/groupz.php',
			'tabs'   => array()
		) );
	}

	public function add_events() {

		// Add custom Groupz events text
		$events = array(
			'add'              => __('added to group %s',                               'groupz'),
			'remove'           => __('removed from group %s',                           'groupz'),
			'post_add'         => __('added read privilege for group %s',               'groupz'),
			'post_remove'      => __('removed read privilege for group %s',             'groupz'),
			'groups_now'       => __('has read privilege for the following groups: %s', 'groupz'),
			'groups_none'      => __('is currently not assigned to any group',          'groupz'),
			'edit_post_add'    => __('can be edited by users in group %s',              'groupz'),
			'edit_post_remove' => __('can not be edited anymore by users in group %s',  'groupz'),
			'edit_groups_now'  => __('has edit privilege for the following groups: %s', 'groupz'),
			'edit_groups_none' => __('is currently not assigned to any edit group',     'groupz')
		);

		return apply_filters( 'groupz_simple_history_events', $events );
	}

	public function add_actions() {

		// Users
		add_action( 'groupz_add_users',                array( $this, 'add_users'                ), 10, 2 );
		add_action( 'groupz_remove_users',             array( $this, 'remove_users'             ), 10, 2 );

		// Group
		add_action( 'groupz_create_group',             array( $this, 'create_group'             )        );
		add_action( 'groupz_update_group',             array( $this, 'update_group'             )        );
		add_action( 'groupz_delete_group',             array( $this, 'delete_group'             )        );

		// Posts
		add_action( 'groupz_added_post_group',         array( $this, 'added_post_group'         ), 10, 2 );
		add_action( 'groupz_removed_post_group',       array( $this, 'removed_post_group'       ), 10, 2 );
		add_action( 'set_object_terms',                array( $this, 'set_object_terms'         ), 10, 6 );
		add_action( 'groupz_add_post_edit_group',      array( $this, 'add_post_edit_group'      ), 10, 2 );
		add_action( 'groupz_remove_post_edit_group',   array( $this, 'remove_post_edit_group'   ), 10, 2 );
		add_action( 'groupz_updated_post_edit_groups', array( $this, 'updated_post_edit_groups' ), 10, 2 );
		add_action( 'groupz_remove_post_edit_groups',  array( $this, 'remove_post_edit_groups'  )        );

		// Hook for other groupz extensions
		do_action( 'groupz_simple_history_actions' );
	}

	/** Helpers ******************************************************/

	public function extend_group( $group_id, $action ) {
		$this->extend( array( 
			'action' => $action,
			'type'   => __('Group', 'groupz'),
			'name'   => get_group_name( $group_id ),
			'id'     => $group_id
		) );
	}

	/** Users ********************************************************/

	public function add_users( $group_id, $user_id_or_ids ) {
		foreach ( $user_id_or_ids as $user_id ) {
			$this->extend_user( $user_id, sprintf( $this->events['add'], get_group_name( $group_id ) ) );
		}
	}

	public function remove_users( $group_id, $user_id_or_ids ) {
		foreach ( $user_id_or_ids as $user_id ) {
			$this->extend_user( $user_id, sprintf( $this->events['remove'], get_group_name( $group_id ) ) );
		}
	}

	/** Group ********************************************************/

	public function create_group( $group_id ) {
		$this->extend_group( $group_id, $this->events['new'] );
	}

	public function update_group( $group_id ) {
		$this->extend_group( $group_id, $this->events['edit'] );
	}

	public function delete_group( $group_id ) {
		$this->extend_group( $group_id, $this->events['delete'] );
	}

	/** Posts ********************************************************/

	/** 
	 * Saving a new or an existing post allways triggers the add functions
	 * and never the remove functions, because there's allways a new post
	 * created, while the existing post is saved as a revision. Therefor
	 * the groups get (re)assigned as new post groups.
	 */
	public function added_post_group( $post_id, $group_id ) {
		$this->extend_post( $post_id, sprintf( $this->events['add'], get_group_name( $group_id ) ) );
	}

	public function removed_post_group( $post_id, $group_id ) {
		$this->extend_post( $post_id, sprintf( $this->events['remove'], get_group_name( $group_id ) ) );
	}

	public function set_object_terms( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {

		// Don't log revisions
		if ( wp_is_post_revision( $object_id ) )
			return;

		// Only log groups
		if ( $taxonomy != groupz_get_group_tax_id() )
			return;

		// Post has no groups
		if ( empty( $terms ) ){
			$this->extend_post( $object_id, $this->events['groups_none'] );
		}

		// Post has groups
		else {
			$groups = array();

			// Gather group names
			foreach ( $terms as $term_id )
				$groups[] = get_group_name( $term_id );

			$this->extend_post( $object_id, sprintf( $this->events['groups_now'], join( ', ', $groups ) ) );
		}
	}

	public function add_post_edit_group( $post_id, $group_id ) {
		// Don't log revisions
		if ( wp_is_post_revision( $post_id ) )
			return;

		$this->extend_post( $post_id, sprintf( $this->events['edit_post_add'], get_group_name( $group_id ) ) );
	}

	public function remove_post_edit_group( $post_id, $group_id ) {
		// Don't log revisions
		if ( wp_is_post_revision( $post_id ) )
			return;

		$this->extend_post( $post_id, sprintf( $this->events['edit_post_remove'], get_group_name( $group_id ) ) );
	}

	public function updated_post_edit_groups( $post_id, $new_group_ids ) {
		// Don't log revisions
		if ( wp_is_post_revision( $post_id ) )
			return;

		$groups = array();

		// Gather group names
		foreach ( $terms as $term_id )
			$groups[] = get_group_name( $term_id );

		$this->extend_post( $post_id, sprintf( $this->events['edit_groups_now'], join( ', ', $groups ) ) );
	}

	public function remove_post_edit_groups( $post_id ) {
		$this->extend_post( $post_id, $this->events['edit_groups_none'] );
	}
}

endif; // class_exists