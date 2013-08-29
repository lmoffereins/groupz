<?php

/**
 * Groupz Functions
 *
 * @package Groupz
 * @subpackage Core
 */

/** Group Meta API ***********************************************/

/**
 * As long as there doesn't exist a valid Term Meta API in WP
 * we use a custom workaround within the wpdb->options table.
 * Group meta is stored with a 'group_meta_{group_id}-{meta_key}' 
 * meta key.
 *
 * @link http://make.wordpress.org/core/2013/07/28/potential-roadmap-for-taxonomy-meta-and-post-relationships/
 */

/**
 * Return group meta
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param string $key Meta key
 * @param boolean $default Default return value
 * @return mixed Group meta vale
 */
function get_group_meta( $group_id, $key, $default = false ) {
	return get_option( groupz()->pre_meta . $group_id . '-' . $key, $default );
}

/**
 * Update group meta
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param string $key Meta key
 * @param mixed $value Meta value
 * @return boolean Update success
 */
function update_group_meta( $group_id, $key, $value ) {
	return update_option( groupz()->pre_meta . $group_id . '-' . $key, $value );
}

/**
 * Delete group meta
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param string $key Meta key
 * @return boolean Delete success
 */
function delete_group_meta( $group_id, $key ) {
	return delete_option( groupz()->pre_meta . $group_id . '-' . $key );
}

/**
 * Delete all existing group meta
 *
 * Bulk removal of all Groupz group meta in the WP database
 *
 * @since 0.1
 *
 * @uses get_groups()
 * @uses groupz_get_group_params()
 * @uses delete_group_meta()
 */
function groupz_remove_all_meta() {
	foreach ( get_groups() as $group ) {
		foreach ( groupz()->group_params() as $param => $args ) {
			delete_group_meta( $group->term_id, $param );
		}
	}
}

/** Group Parameters *********************************************/

/**
 * Return the group parameters with respective values
 *
 * @since 0.1
 *
 * @uses apply_filters() To call 'groupz_group_params' filter
 * @return array $params
 */
function groupz_get_group_params() {
	return apply_filters( 'groupz_group_params', array(

		// Users
		'users'       => array(
			'label'           => __('Users', 'groupz'),
			'description'     => '',
			'field_callback'  => 'groupz_group_field_users',
			'get_callback'    => 'groupz_group_get_users',
			'update_callback' => 'groupz_group_update_users'
		),

		/*// Invisibility
		'invisible'   => array(
			'label'           => __('Invisible', 'groupz'),
			'description'     => __('Make this group visible for admins only.', 'groupz'),
			'field_callback'  => 'groupz_group_field_invisible',
			'get_callback'    => 'groupz_group_get_invisible',
			'update_callback' => 'groupz_group_update_invisible',
			'inverse'         => true // Param works other way round
		)*/
	) );
}

/** Group Param: Users *******************************************/

/**
 * Return users of given group
 * 
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return array Group users
 */
function groupz_group_get_users( $group_id ) {
	return array_map( 'intval', get_group_meta( $group_id, 'users', array() ) );
}

/**
 * Update group users of group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param array $users New Group users
 * @return boolean Update success
 */
function groupz_group_update_users( $group_id, $users ) {
	return update_group_meta( $group_id, 'users', array_map( 'intval', (array) $users ) );
}

/**
 * Add new group users to group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param int|array $user_id_or_ids User ID or IDs
 * @return boolean Update success
 */
function groupz_group_add_users( $group_id, $user_id_or_ids ) {
	do_action( 'groupz_add_users', $group_id, (array) $user_id_or_ids );

	return groupz_group_update_users( $group_id, array_unique( array_merge( groupz_group_get_users( $group_id ), (array) $user_id_or_ids ) ) );
}

/**
 * Remove group users from group
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param int|array $user_id_or_ids User ID or IDs
 * @return boolean Remove success
 */
function groupz_group_remove_users( $group_id, $user_id_or_ids ) {
	do_action( 'groupz_remove_users', $group_id, (array) $user_id_or_ids );

	return groupz_group_update_users( $group_id, array_unique( array_diff( groupz_group_get_users( $group_id ), (array) $user_id_or_ids ) ) );
}

/**
 * Output group users param field
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 */
function groupz_group_field_users( $group_id ) {
	$args = array(
		'id' => 'groupz_users', 'name' => 'groupz_users[]',
		'selected' => groupz_group_get_users( $group_id ),
		'multiple' => 1, 'class' => 'chzn-select select_group_users',
		'style' => sprintf( ' data-placeholder="%s"', __('Select a user', 'groupz') ), // !
		'width' => '95%', // !
		'disabled' => ! current_user_can( 'manage_group_users' )
	);

	// @todo Can do better
	if ( isset( $args['width'] ) )
		$args['style'] .= sprintf( ' style="width:%s;"', is_int( $args['width'] ) ? (string) $args['width'] .'px' : $args['width'] );

	groupz_dropdown_users( $args );
}

/** Group Param: Invisible ***************************************/

/**
 * Return group invisible param
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return boolean Group is invisible
 */
function groupz_group_get_invisible( $group_id ) {
	return (bool) get_group_meta( $group_id, 'invisible' );
}

/**
 * Update group invisible param
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @param boolean $invisible New group invisibility
 * @return boolean Update success
 */
function groupz_group_update_invisible( $group_id, $invisible ) {
	if ( groupz_group_get_invisible( $group_id ) == $invisible )
		return;

	do_action( 'groupz_update_invisible', $group_id, $invisible );

	return update_group_meta( $group_id, 'invisible', (bool) $invisible );
}

/**
 * Output group invisible param field
 *
 * @since 0.1
 * 
 * @param int $group_id Group ID
 */
function groupz_group_field_invisible( $group_id ) {
	?>
		<input name="groupz_invisible" type="checkbox" id="groupz_invisible" value="1" <?php checked( groupz_group_get_invisible( $group_id ) ); ?>/>
	<?php
}

/*****************************************************************/

/**
 * Return group taxonomy id
 *
 * @since 0.1
 *  
 * @return string Group taxonomy ID
 */
function groupz_get_group_tax_id() {
	return groupz()->group_tax_id;
}

/**
 * Return whether given ID is a group ID
 * 
 * @since 0.1
 * 
 * @param int $term_id Term ID
 * @return boolean ID is a group
 */
function groupz_is_group( $term_id ) {
	return term_exists( $term_id, groupz_get_group_tax_id() );
}

/**
 * Return requested group object
 * 
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return object Group
 */
function get_group( $group_id ) {
	return get_term( (int) $group_id, groupz_get_group_tax_id() );
}

/**
 * Return requested group name
 * 
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return string Group name
 */
function get_group_name( $group_id ) {
	return get_group( $group_id )->name;
}

/**
 * Return requested group users
 * 
 * @since 0.1
 * 
 * @param int $group_id Group ID
 * @return array Group users
 */
function get_group_users( $group_id ) {
	return get_group( $group_id )->users;
}

/**
 * Return all groups. The base function
 * 
 * @since 0.1
 * 
 * @uses get_terms() To find the groups
 * 
 * @param array $args Optional. Arguments for get_terms()
 * @return array $groups
 */
function get_groups( $args = array() ) {
	return get_terms( groupz_get_group_tax_id(), wp_parse_args( $args, array( 'hide_empty' => 0 ) ) );
}

/**
 * Return the groups of the given user
 * 
 * @since 0.1
 * 
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $ids Whether to return ids or terms
 * @param boolean $include_ancestors Whether to insert groups from the users group ancestor tree
 * @return array $groups
 */
function get_user_groups( $user_id = 0, $ids = true, $include_ancestors = false ) {
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	return groupz_get_user_groups( (int) $user_id, array( 'fields' => $ids ? 'ids' : 'all' ), $include_ancestors );	
}

/**
 * Return the groups not containing the given user
 * 
 * @since 0.1
 * 
 * @param int $user_id User ID. Defaults to current user
 * @param boolean $ids Whether to return ids or terms
 * @param boolean $exclude_ancestors Whether to withhold groups from the users group ancestor tree
 * @return array $groups
 */
function get_not_user_groups( $user_id = 0, $ids = true, $exclude_ancestors = false ) {
	if ( empty( $user_id ) )
		$user_id = get_current_user_id();

	return groupz_get_not_user_groups( (int) $user_id, array( 'fields' => $ids ? 'ids' : 'all' ), $exclude_ancestors );
}

/** List Groups **************************************************/

/**
 * Return an unordered list of user groups
 *
 * @since 0.1
 * 
 * @param int $user_id User ID to list groups for
 * @param array $args See get_groups()
 * @return string $list
 */
function list_user_groups( $user_id, $args = array() ) {
	$defaults = array(
		'ul_class' => 'list_groups user_groups',
		'echo' => 1
	);
	$args = wp_parse_args( $args, $defaults );

	// Set post ID
	$args['user_id'] = (int) $user_id;

	return list_groups( $args );
}

/** Select Groups ************************************************/

/**
 * Output input element to select groups
 * 
 * @since 0.1
 * 
 * @uses get_select_groups()
 * 
 * @param array $args Additional arguments
 */
function select_groups( $args = array() ) {
	echo get_select_groups( $args );
}

	/**
	 * Return input element to select groups
	 * 
	 * When the Fancy Select option is selected this
	 * returns a select element - else checkboxes
	 * 
	 * @since 0.1
	 * 
	 * @param array $args Additional arguments
	 * @return string Input element
	 */
	function get_select_groups( $args = array() ) {

		// Do multiple select
		if ( groupz()->admin->use_chosen ) {
			$defaults = array(
				'name'  => 'select_groups[]',
				'echo'  => false, 'multiple' => true, 'hierarchical' => true,
				'style' => sprintf( 'data-placeholder="%s"', __('Select a group', 'groupz') ), 'width' => 0, // !
				'class' => 'select_groups dropdown_groups chzn-select' // With chosen class
			);
			$args = wp_parse_args( $args, $defaults );

			// @todo Can do better
			if ( $args['width'] )
				$args['style'] .= sprintf( ' style="width:%s;"', is_int( $args['width'] ) ? (string) $args['width'] .'px' : $args['width'] );

			add_filter( 'list_groups', 'groupz_dropdown_show_parent', 10, 3 );
			$dropdown = dropdown_groups( $args );
			remove_filter( 'list_groups', 'groupz_dropdown_show_parent' );

			return $dropdown;

		// Do checkboxes
		} else {
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
 * @since 0.1
 *
 * @param string $label Group label
 * @param object $group Group
 * @param array $args Dropdown arguments
 * @return string $label
 */
function groupz_dropdown_show_parent( $label, $group, $args = array() ) {

	// Bail if setting not set
	if ( ! get_option( 'groupz_dropdown_show_parent' ) )
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
 * @since 0.1
 * 
 * @return string Url
 */
function groupz_get_admin_page_url() {
	return add_query_arg( 'taxonomy', groupz_get_group_tax_id(), admin_url( 'edit-tags.php' ) );
}

