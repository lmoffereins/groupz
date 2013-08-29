<?php

/**
 * Groupz Admin Post & Edit Settings 
 * 
 * @package Groupz
 * @subpackage Administration
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Add post and edit settings sections
 * 
 * @since 0.x
 * 
 * @param array $sections Admin settings sections
 * @return array $sections
 */
function groupz_posts_settings_sections( $sections ) {
	return array_merge( $sections, array(

		/** Read Settings ************************************************/
	
		'groupz_read_section' => array(
			'title'    => __('Read Settings', 'groupz'),
			'callback' => 'groupz_read_section_info',
			'page'     => 'groupz'
		),
	
		/** Edit Settings ************************************************/
	
		'groupz_edit_section' => array(
			'title'    => __('Edit Settings', 'groupz'),
			'callback' => 'groupz_edit_section_info',
			'page'     => 'groupz'
		)
	) );
}

/**
 * Add settings fields for post and edit sections
 *
 * @since 0.x
 * 
 * @param array $fields Admin settings fields
 * @return array $fields
 */
function groupz_posts_settings_fields( $fields ) {
	return array_merge( $fields, array(

		/** Read Settings ************************************************/

		// Whether to use read options for which post types
		'groupz_read_post_types' => array(
			'title'    => __('Use read privileges', 'groupz'),
			'callback' => 'groupz_settings_field_read_post_types',
			'sanitize' => 'groupz_sanitize_post_types',
			'page'     => 'groupz',
			'section'  => 'groupz_read_section'
		),

		// Whether to propagate group privileges
		'groupz_propagate' => array(
			'title'    => __('Propagate groups', 'groupz'),
			'callback' => 'groupz_settings_field_propagate',
			'sanitize' => 'intval',
			'page'     => 'groupz',
			'section'  => 'groupz_read_section'
		),

		// Whether to render groupless posts private
		// @todo Fix revert to private
		// 'groupz_set_private' => array(
		// 	'title'    => __('Revert items to private', 'groupz'),
		// 	'callback' => 'groupz_settings_field_set_private',
		// 	'sanitize' => 'intval',
		// 	'page'     => 'groupz',
		// 	'section'  => 'groupz_read_section'
		// ),

		// The symbol to mark the group assigned posts
		'groupz_post_marking' => array(
			'title'    => __('Post marking symbol', 'groupz'),
			'callback' => 'groupz_settings_field_post_marking',
			'sanitize' => 'sanitize_text_field',
			'page'     => 'groupz',
			'section'  => 'groupz_read_section'
		),

		/** Edit Settings ************************************************/

		// Whether to use edit options for which post types
		'groupz_edit_post_types' => array(
			'title'    => __('Use edit privileges', 'groupz'),
			'callback' => 'groupz_settings_field_edit_post_types',
			'sanitize' => 'groupz_sanitize_post_types',
			'page'     => 'groupz',
			'section'  => 'groupz_edit_section'
		)
	) );
}

/** Sections *****************************************************/

/**
 * Output the lead information for read section
 *
 * @since 0.1
 */
function groupz_read_section_info() {
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
 *
 * @since 0.1
 */
function groupz_edit_section_info() {
	?>
		<p>
			<?php _e('Setup your edit privileges and additional options.', 'groupz'); ?>
		</p>	
	<?php
}

/** Fields *******************************************************/

/**
 * Output the field for the setting to enable read privileges
 *
 * @since 0.1
 */
function groupz_settings_field_read_post_types() {
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
 * Output the field for the setting to propagate group privileges
 *
 * @since 0.1
 */
function groupz_settings_field_propagate() {
	?>
		<input name="groupz_propagate" type="checkbox" id="groupz_propagate" value="1" <?php checked( get_option( 'groupz_propagate' ) ); ?> />
		<label for="groupz_propagate"><span class="description"><?php _e('Whether to propagate group read privileges to child posts. Recommended. Groupz has no option to manage propagation on a per-group basis. NOTE: This can get heavy when your post has many children (bbPress forums for example).', 'groupz'); ?></span></label>
	<?php
}

/**
 * Output the field for the setting to render posts private
 * 
 * @since 0.1
 */
function groupz_settings_field_set_private() {
	?>
		<input disabled name="groupz_set_private" type="checkbox" id="groupz_set_private" value="1" <?php checked( get_option( 'groupz_set_private' ) ); ?> />
		<label for="groupz_set_private"><span class="description"><?php _e( 'On group deletion, set the associated items visibility to private to prevent all items being disclosed for all users.', 'groupz' ); ?></span> Not active yet.</label>
	<?php
}

/**
 * Output the field for the setting to add a post marking
 *
 * @since 0.1
 */
function groupz_settings_field_post_marking() {
	?>
		<input name="groupz_post_marking" type="text" id="groupz_post_marking" value="<?php echo get_option( 'groupz_post_marking', '' ); ?>" class="small-text" /><br/>
		<label for="groupz_post_marking"><span class="description"><?php _e( 'Set a symbol like * to mark your group assigned posts. For admins and editors only. Set empty to disable.', 'groupz' ); ?></span></label>
	<?php
}

/**
 * Output the field for the setting to enable edit privileges
 *
 * @since 0.1
 */
function groupz_settings_field_edit_post_types() {
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
 * @since 0.1
 * 
 * @param mixed $input
 * @return array $input
 */
function groupz_sanitize_post_types( $input ) {

	if ( ! isset( $input ) || empty( $input ) || ! is_array( $input ) )
		$input = array();

	return $input;
}
