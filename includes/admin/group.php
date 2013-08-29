<?php

/**
 * Groupz Admin Group class
 *
 * @package Groupz
 * @subpackage Administration
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Group_Admin' ) ) :

/**
 * Main Groupz Group Admin Class
 * 
 * This class serves all the admin UI elements to 
 * handle group management.
 *
 * @since 0.1
 *
 * @todo Empty users input field after group creation
 */
class Groupz_Group_Admin {

	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Setup default actions and filters
	 * 
	 * @since 0.1
	 */
	private function setup_actions() {

		// Add Group Form
		add_action( "{$this->tax}_add_form_fields",  array( $this, 'add_form_fields'  ) );
		add_action( "after-{$this->tax}-table",      array( $this, 'after_table_info' ) );
		// add_action( "{$this->tax}_pre_add_form",     array( $this, 'pre_add_form'     ) );
		// add_action( "{$this->tax}_add_form",         array( $this, 'add_form'         ) );

		// Groups List Table
		add_filter( "manage_edit-{$this->tax}_columns",          array( $this, 'add_table_column'         ), 10, 2 );
		add_filter( "manage_edit-{$this->tax}_sortable_columns", array( $this, 'add_sortable_column'      )        );
		add_filter( "manage_{$this->tax}_custom_column",         array( $this, 'add_table_column_content' ), 10, 3 );
		
		// Edit Group Form
		add_action( "{$this->tax}_edit_form_fields", array( $this, 'edit_form_fields' ), 10, 2 );
		// add_action( "{$this->tax}_pre_edit_form",    array( $this, 'pre_edit_form'    ), 10, 2 );
		// add_action( "{$this->tax}_edit_form",        array( $this, 'edit_form'        ), 10, 2 );
	}

	/**
	 * Declare default class globals
	 *
	 * @since 0.1
	 */
	private function setup_globals() {
		$this->tax = groupz_get_group_tax_id();
	}

	/** Add Group Form ***********************************************/

	/**
	 * Output additional form fields for the groups properties
	 * on the add group form
	 *
	 * @since 0.1
	 *
	 * @uses groupz_get_group_params() To get the group parameters
	 */
	public function add_form_fields() {

		// Loop over all group parameters
		foreach ( groupz_get_group_params() as $param => $args ) {

			// Only when field callback is set
			if ( ! isset( $args['field_callback'] ) )
				continue;

			// Output HTML
			?>
				<div class="form-field">
					<label for="groupz_<?php echo $param; ?>"><?php echo $args['label']; ?></label>
					<?php wp_nonce_field( "groupz_{$param}", "groupz_{$param}_nonce" ); ?>
					<?php call_user_func_array( $args['field_callback'], array( 0 ) ); ?><br />
					<p><?php echo $args['description']; ?></p>
				</div>
			<?php
		}
	}

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

	/** Groups List Table ********************************************/

	/**
	 * Adds the users column to the group list table
	 *
	 * Also removes slug column
	 *
	 * @since 0.1
	 *
	 * @param array $columns List table columns
	 * @return array $columns
	 */
	public function add_table_column( $columns ) {

		// Remove slug column
		if ( isset( $columns['slug'] ) ) 
			unset( $columns['slug'] );

		$params = groupz_get_group_params();

		// Add users column
		$columns['users'] = $params['users']['label'];

		return $columns;
	}

	/**
	 * Adds the users column to the list table sortable columns
	 * 
	 * @since 0.1
	 * 
	 * @param array $columns List table columns
	 * @return array $columns
	 */
	public function add_sortable_column( $columns ) {

		// Add users sortable column
		$columns['users'] = 'users';

		return $columns;
	}

	/**
	 * Return the users column content in the group list table
	 *
	 * @since 0.1
	 * 
	 * @param string $content Current content
	 * @param string $column_name Column name
	 * @param int $term_id Group ID
	 */
	public function add_table_column_content( $content, $column_name, $term_id ) {

		// Add users column content
		if ( 'users' == $column_name ) {

			// Get user count
			$users = groupz_group_get_users( $term_id );
			$count = number_format_i18n( count( $users ) );

			// Setup return string. The data-users attribute must ensure correct group sorting
			$content = "<a data-users='$count' href='" . esc_url ( add_query_arg( 'groupz_group_id', $term_id, 'users.php' ) ) . "'>$count</a>";

			// Has subgroups
			$children = get_term_children( $term_id, groupz_get_group_tax_id() );

			// Add count of subgroup users
			if ( ! empty( $children ) ) {
				$sub_users = $unique_users = array();

				// Gather sub group users and count them
				foreach ( $children as $group_id ){
					$child_users          = groupz_group_get_users( $group_id );
					$unique_users         = array_unique( array_merge( $unique_users, $child_users ) );
					$sub_users[$group_id] = count( $child_users );
				}

				if ( ! empty( $sub_users ) ) {
					$sub_count = number_format_i18n( array_sum( $sub_users ) + count( $users ) );
					$uni_count = number_format_i18n( count( array_unique( array_merge( $unique_users, $users ) ) ) );
					$args      = array( 'groupz_group_id' => $term_id, 'groupz_family' => true );
					$title     = sprintf( __('User count with subgroups (%d unique)', 'groupz'), $uni_count );

					// Append child user count
					$content  .= " <a href='" . esc_url ( add_query_arg( $args, 'users.php' ) ) . "' title='$title'>($sub_count)</a>";
				}
			}
		}

		return $content;
	}

	/** Edit Group Form **********************************************/

	/**
	 * Add additional form fields for the group meta on the edit group form
	 *
	 * @since 0.1
	 *
	 * @uses groupz_get_group_params() To get the group parameters
	 * 
	 * @param object $tag The tag object
	 * @param string $taxonomy The taxonomy type
	 * @return void
	 */
	public function edit_form_fields( $tag, $taxonomy ) {

		// Loop over all group parameters
		foreach ( groupz_get_group_params() as $param => $args ) {

			// Only when field callback is set
			if ( ! isset( $args['field_callback'] ) )
				continue;

			// Output HTML
			?>
				<tr class="form-field">
					<th scope="row" valign="top"><label for="groupz_<?php echo $param; ?>"><?php echo $args['label']; ?></label></th>
					<td>
						<?php wp_nonce_field( "groupz_{$param}", "groupz_{$param}_nonce" ); ?>
						<?php call_user_func_array( $args['field_callback'], array( $tag->term_id ) ); ?><br />
						<span class="description"><?php echo $args['description']; ?></span>
					</td>
				</tr>
			<?php
		}
	}
}

endif; // class_exists

/**
 * Setup Group Admin area
 *
 * @since 0.x
 *
 * @uses Groupz_Group_Admin
 */
function groupz_group_admin() {
	groupz()->admin->group = new Groupz_Group_Admin;
}

