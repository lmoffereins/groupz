<?php

/**
 * Plugin Name: Groupz
 * Description: User groups done the right way. Management, access and more.
 * Plugin URI:  www.offereinspictures.nl/wp/plugins/groupz/
 * Author:      Laurens Offereins
 * Author URI:  http://www.offereinspictures.nl
 * Version:     0.1
 * Text Domain: groupz
 * Domain Path: /languages/
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'Groupz' ) ) :

/**
 * The Main Groupz Class
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
	 * @see  groupz()
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
	 * Setup class default variables
	 *
	 * @since 0.1
	 */
	private function setup_globals(){

		/** Version ******************************************************/

		$this->version          = '0.1';
		$this->db_version       = '10';

		/** Plugin *******************************************************/

		$this->file             = __FILE__;
		$this->basename         = plugin_basename( $this->file );
		$this->plugin_dir       = plugin_dir_path( $this->file );
		$this->plugin_url       = plugin_dir_url(  $this->file );

		// Includes
		$this->includes_dir     = trailingslashit( $this->plugin_dir . 'includes' );
		$this->includes_url     = trailingslashit( $this->plugin_url . 'includes' );

		/** Group ********************************************************/
		
		$this->group_tax_id     = apply_filters( 'groupz_group_tax_id', 'group' );

		$this->read_post_types  = get_option( 'groupz_read_post_types', array() );
		$this->edit_post_types  = get_option( 'groupz_edit_post_types', array() );

		/** Misc *********************************************************/
		
		$this->domain   = 'groupz';
		$this->label    = 'Groupz';
		$this->pre_meta = '_groupz_meta_';
		$this->extend   = new stdClass();
	}

	/**
	 * Include required files
	 *
	 * @since 0.1
	 */
	private function setup_requires(){

		/** Core *********************************************************/
		
		require( $this->includes_dir . 'core/actions.php'       );
		require( $this->includes_dir . 'core/capabilities.php'  );
		require( $this->includes_dir . 'core/core.php'          );
		require( $this->includes_dir . 'core/extend.php'        );
		require( $this->includes_dir . 'core/functions.php'     );
		require( $this->includes_dir . 'core/helpers.php'       );

		/** Posts ********************************************************/

		// require( $this->includes_dir . 'posts/access.php'       );
		// require( $this->includes_dir . 'posts/actions.php'      );
		// require( $this->includes_dir . 'posts/admin.php'        );
		// require( $this->includes_dir . 'posts/capabilities.php' );
		// require( $this->includes_dir . 'posts/functions.php'    );

		/** Users ********************************************************/
		
		require( $this->includes_dir . 'users/functions.php'    );

		/** Admin ********************************************************/
		
		if ( is_admin() ){
			require( $this->includes_dir . 'admin/admin.php'    );
		}

	}

	/**
	 * Setup default actions and filters
	 *
	 * @since 0.1
	 */
	private function setup_actions(){

		/** Plugin *******************************************************/

		add_action( 'activate_'   . $this->basename, 'groupz_activation'   );
		add_action( 'deactivate_' . $this->basename, 'groupz_deactivation' );
		add_action( 'uninstall_'  . $this->basename, 'groupz_uninstall'    );

		/** Groups *******************************************************/

		add_action( 'groupz_init', array( $this, 'load_textdomain'         ) );
		add_action( 'groupz_init', array( $this, 'register_group_taxonomy' ) );
	}

	/** Functions ****************************************************/

	/**
	 * Load the translation file for current language. Checks the languages
	 * folder inside the Groupz plugin first, and then the default WordPress
	 * languages folder.
	 *
	 * Note that custom translation files inside the Groupz plugin folder
	 * will be removed on Groupz updates. If you're creating custom
	 * translation files, please use the global language folder.
	 *
	 * @since 0.1
	 *
	 * @uses apply_filters() Calls 'groupz_locale' with the
	 *                        {@link get_locale()} value
	 * @uses load_textdomain() To load the textdomain
	 * @return bool True on success, false on failure
	 */
	public function load_textdomain() {

		// Traditional WordPress plugin locale filter
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		// Setup paths to current locale file
		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/groupz/' . $mofile;

		// Look in global /wp-content/languages/groupz folder
		if ( file_exists( $mofile_global ) ) {
			return load_textdomain( $this->domain, $mofile_global );

		// Look in local /wp-content/plugins/groupz/languages/ folder
		} elseif ( file_exists( $mofile_local ) ) {
			return load_textdomain( $this->domain, $mofile_local );
		}

		// Nothing found
		return false;
	}

	/** Taxonomy *****************************************************/

	/**
	 * Register the group taxonomy
	 *
	 * @since 0.1
	 * 
	 * @uses register_taxonomy()
	 */
	public function register_group_taxonomy(){

		// Custom labels
		$labels = array(
			'name'              => __('Groups',         'groupz'),
			'singular_name'     => __('Group',          'groupz'),
			'search_items'      => __('Search Groups',  'groupz'),
			'all_items'         => __('All Groups',     'groupz'),
			'parent_item'       => __('Parent Group',   'groupz'),
			'parent_item_colon' => __('Parent Group:',  'groupz'),
			'edit_item'         => __('Edit Group',     'groupz'),
			'view_item'         => __('View Group',     'groupz'),
			'update_item'       => __('Update Group',   'groupz'),
			'add_new_item'      => __('Add New Group',  'groupz'),
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
			'', // Orphan taxonomy
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
 * @return object The Groupz Instance
 */
function groupz(){
	return Groupz::instance();
}

// Do the magic
groupz();

endif; // class_exists
