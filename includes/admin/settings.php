<?php

/**
 * Groupz Admin Settings 
 * 
 * @package Groupz
 * @subpackage Admin
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

if ( !class_exists( 'Groupz_Admin_Settings' ) ) :

/**
 * Plugin class
 */
class Groupz_Admin_Settings {

	/**
	 * Return all groupz settings sections
	 * @return array The sections as an array of section_id => $args
	 */
	public function get_settings_sections(){
		return apply_filters( 'groupz_settings_sections', array(

			/** Main Settings ************************************************/

			'groupz_main_section' => array(
				'title'    => __('Main Settings', 'groupz'),
				'callback' => array( $this, 'main_section_info' ),
				'page'     => 'groupz'
				),

			/** Display Settings *********************************************/

			'groupz_display_section' => array(
				'title'    => __('Display Settings', 'groupz'),
				'callback' => array( $this, 'display_section_info' ),
				'page'     => 'groupz'
				),

			/** Read Settings ************************************************/
		
			'groupz_read_section' => array(
				'title'    => __('Read Settings', 'groupz'),
				'callback' => array( $this, 'read_section_info' ),
				'page'     => 'groupz'
				),
		
			/** Edit Settings ************************************************/
		
			'groupz_edit_section' => array(
				'title'    => __('Edit Settings', 'groupz'),
				'callback' => array( $this, 'edit_section_info' ),
				'page'     => 'groupz'
				)

			) );
	}

	/**
	 * Return all groupz settings fields
	 * 
	 * @return array The fields as an array of field_id => $args
	 */
	public function get_settings_fields(){
		return apply_filters( 'groupz_settings_fields', array( 

			/** Main Settings ************************************************/

			// Whether to propagate group privileges
			'groupz_propagate' => array(
				'title'    => __('Propagate groups', 'groupz'),
				'callback' => array( $this, 'settings_field_propagate' ),
				'sanitize' => 'intval',
				'page'     => 'groupz',
				'section'  => 'groupz_main_section'
				),
			
			/** Display Settings *********************************************/

			// Whether to enable chosen.js for select groups
			'groupz_use_chosen' => array(
				'title'    => __('Use fancy select box', 'groupz'),
				'callback' => array( $this, 'settings_field_use_chosen' ),
				'sanitize' => 'intval',
				'page'     => 'groupz',
				'section'  => 'groupz_display_section'
				),

			// Whether to show group parent in dropdown
			'groupz_dropdown_show_parent' => array(
				'title'    => __('Show parent in dropdown', 'groupz'),
				'callback' => array( $this, 'settings_field_dropdown_show_parent' ),
				'sanitize' => 'intval',
				'page'     => 'groupz',
				'section'  => 'groupz_display_section'
				),

			/** Read Settings ************************************************/

			// Whether to use read options for which post types
			'groupz_read_post_types' => array(
				'title'    => __('Use read privileges', 'groupz'),
				'callback' => array( $this, 'settings_field_read_post_types' ),
				'sanitize' => array( $this, 'sanitize_post_types' ),
				'page'     => 'groupz',
				'section'  => 'groupz_read_section'
				),

			// Whether to render groupless posts private
			// @todo Fix revert to private
			// 'groupz_set_private' => array(
			// 	'title'    => __('Revert items to private', 'groupz'),
			// 	'callback' => array( $this, 'settings_field_set_private' ),
			// 	'sanitize' => 'intval',
			// 	'page'     => 'groupz',
			// 	'section'  => 'groupz_read_section'
			// 	),

			// The symbol to mark the group assigned posts
			'groupz_post_marking' => array(
				'title'    => __('Post marking symbol', 'groupz'),
				'callback' => array( $this, 'settings_field_post_marking' ),
				'sanitize' => 'sanitize_text_field',
				'page'     => 'groupz',
				'section'  => 'groupz_read_section'
				),

			/** Edit Settings ************************************************/

			// Whether to use edit options for which post types
			'groupz_edit_post_types' => array(
				'title'    => __('Use edit privileges', 'groupz'),
				'callback' => array( $this, 'settings_field_edit_post_types' ),
				'sanitize' => array( $this, 'sanitize_post_types' ),
				'page'     => 'groupz',
				'section'  => 'groupz_edit_section'
				)

			) );
	}

	/**
	 * Setup Groups Settings
	 */
	public function __construct(){
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		add_action( 'groupz_uninstall', array( $this, 'remove_settings' ) );
	}

	/**
	 * Register all plugin settings
	 * 
	 * @uses self::get_settings_sections() To fetch all the sections to render
	 * @uses self::get_settings_fields() To fetch all the fields to render
	 */
	public function register_settings(){

		// Register all sections
		foreach ( $this->get_settings_sections() as $section_id => $args ){
			add_settings_section( $section_id, $args['title'], $args['callback'], $args['page'] );
		}

		// Register all fields
		foreach ( $this->get_settings_fields() as $field_id => $args ){
			add_settings_field( $field_id, $args['title'], $args['callback'], $args['page'], $args['section'] );
			register_setting( $args['page'], $field_id, $args['sanitize'] );
		}
	}

	/**
	 * Delete all settings
	 *
	 * @uses self::get_settings_fields()
	 * @return void
	 */
	public function remove_settings(){
		foreach ( $this->get_settings_fields() as $field_id => $args ){
			delete_option( $field_id );
		}
	}

	/**
	 * Add the settings menu
	 */
	public function admin_menu(){
		add_options_page( __('Groupz Settings', 'groupz'), __('Groupz', 'groupz'), 'manage_options', 'groupz', array( $this, 'admin_page' ) );
	}

	/**
	 * Render the admin page's HTML
	 */
	public function admin_page(){
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

	/** Section infos ************************************************/

	/**
	 * Output the lead information for main section
	 */
	public function main_section_info(){
		?>
			<p>
				<?php printf( __('The main Groupz settings. If you are ready to start using groups, <a href="%s">go there!</a>', 'groupz'), groupz_get_admin_page_url() ); ?>
			</p>
		<?php
	}

	/**
	 * Output the lead information for display section
	 */
	public function display_section_info(){
		// Nothing to note
	}

	/**
	 * Output the lead information for read section
	 */
	public function read_section_info(){
		?>
			<p>
				<?php _e('Setup your read privileges and additional options.', 'groupz'); ?>
			</p>

			<p>
				<?php _e('Only the default front-end post views of custom post types are supported. Unless stated otherwise, Groupz does not come with support for other plugin functionality.', 'groupz'); ?>
			</p>
		<?php
	}

	/**
	 * Output the lead information for edit section
	 */
	public function edit_section_info(){
		echo '<p>'. __('Setup your edit privileges and additional options.', 'groupz') .'</p>';
	}

	/** Field ouputs *************************************************/

	/**
	 * Output the field for the setting to use chosen.js
	 */
	public function settings_field_propagate(){
		?>
			<input name="groupz_propagate" type="checkbox" id="groupz_propagate" value="1" <?php checked( get_option( 'groupz_propagate' ) ); ?> />
			<label for="groupz_propagate"><span class="description"><?php _e('Whether to propagate group read privileges to child posts. Recommended. Groupz has no option to manage propagation on a per-group basis. NOTE: This can get heavy when your post has many children (bbPress forums for example).', 'groupz'); ?></span></label>
		<?php
	}

	/**
	 * Output the field for the setting to use chosen.js
	 */
	public function settings_field_use_chosen(){
		?>
			<input name="groupz_use_chosen" type="checkbox" id="groupz_use_chosen" value="1" <?php checked( get_option( 'groupz_use_chosen' ) ); ?> />
			<label for="groupz_use_chosen"><span class="description"><?php printf( __('Generate fancy select boxes with <a href="%s">chosen.js</a> instead of multiple checkboxes when selecting groups.', 'groupz'), 'http://harvesthq.github.com/chosen/' ); ?></span></label>
		<?php
	}

	/**
	 * Output the field for the setting to show group parents in dropdown
	 */
	public function settings_field_dropdown_show_parent(){
		?>
			<input name="groupz_dropdown_show_parent" type="checkbox" id="groupz_dropdown_show_parent" value="1" <?php checked( get_option( 'groupz_dropdown_show_parent' ) ); ?> />
			<label for="groupz_dropdown_show_parent"><span class="description"><?php _e('Adds for each child group it\'s parent in the group select dropdown.', 'groupz'); ?></span></label>
		<?php
	}

	/**
	 * Output the field for the setting to enable read privileges
	 */
	public function settings_field_read_post_types(){
		$option = get_option( 'groupz_read_post_types', array() ); ?>

			<p>
				<?php _e('If you want to enable read privileges for certain post types, you need to select them here first.', 'groupz'); ?>
				<?php _e('Unsupported post types are disabled.', 'groupz'); ?>
				<?php _e('<strong>NOTE:</strong> Unselecting a post type disables the read privilege functionality for that post type, so all items will be disclosed for all users. The saved group data however will not be removed.', 'groupz'); ?>
			</p>

			<?php foreach ( get_post_types( array(), 'objects' ) as $post_type ) : ?>

				<?php $disabled = in_array( $post_type->name, groupz()->get_unsupported_post_types() ) ? ' disabled="disabled"' : ''; ?>

				<input name="groupz_read_post_types[]" type="checkbox" id="groupz_read_post_types_<?php echo $post_type->name; ?>" value="<?php echo $post_type->name; ?>" <?php checked( in_array( $post_type->name, $option ) ); echo $disabled; ?> />
				<label for="groupz_read_post_types_<?php echo $post_type->name; ?>"><span class="description"><?php echo $post_type->label; ?></span></label><br/>

			<?php endforeach; ?>
		<?php
	}

	/**
	 * Output the field for the setting to render posts private
	 */
	public function settings_field_set_private(){
		?>
			<input disabled name="groupz_set_private" type="checkbox" id="groupz_set_private" value="1" <?php checked( get_option( 'groupz_set_private' ) ); ?> />
			<label for="groupz_set_private"><span class="description"><?php _e( 'On group deletion, set the associated items visibility to private to prevent all items being disclosed for all users.', 'groupz' ); ?></span> Not active yet.</label>
		<?php
	}

	/**
	 * Output the field for the setting to add a post marking
	 */
	public function settings_field_post_marking(){
		?>
			<input name="groupz_post_marking" type="text" id="groupz_post_marking" value="<?php echo get_option( 'groupz_post_marking', '' ); ?>" class="small-text" /><br/>
			<label for="groupz_post_marking"><span class="description"><?php _e( 'Set a symbol like * to mark your group assigned posts. For admins and editors only. Set empty to disable.', 'groupz' ); ?></span></label>
		<?php
	}

	/**
	 * Output the field for the setting to enable edit privileges
	 */
	public function settings_field_edit_post_types(){
		$option = get_option( 'groupz_edit_post_types', array() ); ?>

			<p>
				<?php _e('If you want to enable edit privileges for certain post types, you need to select them here first.', 'groupz'); ?>
				<?php _e('Unsupported post types are disabled.', 'groupz'); ?>
			</p>

			<?php foreach ( get_post_types( array(), 'objects' ) as $post_type ) : ?>

				<?php $disabled = in_array( $post_type->name, groupz()->get_unsupported_post_types() ) ? ' disabled="disabled"' : ''; ?>

				<input name="groupz_edit_post_types[]" type="checkbox" id="groupz_edit_post_types_<?php echo $post_type->name; ?>" value="<?php echo $post_type->name; ?>" <?php checked( in_array( $post_type->name, $option ) ); echo $disabled; ?> />
				<label for="groupz_edit_post_types_<?php echo $post_type->name; ?>"><span class="description"><?php echo $post_type->label; ?></span></label><br/>

			<?php endforeach; ?>
		<?php
	}

	/** Sanitization *************************************************/

	/**
	 * Return post types input sanitized
	 *
	 * Required to return empty array if no types are selected
	 * 
	 * @param mixed $input
	 * @return array $input
	 */
	public function sanitize_post_types( $input ){

		if ( !isset( $input ) || empty( $input ) || !is_array( $input ) )
			$input = array();

		return $input;
	}

}

new Groupz_Admin_Settings();

endif; // class_exists

