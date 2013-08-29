<?php

/**
 * Groupz Core Group Functionality
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Core' ) ) :

/**
 * The Groupz Core Class
 *
 * Hooks in all existing term actions and filters to ensure
 * groups are allways saved and returned with all details on 
 * board when requested.
 * 
 * @since 0.1
 */
class Groupz_Core {

	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Define default class globals
	 *
	 * @since 0.1
	 */
	private function setup_globals() {
		$this->tax = groupz_get_group_tax_id();
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.1
	 */
	private function setup_actions() {

		// Return Group
		add_filter( 'get_terms',             array( $this, 'get_terms'               ), 10, 3 );
		add_filter( 'get_' . $this->tax,     array( $this, 'get_term'                ), 10, 2 );
		add_filter( 'wp_get_object_terms',   array( $this, 'get_object_terms'        ), 10, 4 );

		// Handle Group
		add_action( 'created_' . $this->tax, array( $this, 'update_term'             ), 10, 2 );
		add_action( 'edited_'  . $this->tax, array( $this, 'update_term'             ), 10, 2 );
		add_action( 'delete_term_taxonomy',  array( $this, 'delete_term'             )        );

		// Filters
		add_filter( 'get_terms_args',        array( $this, 'force_groups_as_objects' ),  0, 2 );
		add_filter( 'get_terms',             array( $this, 'filter_user_groups'      ), 70, 3 );
		add_filter( 'get_terms',             array( $this, 'filter_group_properties' ), 80, 3 );
		add_filter( 'get_terms',             array( $this, 'unset_groups_as_objects' ), 90, 3 );
	}

	/** Return Group *************************************************/

	/**
	 * Return the requested group terms with added properties
	 *
	 * Send the terms that are groups through {@link Groupz_Core::setup_group()}.
	 *
	 * @since 0.1
	 *
	 * @uses self::setup_group() To add the group properties
	 * 
	 * @param array $terms The requested terms
	 * @param array $taxonomies The requested taxonomy or taxonomies
	 * @param array $args The request arguments
	 * @return array The terms
	 */
	public function get_terms( $terms, $taxonomies, $args ) {

		// Bail if not a group
		if ( ! in_array( $this->tax, (array) $taxonomies ) )
			return $terms;

		// Setup groups
		foreach ( $terms as $k => $term ) {
			if (   isset( $term->taxonomy ) // Is term taxonomy object
				&& $this->tax == $term->taxonomy // Has required taxonomy property
				&& in_array( $args['fields'], array( 'all', 'all_with_object_id' ) ) // If all fields requested
			) {
				$terms[$k] = $this->setup_group( $term );
			}
		}

		return $terms;
	}

	/**
	 * Return a single group term as group object
	 *
	 * Send group through {@link Groupz_Core::setup_group()}.
	 *
	 * @since 0.1
	 *
	 * @uses Groupz_Core::setup_group()
	 * 
	 * @param object $term The group
	 * @param string $taxonomy The taxonomy name
	 * @return object Group
	 */
	public function get_term( $term, $taxonomy ) {
		return $this->setup_group( $term );
	}

	/**
	 * Return group objects for wp_get_object_terms()
	 *
	 * Runs through all terms to see if they are a group and if so, 
	 * send them through {@link Groupz_Core::setup_group()}.
	 *
	 * The $taxonomies argument is passed through as an array 
	 * of quoted taxonomy names from wp_get_object_terms().
	 * Therefor it first needs to be decomposed.
	 *
	 * @since 0.1
	 *
	 * @uses Groupz_Core::get_terms() To setup groups
	 * 
	 * @param array $terms The found terms
	 * @param array $object_ids The requested object IDs
	 * @param string $taxonomies The requested taxonomies
	 * @param array $args The query args
	 * @return array The terms
	 */
	public function get_object_terms( $terms, $object_ids, $taxonomies, $args ) {

		// Decompose quoted taxonomies string var to force array
		if ( ! is_array( $taxonomies ) )
			$taxonomies = explode( "','", substr( $taxonomies, 1, -1 ) );

		return $this->get_terms( $terms, (array) $taxonomies, $args );
	}

	/**
	 * Return a group term object with added properties
	 *
	 * @since 0.1
	 *
	 * @uses call_user_func_array() To call the get callback for the properties value
	 * 
	 * @param object $group The group term to setup
	 * @return object $group
	 */
	public function setup_group( $group ) {

		// Bail if not an object
		if ( ! is_object( $group ) )
			return $group;

		// Add properties
		foreach ( groupz_get_group_params() as $param => $args ) {	
			if ( ! isset( $group->$param ) && isset( $args['get_callback'] ) && is_callable( $args['get_callback'] ) )
				$group->$param = call_user_func_array( $args['get_callback'], array( $group->term_id ) );
		}

		return $group;
	}

	/** Handle Group *************************************************/

	/**
	 * Call 'groupz_create_group' on creation of new group
	 *
	 * @since 0.1
	 *
	 * @param int $group_id The term ID
	 * @param int $term_taxonomy_id The term_taxonomy ID
	 */
	public function create_term( $group_id, $term_taxonomy_id ) {
		do_action( 'groupz_create_group', $group_id );
	}

	/**
	 * Save group properties on updating its term
	 *
	 * @since 0.1
	 *
	 * @uses groupz_get_group_params() To get the group properties
	 * @uses call_user_func_array() To call the update callback for the property
	 * 
	 * @param int $group_id The term ID
	 * @param int $term_taxonomy_id The term_taxonomy ID
	 */
	public function update_term( $group_id, $term_taxonomy_id  ) {

		// Loop over all params
		foreach ( groupz_get_group_params() as $param => $args ) {

			// Verify requirements and security
			if (   isset( $_POST["groupz_$param"] ) 
				&& isset( $args['update_callback'] ) 
				&& isset( $_POST["groupz_{$param}_nonce"] )
				&& wp_verify_nonce( $_POST["groupz_{$param}_nonce"], "groupz_$param" )
			) {

				// Run update function
				call_user_func_array( $args['update_callback'], array( $group_id, $_POST["groupz_$param"] ) );
			}
		}

		// Hook created or update action
		if ( 'created_' . $this->tax == current_filter() )
			do_action( 'groupz_created_group', $group_id );
		else
			do_action( 'groupz_updated_group', $group_id );
	}

	/**
	 * Delete the group properties on deleting its term
	 *
	 * @since 0.1
	 *
	 * @uses do_action() To call 'groupz_delete_group' action
	 * @uses groupz_get_group_params() To get the group properties
	 * @uses delete_group_meta() To delete all stored group meta
	 * 
	 * @param object $group The deleted group
	 * @param int $group_id Group ID
	 */
	public function delete_term( $term_id ) {

		// Bail if not a group
		if ( ! groupz_is_group( $term_id ) )
			return;

		do_action( 'groupz_delete_group', $term_id );

		// Delete all meta
		foreach ( groupz_get_group_params() as $param => $args ) {
			delete_group_meta( $term_id, $param );
		}
	}

	/** Filters ******************************************************/

	/**
	 * Force object retrieval when requesting groups
	 *
	 * @since 0.1
	 * 
	 * @param array $args Arguments for get_terms()
	 * @param array $taxonomies Requested taxonomies
	 * @return array $args
	 */
	public function force_groups_as_objects( $args, $taxonomies ) {

		// Require group taxonomy
		if ( in_array( $this->tax, $taxonomies ) && 'all' != $args['fields'] ){
			$args['return_type'] = $args['fields'];

			// Force WP to process groups as objects
			$args['fields'] = 'all';
		}

		return $args;
	}

	/**
	 * Filter get_groups() for given user ID
	 *
	 * If $args['user_id'] is set returns only groups containing 
	 * given user ID.
	 * If $args['not_user_id'] is set returns only groups NOT 
	 * containing given user ID.
	 *
	 * @since 0.1
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function filter_user_groups( $terms, $taxonomies, $args ) {

		// Require group taxonomy
		if ( ! in_array( $this->tax, $taxonomies ) )
			return $terms;

		// Require single user ID
		if ( isset( $args['user_id'] ) ) {
			$user_id = (int) $args['user_id'];

			// Loop over all groups
			foreach ( $terms as $k => $term ) :

				// Require group
				if ( $term->taxonomy != $this->tax )
					continue;

				// Filter user ID
				if ( ! in_array( $user_id, $term->users ) )
					unset( $terms[$k] );

			endforeach; // End groups loop
		}

		// Require single not user ID
		if ( isset( $args['not_user_id'] ) ) {
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
	 * @since 0.1
	 *
	 * @uses apply_filters() Calls 'groupz_filter_group_properties'
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function filter_group_properties( $terms, $taxonomies, $args ) {

		// Require group taxonomy in query
		if ( ! in_array( $this->tax, $taxonomies ) )
			return $terms;

		// Get group params
		$params = groupz_get_group_params();

		// Loop over all groups
		foreach ( $terms as $k => $term ) :

			// Require group
			if ( $term->taxonomy != $this->tax )
				continue;

			// Filter boolean group properties
			foreach ( apply_filters( 'groupz_filter_group_properties', array( 'invisible' ) ) as $filter ) {

				// Whether to inverse behaviour
				$inverse = isset( $params[$filter]['inverse'] ) ? $params[$filter]['inverse'] : false;

				// Is filter present
				if ( isset( $args[$filter] ) ) {
					if ( $args[$filter] ){	
						if ( ( !$term->$filter && !$inverse ) || ( $term->$filter && $inverse ) ) {
							unset( $terms[$k] );
							continue 2;	// Group is unset so continue to next term
						}

					} else {
						if ( ( $term->$filter && !$inverse ) || ( !$term->$filter && $inverse ) ) {
							unset( $terms[$k] );
							continue 2;	// Group is unset so continue to next term
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
	 * @since 0.1
	 * 
	 * @param array $terms Found terms
	 * @param array $taxonomies The terms taxonomies
	 * @param array $args Arguments for get_terms()
	 * @return array $terms
	 */
	public function unset_groups_as_objects( $terms, $taxonomies, $args ) {

		// Require group taxonomy
		if ( ! in_array( $this->tax, $taxonomies ) )
			return $terms;

		// Require return type set in force_groups_as_objects()
		if ( ! isset( $args['return_type'] ) )
			return $terms;

		// Return count if requested
		if ( 'count' == $args['return_type'] )
			return count( $terms );

		// Setup return values
		$_terms = array();
		foreach ( $terms as $k => $term ) {
			switch ( $args['return_type'] ) {
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

		return ! empty( $_terms ) ? $_terms : $terms;
	}

}

endif; // class_exists

/**
 * Hook core functions into Groupz
 *
 * @since 0.1
 *
 * @uses Groupz_Core
 */
function groupz_core() {
	groupz()->core = new Groupz_Core();
}

