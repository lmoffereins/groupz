<?php

/**
 * Groupz Posts Hooks
 * 
 * @package Groupz
 * @subpackage Posts
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

/** Edit Groups **************************************************/

add_action( 'groupz_group_params',            'groupz_posts_is_edit_group_param'     );
add_action( 'groupz_filter_group_properties', 'groupz_posts_is_edit_filter_property' );

/** Post Groups **************************************************/

add_action( 'added_term_relationship',    'groupz_added_post_group',    10, 2 );
add_action( 'deleted_term_relationships', 'groupz_removed_post_groups', 10, 2 );

/** Access *******************************************************/

add_action( 'plugins_loaded', 'groupz_access' );

