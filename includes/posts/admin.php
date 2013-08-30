<?php

/**
 * Groupz Posts Admin
 *
 * @package Groupz
 * @subpackage Administration
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Posts_Admin' ) ) :

/**
 * Groupz Posts Admin Class
 *
 * @since 0.x
 *
 * @todo Privatize post after last group deletion
 */
class Groupz_Posts_Admin {

	public function __construct() {
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Declare default class globals
	 *
	 * @since 0.x
	 */
	private function setup_globals() {
		$this->tax       = groupz_get_group_tax_id();
		$this->posts_dir = trailingslashit( groupz()->includes_dir . 'posts' );
		$this->posts_url = trailingslashit( groupz()->includes_url . 'posts' );
	}

	/**
	 * Include required files
	 *
	 * @since 0.x
	 */
	private function includes() {
		require( $this->posts_dir . 'settings.php' );
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.x
	 */
	private function setup_actions() {

		// Main
		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );

		// List Tables
		add_action( 'restrict_manage_posts', array( $this, 'post_groups_dropdown' ) );
		add_action( 'restrict_manage_posts', array( $this, 'edit_groups_dropdown' ) );

		foreach ( groupz_get_read_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns",       array( $this, 'post_read_table_column' ),     11    );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'posts_table_column_content' ), 10, 2 );
		}

		foreach ( groupz_get_edit_post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns",       array( $this, 'post_edit_table_column' ),     11    );
			add_action( "manage_{$post_type}_posts_custom_column", array( $this, 'posts_table_column_content' ), 10, 2 );
		}

		add_action( 'pre_get_posts', array( $this, 'post_groups_list_table_query' ) );
		add_action( 'pre_get_posts', array( $this, 'edit_groups_list_table_query' ) );

		// add_filter( 'manage_media_columns', array( $this, 'group_table_column' ) ); // Attachments
		// add_action( 'manage_media_custom_column', array( $this, 'groups_table_column_content' ), 10, 2 );

		// Meta Boxes
		add_action( 'add_meta_boxes',      array( $this, 'add_meta_boxes'          ), 10, 2 );
		add_action( 'save_post',           array( $this, 'save_meta_boxes'         ), 10, 2 );

		add_action( 'groupz_delete_group', array( $this, 'remove_post_edit_groups' )        );

		// Group Admin
		add_action( "after-{$this->tax}-table", array( $this, 'after_table_info' ) );
	}

	/** Main *********************************************************/

	/**
	 * Output admin wide custom styles
	 *
	 * @since 0.1
	 */
	public function admin_styles() {

		// Bail if not on required page
		if ( 'edit' != get_current_screen()->base || ! groupz_admin_page_with_groups() ) : ?>

	<style type="text/css">
		/* Visual hierarchy */
		.list_groups .children {
			padding: 6px 0 0 14px;
		}

		/* List Table */
		.fixed .column-groups,
		.fixed .column-edit_groups {
			width: 10%;
		}
	</style>

		<?php endif;

		// Bail if not on required page
		if (   isset( get_current_screen()->post_type ) 
			&& (   groupz_is_read_post_type( get_current_screen()->post_type )
				|| groupz_is_edit_post_type( get_current_screen()->post_type ) 
				) 
			) : ?>

	<style type="text/css">
		.column-groups,
		.column-edit_groups {
			width: 10% !important;
		}
	</style>

		<?php endif;
	}

	/** List Tables **************************************************/

	/**
	 * Output dropdown to filter posts per group
	 *
	 * Does not require a 'include subgroups' checbox since
	 * the tax_query includes all subgroups.
	 *
	 * @since 0.1
	 *
	 * @uses groupz_is_read_post_type()
	 * @uses dropdown_groups()
	 */
	public function post_groups_dropdown() {

		// Bail if current user cannot view groups
		if ( ! current_user_can( 'assign_groups' ) )
			return;

		// Bail if post type is not supported
		if ( ! groupz_is_read_post_type( isset( $_GET['post_type'] ) ? $_GET['post_type'] : '' ) )
			return;

		// Setup dropdown args
		$args = array(
			'selected' => isset( $_GET['group'] ) ? $_GET['group'] : false,
			'class' => 'select_groups dropdown_groups',
			'name' => 'group', 'hierarchical' => true,
			'id' => 'groupz-select-group',
			'show_option_none' => __('Show all groups', 'groupz')
		); ?>

		<label class="screen-reader-text" for="groupz-select-group"><?php _e( 'Show all groups', 'groupz' ) ?></label>
		<?php dropdown_groups( $args );
	}

	/**
	 * Output dropdown to filter posts per edit group
	 *
	 * @since 0.1
	 *
	 * @uses groupz_is_edit_post_type()
	 * @uses dropdown_groups()
	 */
	public function edit_groups_dropdown() {

		// Bail if current user cannot view edit groups
		if ( ! current_user_can( 'assign_edit_groups' ) )
			return;

		// Bail if post type is not supported
		if ( ! groupz_is_edit_post_type( isset( $_GET['post_type'] ) ? $_GET['post_type'] : '' ) )
			return;

		// Setup dropdown args
		$args = array(
			'selected' => isset( $_GET['group'] ) ? $_GET['group'] : false,
			'class' => 'select_groups dropdown_groups',
			'name' => 'edit_group', 'hierarchical' => true,
			'id' => 'groupz-select-edit-group', 'is_edit' => true,
			'show_option_none' => __('Show all edit groups', 'groupz')
		); ?>

		<label class="screen-reader-text" for="groupz-select-edit-group"><?php _e( 'Show all edit groups', 'groupz' ) ?></label>
		<?php dropdown_groups( $args );
	}

	/**
	 * Add custom read groups column to the list table
	 *
	 * @since 0.1
	 *
	 * @param array $columns Current table columns
	 * @return array $columns
	 */
	public function post_read_table_column( $columns ) {

		// Read privilege column
		if ( current_user_can( 'assign_groups' ) )
			$columns['groups'] = __('Read Privilege', 'groupz');

		return $columns;
	}

	/**
	 * Add custom edit groups column to the list table
	 *
	 * @since 0.1
	 *
	 * @param array $columns Current table columns
	 * @return array $columns
	 */
	public function post_edit_table_column( $columns ) {

		// Edit groups column
		if ( current_user_can( 'assign_edit_groups' ) )
			$columns['edit_groups'] = __('Edit Privilege', 'groupz');

		return $columns;
	}

	/**
	 * Output the custom column content to the list table
	 *
	 * Generates HTML for 'read' and 'edit' group column
	 *
	 * @since 0.1
	 * 
	 * @param string $column Current column name
	 * @param int $post_id Current post ID
	 */
	public function posts_table_column_content( $column, $post_id ) {
		$content = '';
		$groups  = array();

		// Read columnn
		if ( 'groups' == $column ){
			foreach ( get_post_groups( $post_id, false ) as $group )
				$groups[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'group', $group->term_id ), $group->name );
		}

		// Edit column
		if ( 'edit_groups' == $column ){
			foreach ( get_post_edit_groups( $post_id, false ) as $group )
				$groups[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'edit_group', $group->term_id ), $group->name );
		}

		// Append group names
		if ( ! empty( $groups ) )
			$content = join( ', ', $groups );

		echo $content;
	}

	/**
	 * Adjust list table query to return only posts that 
	 * are in given groups
	 *
	 * Checks for groups on edit.php only.
	 *
	 * Includes posts of child groups.
	 *
	 * @since 0.1
	 * 
	 * @param WP_Query $query
	 */
	public function post_groups_list_table_query( $query ) {
		global $pagenow;

		// Bail if not on edit.php
		if ( 'edit.php' != $pagenow )
			return;

		// Bail if post type is not supported
		if (   ( ! isset( $_GET['post_type'] ) && ! groupz_is_read_post_type() )
			|| (   isset( $_GET['post_type'] ) && ! groupz_is_read_post_type( $_GET['post_type'] ) )
			)
			return;

		// Bail if no or invalid group given
		if ( ! isset( $_GET['group'] ) || $_GET['group'] <= 0 )
			return;

		// Sanitize group ID
		if ( is_numeric( $_GET['group'] ) ) {
			$group_id = (int) $_GET['group'];
		} else {
			$group    = get_term_by( 'slug', $_GET['group'], groupz_get_group_tax_id() );
			$group_id = $group->term_id;
		}

		// Get current query var
		$tax_query = (array) $query->get( 'tax_query' );

		// Make new value
		$tax_query[] = array(
			'taxonomy' => groupz_get_group_tax_id(),
			'terms'    => array( $group_id )
		);

		// Set query var
		$query->set( 'tax_query', $tax_query );
	}
	
	/**
	 * Adjust list table query to return only posts that 
	 * are in given edit groups
	 *
	 * Checks for edit groups on edit.php only.
	 *
	 * @since 0.1
	 * 
	 * @param WP_Query $query
	 */
	public function edit_groups_list_table_query( $query ) {
		global $pagenow;

		// Bail if not on edit.php
		if ( 'edit.php' != $pagenow )
			return;

		// Bail if post type is not supported
		if (   ( ! isset( $_GET['post_type'] ) && ! groupz_is_edit_post_type() )
			|| (   isset( $_GET['post_type'] ) && ! groupz_is_edit_post_type( $_GET['post_type'] ) )
			)
			return;

		// Bail if no or invalid group given
		if ( ! isset( $_GET['edit_group'] ) || $_GET['edit_group'] <= 0 )
			return;

		// Sanitize group ID
		if ( is_numeric( $_GET['edit_group'] ) ) {
			$group_id = (int) $_GET['edit_group'];
		} else {
			$group    = get_term_by( 'slug', $_GET['edit_group'], groupz_get_group_tax_id() );
			$group_id = $group->term_id;
		}

		// Prevent looping
		remove_action( 'pre_get_posts', __FUNCTION__ );

		// Set query var - previous 'post__in' must not interfere ?
		$query->set( 'post__in', Groupz_Core::get_edit_group_posts( $group_id ) );

		// Reset action
		add_action( 'pre_get_posts', __FUNCTION__ );
	}
	
	/** Meta Boxes ***************************************************/

	/**
	 * Add meta boxes to the edit post screen
	 *
	 * @since 0.1
	 *
	 * @uses groupz_get_read_post_types() To allow for assigned post types
	 * @uses add_meta_box() To add the meta boxes
	 * 
	 * @param string $post_type The post type to add meta boxes for
	 * @param object $post
	 */
	public function add_meta_boxes( $post_type, $post ) {

		// Only show post groups box for allowed users that have groups
		if (   groupz_is_read_post_type( $post_type )
			&& current_user_can( 'assign_groups' ) 
			&& ( current_user_can( 'assign_others_groups' ) || user_has_group() )
			) {

			add_meta_box(
				'groupz_post_groups',
				__( 'Read Privilege', 'groupz' ),
				array( $this, 'meta_box_post_groups' ),
				$post_type,
				'side',
				'core'
			);
		}

		// Only show post edit groups box for allowed users that have groups
		if (   groupz_is_edit_post_type( $post_type )
			&& current_user_can( 'assign_edit_groups' ) 
			&& ( current_user_can( 'assign_others_edit_groups' ) || user_has_group() )
			) {

			add_meta_box(
				'groupz_edit_groups',
				__( 'Edit Privilege', 'groupz' ),
				array( $this, 'meta_box_edit_groups' ),
				$post_type,
				'side',
				'core'
			);
		}
	}

	/**
	 * Output post groups metabox
	 * 
	 * @since 0.1
	 *
	 * @uses wp_nonce_field()
	 * @uses select_groups()
	 * @uses get_post_groups()
	 * @uses get_not_user_groups()
	 * 
	 * @param object $post The current post
	 */
	public function meta_box_post_groups( $post ) {
		?>
			<div id="taxonomy-<?php echo $this->tax; ?>" class="groupsdiv">
				<?php wp_nonce_field( 'groupz_post_groups', 'groupz_post_groups_nonce' ); ?>
				<?php select_groups( array( 
					'name'     => 'groupz_post_groups[]', 
					'selected' => get_post_groups( $post->ID ), 
					'width'    => 257, // !
					'exclude'  => current_user_can( 'assign_others_groups' ) ? array() : get_not_user_groups() // Restrict unselectable groups
				) ); ?>
			</div>
		<?php
	}

	/**
	 * Output post edit groups metabox
	 *
	 * @since 0.1
	 * 
	 * @uses wp_nonce_field()
	 * @uses select_groups()
	 * @uses get_post_edit_groups()
	 * @uses get_not_user_groups()
	 * 
	 * @param object $post The current post
	 */
	public function meta_box_edit_groups( $post ) {
		?>
			<div id="taxonomy-<?php echo $this->tax; ?>-edit_groups" class="groupsdiv">
				<?php wp_nonce_field( 'groupz_edit_groups', 'groupz_edit_groups_nonce' ); ?>
				<?php select_groups( array( 
					'name'     => 'groupz_edit_groups[]', 
					'selected' => get_post_edit_groups( $post->ID ), 
					'is_edit'  => true, 
					'width'    => 257, // !
					'exclude'  => current_user_can( 'assign_others_edit_groups' ) ? array() : get_not_user_groups() // Restrict unselectable groups
				) ); ?>
			</div>
		<?php
	}

	/**
	 * Save the post groups and post edit groups on save_post()
	 *
	 * @since 0.1
	 * 
	 * @param int $post_id The post ID
	 * @param object $post The post object
	 */
	public function save_meta_boxes( $post_id ) {

		// Bail if doing an autosave
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		// Bail if not a post request
		if ( 'POST' != strtoupper( $_SERVER['REQUEST_METHOD'] ) )
			return;

		// Bail if current user cannot edit this item
		if ( ! current_user_can( get_post_type_object( get_post_type( $post_id ) )->cap->edit_post, $post_id ) )
			return;

		/**
		 * Handle post groups
		 *
		 * Current user must be able to assign groups to posts.
		 * If current user can not assign others groups, the already
		 * assigned others groups will be added to the update array.
		 * 
		 * @uses groupz_update_post_groups() To do the updating
		 */
		if (   isset( $_POST['groupz_post_groups_nonce'] ) 
			&& wp_verify_nonce( $_POST['groupz_post_groups_nonce'], 'groupz_post_groups' ) 
			&& current_user_can( 'assign_groups' ) // User can assign post groups
			) {

			// Sanitize ids
			$group_ids = array_map( 'intval', isset( $_POST['groupz_post_groups'] ) ? $_POST['groupz_post_groups'] : array() );

			// Handle unselectable groups
			if ( ! current_user_can( 'assign_others_groups' ) ) {
				$not_user_groups = get_not_user_groups( get_current_user_id() );

				// Loop over all already assigned post groups
				foreach ( get_post_groups( $post_id ) as $group_id ) {
					if ( in_array( $group_id, $not_user_groups ) )
						$group_ids[] = $group_id; // Append group ID to array
				}
			}

			// Update taxonomy relationship
			groupz_update_post_groups( $post_id, $group_ids );
		}

		/**
		 * Handle post edit groups
		 *
		 * Current user must be able to assign edit groups to posts.
		 * If current user can not assign others edit groups, the already
		 * assigned others edit groups will be added to the update array.
		 *
		 * @uses groupz_update_post_edit_groups() To do the updating
		 * @uses groupz_remove_post_edit_groups() To remove edit groups if non submitted
		 */
		if (   isset( $_POST['groupz_edit_groups_nonce'] ) 
			&& wp_verify_nonce( $_POST['groupz_edit_groups_nonce'], 'groupz_edit_groups' ) 
			&& current_user_can( 'assign_edit_groups' ) // User can assign post edit groups
			) {

			// Sanitize ids
			$group_ids = array_map( 'intval', isset( $_POST['groupz_edit_groups'] ) ? $_POST['groupz_edit_groups'] : array() );

			// Handle unselectable groups
			if ( ! current_user_can( 'assign_others_edit_groups' ) ) {
				$not_user_groups = get_not_user_groups( get_current_user_id() );

				// Loop over all already assigned edit groups
				foreach ( get_post_edit_groups( $post_id ) as $group_id ) {
					if ( in_array( $group_id, $not_user_groups ) )
						$group_ids[] = $group_id; // Append group ID to array
				}
			}

			// Update post edit groups 
			if ( ! empty( $group_ids ) ) {
				groupz_update_post_edit_groups( $post_id, $group_ids );

			// Remove post edit groups
			} else {
				groupz_remove_post_edit_groups( $post_id );
			}
		}
	}

	/**
	 * Removes edit groups from posts on group deletion
	 *
	 * @since 0.x
	 * 
	 * @param int $group_id Group ID
	 */
	public function remove_post_edit_groups( $group_id ) {
		if ( groupz_is_edit_group( $group_id ) )
			groupz_remove_edit_group_from_posts( $group_id );
	}

	/** Group Admin **************************************************/

	/**
	 * Display additional information after the group list table
	 * 
	 * @since 0.1
	 * 
	 * @param string $taxonomy The taxonomy
	 */
	public function after_table_info( $taxonomy ) {
		?>
			<div class="form-wrap">
				<p>
					<?php _e('<strong>Note:</strong><br />Deleting a group does not delete the posts in that group.'); ?>

					<?php if ( get_option('_groupz_set_private') ) : 
						 _e('Instead, posts that were only assigned to the deleted group are set to <strong>private</strong>.', 'groupz');
					 else : 
						 printf( _e('Instead, posts that were only assigned to the deleted group remain <strong>as is</strong> &ndash; most of the time <strong>public</strong>.', 'groupz') );
					 endif; ?>

					<?php if ( current_user_can( 'manage_options' ) ) printf( __('You can change this setting <a href="%s">here</a>.', 'groupz'), add_query_arg( 'page', 'groupz-settings', 'options-general.php' ) ); ?>
				</p>
			</div>
		<?php
	}

}

endif; // class_exists

/**
 * Setup posts admin area
 *
 * @since 0.x
 *
 * @uses Groupz_Posts_Admin
 */
function groupz_posts_admin() {
	groupz()->admin->posts = new Groupz_Posts_Admin;
}
