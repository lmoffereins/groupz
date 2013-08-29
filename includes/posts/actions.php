<?php

/**
 * Groupz Post & Edit Actions
 * 
 * @package Groupz
 * @subpackage Posts
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Access *******************************************************/

add_action( 'groupz_init',              'groupz_access'                  );

/** Admin ********************************************************/

add_action( 'groupz_admin_init',        'groupz_posts_admin'             );

// Settings
add_filter( 'groupz_settings_sections', 'groupz_posts_settings_sections' );
add_filter( 'groupz_settings_fields',   'groupz_posts_settings_fields'   );

/** Edit Groups **************************************************/

add_action( 'groupz_group_params',            'groupz_posts_param_is_edit'  );
add_action( 'groupz_filter_group_properties', 'groupz_posts_filter_is_edit' );

/** Post Groups **************************************************/

add_action( 'added_term_relationship',    'groupz_added_post_group',    10, 2 );
add_action( 'deleted_term_relationships', 'groupz_removed_post_groups', 10, 2 );

