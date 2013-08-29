<?php

/**
 * Groupz Admin Settings 
 * 
 * @package Groupz
 * @subpackage Administration
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Return all groupz settings sections
 *
 * @since 0.1
 * 
 * @return array The sections as an array of section_id => $args
 */
function groupz_get_settings_sections() {
	return apply_filters( 'groupz_settings_sections', array(

		/** Main Settings ************************************************/

		'groupz_main_section' => array(
			'title'    => __('Main Settings', 'groupz'),
			'callback' => 'groupz_main_section_info',
			'page'     => 'groupz'
		)
	) );
}

/**
 * Return all groupz settings fields
 *
 * @since 0.1
 * 
 * @return array The fields as an array of field_id => $args
 */
function groupz_get_settings_fields() {
	return apply_filters( 'groupz_settings_fields', array( 

		/** Main Settings ************************************************/

		// Whether to enable chosen.js for select groups
		'groupz_use_chosen' => array(
			'title'    => __('Use fancy select box', 'groupz'),
			'callback' => 'groupz_settings_field_use_chosen',
			'sanitize' => 'intval',
			'page'     => 'groupz',
			'section'  => 'groupz_main_section'
		),

		// Whether to show group parent in dropdown
		'groupz_dropdown_show_parent' => array(
			'title'    => __('Show parent in dropdown', 'groupz'),
			'callback' => 'groupz_settings_field_dropdown_show_parent',
			'sanitize' => 'intval',
			'page'     => 'groupz',
			'section'  => 'groupz_main_section'
		)
	) );
}

/**
 * Delete all settings
 * 
 * @since 0.1
 *
 * @uses Groupz_Settings_Admin::get_settings_fields()
 */
function groupz_remove_settings() {
	foreach ( groupz_get_settings_fields() as $field_id => $args ){
		delete_option( $field_id );
	}
}

/** Sections *****************************************************/

/**
 * Output the lead information for main section
 * 
 * @since 0.1
 */
function groupz_main_section_info() {
	?>
		<p>
			<?php printf( __('The main Groupz settings. If you are ready to start using groups, <a href="%s">go there!</a>', 'groupz'), groupz_get_admin_page_url() ); ?>
		</p>
	<?php
}

/** Fields *******************************************************/

/**
 * Output the field for the setting to use chosen.js
 *
 * @since 0.1
 */
function groupz_settings_field_use_chosen() {
	?>
		<input name="groupz_use_chosen" type="checkbox" id="groupz_use_chosen" value="1" <?php checked( get_option( 'groupz_use_chosen' ) ); ?> />
		<label for="groupz_use_chosen"><span class="description"><?php printf( __('Generate fancy select boxes with <a href="%s">chosen.js</a> instead of multiple checkboxes when selecting groups.', 'groupz'), 'http://harvesthq.github.com/chosen/' ); ?></span></label>
	<?php
}

/**
 * Output the field for the setting to show group parents in dropdown
 *
 * @since 0.1
 */
function groupz_settings_field_dropdown_show_parent() {
	?>
		<input name="groupz_dropdown_show_parent" type="checkbox" id="groupz_dropdown_show_parent" value="1" <?php checked( get_option( 'groupz_dropdown_show_parent' ) ); ?> />
		<label for="groupz_dropdown_show_parent"><span class="description"><?php _e('Adds for each child group it\'s parent in the group select dropdown.', 'groupz'); ?></span></label>
	<?php
}

