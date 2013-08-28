<?php

/**
 * Groupz Admin
 *
 * @package Groupz
 * @subpackage Admin
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Users_Admin' ) ) :

/**
 * Groupz Users Admin Class
 *
 * @since 0.1
 */
class Groupz_Users_Admin {

	public function __construct(){
		$this->setup_actions();
	}

	public function setup_actions(){

		// Bail if in network admin
		if ( is_network_admin() )
			return;

		// User profile edit/display actions
		add_action( 'edit_user_profile', array( $this, 'user_groups_display' ) );
		add_action( 'show_user_profile', array( $this, 'user_groups_display' ) );

		// WordPress user screen
		add_action( 'restrict_manage_users',      array( $this, 'user_groups_dropdown' )        );
		add_filter( 'manage_users_columns',       array( $this, 'user_groups_column'   )        );
		add_filter( 'manage_users_custom_column', array( $this, 'user_groups_row'      ), 10, 3 );
		add_action( 'pre_user_query',             array( $this, 'pre_user_query'       )        );
	}

	/**
	 * Default interface for setting the user groups
	 *
	 * @param WP_User $profileuser User data
	 * @return void
	 */
	public static function user_groups_display( $profileuser ) {

		// Bail if current user cannot edit users or manage group users
		if ( !current_user_can( 'edit_user', $profileuser->ID ) || !current_user_can( 'manage_group_users' ) )
			return;

		// Setup select args
		$args = array(
			'id' => 'groupz-user-groups',
			'name' => 'groupz-user-groups[]',
			'width' => 301, // !
			'selected' => get_user_groups( $profileuser->ID )
			); ?>

		<h3><?php _e( 'Groups', 'groupz' ); ?></h3>

		<table class="form-table">
			<tbody>
				<tr>
					<th><label for="groupz-user-groups"><?php _e( 'User Groups', 'groupz' ); ?></label></th>
					<td>

						<?php wp_nonce_field( 'groupz_user_groups', 'groupz_user_groups_nonce' ); ?>
						<?php select_groups( $args ); ?>

						<?php if ( user_can( $profileuser->ID, 'ignore_groups' ) ) : ?>
							<p>
								<span class="description">
									<?php 
									if ( get_current_user_id() == $profileuser->ID )
										_e('<strong>NOTE:</strong> By default you can ignore any group restrictions.', 'groupz');

									else
										_e('<strong>NOTE:</strong> By default this user can ignore any group restrictions.', 'groupz'); 
									
									?>
								</span>
							</p>
						<?php endif; ?>

					</td>
				</tr>

			</tbody>
		</table>

		<?php
	}

	/** Wordpress user screen ****************************************/

	public function user_groups_dropdown(){

		// Setup dropdown args
		$args = array(
			'selected' => isset( $_GET['groupz_group_id'] ) ? $_GET['groupz_group_id'] : false,
			'class' => 'select_groups dropdown_groups',
			'name' => 'groupz_group_id', 'hierarchical' => true,
			'id' => 'groupz-select-group',
			'show_option_none' => __('Filter users of group&hellip;', 'groupz')
			); ?>

		<label class="screen-reader-text" for="groupz-select-group"><?php _e( 'Filter users of group&hellip;', 'groupz' ) ?></label>
		<?php dropdown_groups( $args ); ?>

		<label class="screen-reader-text" for="groupz-select-family"><?php _e( 'Filter to include subgroups', 'groupz' ) ?></label>
		<input id="groupz-select-family" type="checkbox" name="groupz_family" value="1" <?php checked( isset( $_GET['groupz_family'] ) && $_GET['family'] ); ?> title="<?php _e('Filter to include subgroups', 'groupz'); ?>" />

		<?php submit_button( __( 'Filter', 'groupz' ), 'secondary', '', false );
	}

	/**
	 * Adds a groups column to the users list table
	 * 
	 * @param array $columns
	 * @return array $columns
	 */
	public function user_groups_column( $columns ){

		// Show group column if user is capable
		if ( current_user_can( 'manage_group_users' ) )
			$columns['groupz_groups'] = __('Groups', 'groupz');

		return $columns;
	}

	/**
	 * Output the groups for the given user in the groups column
	 * 
	 * @uses get_user_groups()
	 * 
	 * @param string $content
	 * @param string $column Column ID
	 * @param int $user_id User ID
	 * @return string $content HTML output
	 */
	public function user_groups_row( $content, $column, $user_id ){

		// Column check
		switch ( $column ) {

			case 'groupz_groups' :
				$groups = array();

				foreach ( get_user_groups( $user_id, false ) as $group ){

					// Setup group link
					$groups[] = sprintf(
						'<a href="%s"%s>%s</a>',
						add_query_arg( 'groupz_group_id', $group->term_id, 'users.php' ),
						$group->parent 
							? sprintf( ' title="%s"', 
								sprintf( 
									__('Subgroup of %s', 'groupz'), 
									get_group_name( $group->parent ) 
									) 
								) 
							: '',
						$group->name
						);

				}

				if ( ! empty( $groups ) )
					$content = join( ', ', $groups );

				break;
		}

		return $content;
	}

	/**
	 * Filter WP_User_Query for group users appending the where clause
	 *
	 * @uses get_group_users()
	 * @uses get_term_children()
	 * 
	 * @param WP_User_Query $query
	 */
	public function pre_user_query( $query ){
		global $wpdb;

		// Force users to be in group when requested
		if ( ! isset( $_GET['groupz_group_id'] ) || ! $_GET['groupz_group_id'] >= 0 )
			return;
		else
			$group_id = (int) $_GET['groupz_group_id'];

		// Get group users
		$users = get_group_users( $group_id );

		// Add family users when requested
		if ( isset( $_GET['groupz_family'] ) && true == $_GET['groupz_family'] ){
			foreach ( get_term_children( $group_id, groupz_get_group_tax_id() ) as $group_id )
				$users = array_unique( array_merge( $users, get_group_users( $group_id ) ) );
		}

		// Bail if no users were found
		if ( empty( $users ) )
			return;

		// Add to query where clause
		$ids = implode( ',', $users );
		$query->query_where .= " AND {$wpdb->users}.ID IN ($ids)";
	}

}

endif; // class_exists

/**
 * Load users admin area
 *
 * @since 0.x
 *
 * @uses Groupz_Users_Admin
 */
function groupz_users_admin() {
	groupz()->admin->users = new Groupz_Users_Admin;
}

