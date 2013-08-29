<?php

/**
 * Groupz Admin
 *
 * @package Groupz
 * @subpackage Administration
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz_Admin' ) ) :

/**
 * Main Groupz Admin class
 *
 * @since 0.1
 */
class Groupz_Admin {

	public function __construct(){
		$this->setup_globals();
		$this->includes();
		$this->setup_actions();
	}

	/**
	 * Define default global variables
	 *
	 * @since 0.1
	 */
	private function setup_globals(){

		// Admin setup
		$this->tax       = groupz_get_group_tax_id();
		$this->admin_dir = trailingslashit( groupz()->includes_dir . 'admin' );
		$this->admin_url = trailingslashit( groupz()->includes_url . 'admin' );

		// Chosen.js
		$this->use_chosen = apply_filters( 'groupz_use_chosen', get_option( 'groupz_use_chosen', false ) );
	}

	/**
	 * Include required files
	 *
	 * @since 0.1
	 */
	private function includes(){
		require( $this->admin_dir . 'functions.php' );
		require( $this->admin_dir . 'group.php'     );
		require( $this->admin_dir . 'settings.php'  );
		require( $this->admin_dir . 'users.php'     );
	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.1
	 */
	private function setup_actions(){

		// Manage Groups
		add_action( 'admin_menu',            array( $this, 'admin_menus'        ) );
		add_filter( 'parent_file',           array( $this, 'groups_parent_file' ) );
		add_action( 'admin_print_styles',    array( $this, 'admin_page_styles'  ) );

		// Settings
		add_action( 'groupz_admin_init',     array( $this, 'register_settings'  ) );

		// Misc
		add_filter( 'plugin_action_links',   array( $this, 'plugin_action_links' ), 10, 2 );

		// Chosen
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_chosen'     ) );
		add_action( 'admin_head',            array( $this, 'enable_chosen'      ) );
	}

	/** Manage Groups ************************************************/

	/**
	 * Add groups and settings menu pages
	 * 
	 * @since 0.1
	 *
	 * @uses add_submenu_page()
	 * @uses add_options_page()
	 */
	public function admin_menus() {
		add_submenu_page( 
			'users.php', 
			__('Manage Groups', 'groupz'), 
			__('Groups',        'groupz'), 
			'manage_groups', 
			"edit-tags.php?taxonomy={$this->tax}"
		);

		add_options_page( 
			__('Groupz Settings', 'groupz'), 
			'Groupz', 
			'manage_options', 
			'groupz', 
			array( $this, 'settings_page' ) 
		);
	}

	/**
	 * Reset the edit group page menu parent to users.php
	 *
	 * On default the taxonomy edit pages have their menu 
	 * parent set to their respective post types, but we
	 * want it to have the Users tab as parent.
	 *
	 * @since 0.1
	 *
	 * @uses groupz_is_admin_page()
	 *
	 * @param string $parent The menu parent
	 * @return string $parent
	 */
	public function groups_parent_file( $parent ) {
		if ( groupz_is_admin_page() )
			$parent = 'users.php';

		return $parent;
	}

	/**
	 * Output groupz admin page only styles
	 *
	 * @since 0.1
	 *
	 * @uses groupz_is_admin_page()
	 */
	public function admin_page_styles() {
		if ( ! groupz_is_admin_page() )
			return;

		?>
			<style type="text/css">
				/* Correct checkbox input width */
				.form-field input[type="checkbox"] {
					width: auto;
				}

				.fixed .column-users {
					width: 10%;
				}

				.column-users {
					text-align: center;
				}
			</style>
		<?php
	}

	/** Settings *****************************************************/

	/**
	 * Output HTML for the admin settings page
	 * 
	 * @since 0.1
	 */
	public function settings_page() {
		?>
			<div class="wrap">
				<?php screen_icon(); ?>
				<h2><?php _e('Groupz Settings', 'groupz'); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'groupz' ); ?>
					<?php do_settings_sections( 'groupz' ); ?>
					<?php submit_button(); ?>
				</form>
			</div>
		<?php
	}

	/**
	 * Register all plugin settings
	 * 
	 * @since 0.1
	 * 
	 * @uses groupz_get_settings_sections() To fetch all the sections to render
	 * @uses groupz_get_settings_fields() To fetch all the fields to render
	 */
	public function register_settings() {

		// Register all sections
		foreach ( groupz_get_settings_sections() as $section_id => $args ) {
			add_settings_section( $section_id, $args['title'], $args['callback'], $args['page'] );
		}

		// Register all fields
		foreach ( groupz_get_settings_fields() as $field_id => $args ) {
			add_settings_field( $field_id, $args['title'], $args['callback'], $args['page'], $args['section'] );
			register_setting( $args['page'], $field_id, $args['sanitize'] );
		}
	}

	/** Misc *********************************************************/

	/**
	 * Add additional links to plugins area
	 *
	 * @since 0.1
	 *
	 * @param array $links Links array in which we would prepend our link
	 * @param string $file Current plugin basename
	 * @return array Processed links
	 */
	public static function plugin_action_links( $links, $file ) {

		// Return normal links if not Groupz
		if ( groupz()->basename != $file )
			return $links;

		// Add a few links to the existing links array
		return array_merge( $links, array(
			'settings' => '<a href="' . add_query_arg( array( 'page' => 'groupz' ), admin_url( 'options-general.php' ) ) . '">' . esc_html__( 'Settings', 'groupz' ) . '</a>',
			'groups'   => '<a href="' . groupz_get_admin_page_url()                                                      . '">' . esc_html__( 'Groups',   'groupz' ) . '</a>'
		) );
	}

	/** Chosen.js ****************************************************/

	/**
	 * Register and enqueue chosen.js for the right admin pages
	 *
	 * Adds livequery.js to enable chosen.js on dynamic pages.
	 *
	 * @since 0.1
	 *
	 * @uses groupz_is_admin_page()
	 * @uses groupz_use_chosen()
	 * @uses groupz_admin_page_with_groups()
	 * @param string $hook Admin page identifier
	 */
	public function enqueue_chosen( $hook ) {

		// Bail if not on admin page and missing requirements
		if ( ! groupz_is_admin_page() && ( ! groupz_use_chosen() || ! groupz_admin_page_with_groups( $hook ) ) )
			return;

		// Register Livequery
		wp_register_script( 'livequery', $this->admin_url . 'scripts/jquery.livequery.min.js', array( 'jquery' ), '1.1.1' );

		// Register Chosen
		wp_register_script( 'chosen', $this->admin_url . 'scripts/jquery.chosen.min.js', array( 'jquery' ), '0.9.8' );
		wp_register_style(  'chosen', $this->admin_url . 'scripts/chosen.css',           false,             '0.9.8' );

		// Enable jQuery
		wp_enqueue_script( 'jquery' );

		// Enable Livequery
		wp_enqueue_script( 'livequery' );

		// Enable Chosen
		wp_enqueue_script( 'chosen' );
		wp_enqueue_style( 'chosen' );
	}

	/**
	 * Print the chosen.js enabling javascript and some restyling css
	 *
	 * @since 0.1
	 * 
	 * @uses groupz_is_admin_page()
	 * @uses groupz_use_chosen()
	 * @uses groupz_admin_page_with_groups()
	 */
	public function enable_chosen() {

		// Bail if not on admin page and missing requirements
		if ( ! groupz_is_admin_page() && ( ! groupz_use_chosen() || ! groupz_admin_page_with_groups() ) )
			return;

		?>
			<script type="text/javascript">
				jQuery('.chzn-select').livequery( function(){
					jQuery(this).chosen({
						allow_single_deselect: true,
						no_results_matched: '<?php _e( "No results for", "groupz" ); ?>'
					});
				});
			</script>

			<style type="text/css">
				.chzn-container-multi .chzn-choices .search-field input {
					height: 25px;
					padding: 3px;
				}
			</style>
		<?php
	}

	/**
	 * Returns whether the given admin page requires chosen.js
	 * 
	 * @since 0.1
	 * 
	 * @param string $hook Page identifier. Defaults to pagenow global var
	 * @return boolean $chosen
	 */
	public function admin_page_with_groups( $hook = '' ) {
		global $pagenow;

		// Default to pagenow
		if ( empty( $hook ) )
			$hook = $pagenow;

		switch ( $hook ){

			case 'post.php' :
			case 'post-new.php' :
				$post_type = isset( $_GET['post_type'] ) ? $_GET['post_type'] : get_current_screen()->post_type;
				return groupz_is_read_post_type( $post_type );
				break;

			case 'user-edit.php' :
			case 'profile.php' :
				return current_user_can( 'manage_group_users' );

			default :
				break;
		}

		return false;
	}

}

endif; // class_exists

/**
 * Setup Groupz Admin
 * 
 * @since 0.1
 *
 * @uses Groupz_Admin
 * @uses Groupz_Group_Admin
 * @uses Groupz_Users_Admin
 */
function groupz_admin() {
	groupz()->admin        = new Groupz_Admin;
	groupz()->admin->group = new Groupz_Group_Admin;
	groupz()->admin->users = new Groupz_Users_Admin;
}
