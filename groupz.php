<?php

/**
 * Plugin Name: Groupz
 * Plugin URI: www.offereinspictures.nl/wp/plugins/groupz/
 * Description: User groups done the right way. Management, access and more.
 * Version: 0.1
 * Author: Laurens Offereins
 * Author URI: http://www.offereinspictures.nl
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Groupz' ) ) :

/**
 * Plugin class
 *
 * Big hat tip to bbPress for the code structure.
 */
final class Groupz {

	/** Data *********************************************************/

	/**
	 * @var array Our main data holder
	 */
	private $data;

	/** Singleton ****************************************************/

	/**
	 * @var Groupz The one true Groupz
	 */
	private static $instance;

	/**
	 * Main Groupz Instance
	 *
	 * Insures that only one instance of Groupz exists in memory at any one
	 * time. Also prevents needing to define globals all over the place.
	 *
	 * @static array $instance
	 * @uses Groupz::setup_globals() Setup the globals needed
	 * @uses Groupz::setup_requires() Include the required files
	 * @uses Groupz::setup_actions() Setup the hooks and actions
	 * @see groupz()
	 * @return The one true Groupz
	 */
	public static function instance() {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new Groupz;
			self::$instance->setup_globals();
			self::$instance->setup_requires();
			self::$instance->setup_actions();
		}
		return self::$instance;
	}

	/** Magic Methods *********************************************************/

	/**
	 * A dummy constructor to prevent Groupz from being loaded more than once.
	 * 
	 * @see Groupz::instance()
	 * @see groupz();
	 */
	private function __construct() { /* Do nothing here */ }

	/**
	 * A dummy magic method to prevent Groupz from being cloned
	 */
	public function __clone() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'groupz' ) ); }

	/**
	 * A dummy magic method to prevent Groupz from being unserialized
	 */
	public function __wakeup() { _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?', 'groupz' ) ); }

	/**
	 * Magic method for checking the existence of a certain custom field
	 */
	public function __isset( $key ) { return isset( $this->data[$key] ); }

	/**
	 * Magic method for getting Groupz variables
	 */
	public function __get( $key ) { return isset( $this->data[$key] ) ? $this->data[$key] : null; }

	/**
	 * Magic method for setting Groupz variables
	 */
	public function __set( $key, $value ) { $this->data[$key] = $value; }

	/**
	 * Magic method to prevent notices and errors from invalid method calls
	 */
	public function __call( $name = '', $args = array() ) { unset( $name, $args ); return null; }

	/**
	 * Declare some required class variables
	 */
	private function setup_globals(){

		/** Version ******************************************************/

		$this->version      = '0.1';

		/** Plugin *******************************************************/

		$this->file         = __FILE__;
		$this->basename     = plugin_basename( $this->file );
		$this->plugin_url   = plugins_url( '/', $this->file );
		$this->plugin_dir   = plugin_dir_path( $this->file );

		$this->includes_dir = $this->plugin_dir .'includes/';
		$this->includes_url = $this->plugin_url .'includes/';

		/** Group ********************************************************/
		
		$this->group_tax_id     = apply_filters( 'groupz_group_tax_id', 'group' );

		$this->read_post_types  = get_option( 'groupz_read_post_types', array() );
		$this->edit_post_types  = get_option( 'groupz_edit_post_types', array() );

		/** Misc *********************************************************/
		
		$this->domain   = 'groupz';
		$this->pre_meta = '_groupz_meta_';
		$this->extend   = new stdClass();
	}

	/**
	 * Load the required files for this thing to work
	 */
	private function setup_requires(){

		/** Core *********************************************************/
		
		require( $this->includes_dir . 'core/access.php'       );
		require( $this->includes_dir . 'core/actions.php'      );
		require( $this->includes_dir . 'core/capabilities.php' );
		require( $this->includes_dir . 'core/extend.php'       );
		require( $this->includes_dir . 'core/functions.php'    );
		require( $this->includes_dir . 'core/group.php'        );
		require( $this->includes_dir . 'core/helpers.php'      );

		/** Users ********************************************************/
		
		require( $this->includes_dir . 'users/functions.php'   );

		/** Admin ********************************************************/
		
		if ( is_admin() ){
			require( $this->includes_dir . 'admin/admin.php'   );
		}

	}

	/**
	 * Hook the required actions to get in the action
	 */
	private function setup_actions(){

		/** Plugin *******************************************************/

		add_action( 'activate_'.   $this->basename, 'groupz_activation'          );
		add_action( 'deactivate_'. $this->basename, 'groupz_deactivation'        );
		add_action( 'uninstall_'.  $this->basename, 'groupz_uninstall'           );
		add_action( 'plugins_loaded',               array( $this, 'textdomain' ) );

		/** Groups *******************************************************/

		add_action( 'plugins_loaded', array( $this, 'register_group_taxonomy' ) );

	}

	/** Plugin *******************************************************/

	/**
	 * Load the translation files
	 */
	public function textdomain(){
		load_plugin_textdomain( $this->domain, false, dirname( $this->basename ) . '/languages/' );
	}

	/** Groups *******************************************************/

	/**
	 * Return read post types for which groups are registered
	 *
	 * Adds 'any' as valid read post type.
	 *
	 * @uses get_post_types()
	 * @return array $post_types Post type names
	 */
	public function get_read_post_types(){
		return apply_filters( 'groupz_get_read_post_types', $this->read_post_types );
	}

	/**
	 * Return edit post types for which groups are registered
	 *
	 * @uses get_post_types()
	 * @return array $post_types Post type names
	 */
	public function get_edit_post_types(){
		return apply_filters( 'groupz_get_edit_post_types', $this->edit_post_types );
	}

	/**
	 * Return unsupported default WP post types
	 *
	 * At this moment in time this concerns
	 * - attachment
	 * - nav_menu_item
	 * 
	 * @uses get_post_types()
	 * @return array $post_types Post type names
	 */
	public function get_unsupported_post_types(){
		return apply_filters( 'groupz_get_unsupported_post_types', array( 'attachment', 'nav_menu_item' ) );
	}

	/**
	 * Register our main element, the group taxonomy
	 *
	 * The 'show_admin_column' argument creates a taxonomy column
	 * in the post tables, only since WP 3.5.
	 *
	 * @uses register_taxonomy()
	 * @return void
	 */
	public function register_group_taxonomy(){

		// Custom labels
		$labels = array(
			'name'              => __('Groups', 'groupz'),
			'singular_name'     => __('Group', 'groupz'),
			'search_items'      => __('Search Groups', 'groupz'),
			'all_items'         => __('All Groups', 'groupz'),
			'parent_item'       => __('Parent Group', 'groupz'),
			'parent_item_colon' => __('Parent Group:', 'groupz'),
			'edit_item'         => __('Edit Group', 'groupz'),
			'view_item'         => __('View Group', 'groupz'),
			'update_item'       => __('Update Group', 'groupz'),
			'add_new_item'      => __('Add New Group', 'groupz'),
			'new_item_name'     => __('New Group Name', 'groupz'),
			);

		// Custom capabilities
		$caps = array(
			'manage_terms'      => 'manage_groups',
			'edit_terms'        => 'edit_groups',
			'delete_terms'      => 'delete_groups',
			'assign_terms'      => 'assign_groups'
			);

		register_taxonomy( 
			groupz_get_group_tax_id(), 
			$this->get_read_post_types(), 
			apply_filters( 'groupz_register_group_taxonomy', array(
				'public'            => false,
				'labels'            => $labels,
				'capabilities'      => $caps,
				'hierarchical'      => true,
				'rewrite'           => false
				) )
			);
	}

}

/**
 * Return the one true Groupz instance
 * 
 * @return object The instance of Groupz
 */
function groupz(){
	return Groupz::instance();
}

// Do the magic
groupz();

endif; // class_exists

