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
 * @todo Reset parent group user count after child group deletion
 */
class Groupz_Group_Admin {

	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Declare default class globals
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

		// Scripts & Styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_tooltip' ) );
		add_action( 'admin_head',            array( $this, 'print_tooltip'   ) );
		
		// Add Group Form
		add_action( "{$this->tax}_add_form_fields",  array( $this, 'add_form_fields'  ) );
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

	/** Scripts & Styles *********************************************/

	/**
	 * Enqueue scripts for admin page tooltips
	 *
	 * @since 0.x
	 *
	 * @uses groupz_is_admin_page()
	 * @uses wp_register_script()
	 * @uses wp_register_style()
	 * @uses wp_enqueue_script()
	 * @uses wp_enqueue_style()
	 */
	public function enqueue_tooltip() {
		if ( ! groupz_is_admin_page() )
			return;

		// Register Tipsy
		wp_register_script( 'tipsy', groupz()->admin->admin_url . 'scripts/jquery.tipsy.min.js', array( 'jquery' ) );
		wp_register_style(  'tipsy', groupz()->admin->admin_url . 'scripts/tipsy.css' );

		// Enqueue Tipsy
		wp_enqueue_script( 'tipsy' );
		wp_enqueue_style(  'tipsy' );		
	}

	/**
	 * Output scripts for admin page tooltips
	 *
	 * @since 0.x
	 *
	 * @uses groupz_is_admin_page()
	 */
	public function print_tooltip() {
		if ( ! groupz_is_admin_page() )
			return;

		?>
<script type="text/javascript">
	jQuery(document).ready( function($) {
		$('td.column-users a').tipsy({
			title:   'data-tooltip',
			gravity: $.fn.tipsy.autoWE,
			html:    true,
			live:    true,
			opacity: 1,
			offset:  5
		});
	});
</script>
<style type="text/css">
	.tipsy-inner {
		background: #000;
		padding: 4px 8px;
		text-align: left;
		font-size: 11px;
		line-height: 14px;
		font-family: 'lucida grande', tahoma, verdana, arial, sans-serif;
	}
</style>
		<?php
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

		// Remove posts column
		if ( isset( $columns['posts'] ) )
			unset( $columns['posts'] );

		$params = groupz_get_group_params();

		// Add users column on second place
		$columns = array_merge( 
			array_slice( $columns, 0, 2 ), // First array part
			array( 'users' => $params['users']['label'] ), // Insert element
			array_slice( $columns, 2 ) // Last array part
		);

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
	 * @return string Column content
	 */
	public function add_table_column_content( $content, $column_name, $term_id ) {

		// Add users column content
		if ( 'users' == $column_name ) {

			// Get user count
			$users   = groupz_group_get_users( $term_id );
			$count   = number_format_i18n( count( $users ) );
			$tooltip = groupz_users_tooltip( $users );

			// Setup content string
			$content = sprintf( '<a href="%2$s" data-tooltip="%3$s">%1$s</a>', $count, esc_url( add_query_arg( 'groupz_group_id', $term_id, 'users.php' ) ), $tooltip );

			// Has subgroups
			$children = get_subgroups( $term_id );

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
					$uni_users = array_unique( array_merge( $users, $unique_users ) );
					$args      = array( 'groupz_group_id' => $term_id, 'groupz_family' => true );
					$tooltip   = groupz_users_tooltip( $uni_users );

					// Append child user count
					$content  .= sprintf( ' <a href="%s" data-tooltip="%s">(%s)</a>', esc_url( add_query_arg( $args, 'users.php' ) ), $tooltip, number_format_i18n( count( $uni_users ) ) );
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

