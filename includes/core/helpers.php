<?php

/** 
 * Groupz Helper functions
 *
 * This file contains a manipulated copy of the dropdown and
 * list functions in includes/category-template.php. It is 
 * inserted to support the necessary dropdown and list functions 
 * for our custom taxonomy.
 * 
 * Ideally there would exist wp_dropdown_terms() and wp_list_terms()
 * functions that can be tailored to our custom taxonomy, but they 
 * don't. When they do, and that time will come, the wrapper functions 
 * at the end of this file will suffice to return the requested 
 * dropdowns and lists.
 *
 * @link http://core.trac.wordpress.org/ticket/19780  Named category functions/docs need cleaning up
 *
 * @package Groupz
 * @subpackage Core
 */

/**
 * Display or retrieve the HTML checkbox list of groups.
 *
 * For the list of arguments see {@see list_groups()}
 * 
 * @uses add_filter() To call 'groupz_checkbox_groups' on the
 *                     'list_groups' filter
 * @uses list_groups()
 * 
 * @param array $args Optional. Override default arguments.
 * @return string HTML content only if 'echo' argument is 0
 */
function checkbox_groups( $args = array() ) {
	$defaults = array(
		'class' => 'checkbox_groups list_groups', 
		'echo' => 0, 'selected' => 0
		);
	$args = wp_parse_args( $args, $defaults );

	// Force selected as array
	if ( !is_array( $args['selected'] ) )
		$args['selected'] = (array) $args['selected'];

	// Handle echoing
	if ( $args['echo'] ){
		$echo = true;
		$args['echo'] = false;
	} else {
		$echo = false;
	}

	add_filter( 'list_groups', 'groupz_checkbox_groups', 10, 3 );
	$list = list_groups( $args );
	remove_filter( 'list_groups', 'groupz_checkbox_groups' );

	if ( $echo )
		echo $list;
	else
		return $list;
}

/**
 * Filter 'list_groups' to return the group name with a checkbox
 * 
 * @param string $group_name The group name
 * @param object $group The current term object
 * @param array $args List groups arguments
 * @return string $content The group checkbox
 */
function groupz_checkbox_groups( $group_name, $group, $args ){

	// Create unique input ID
	$id = substr( $args['name'], 0, -2 ) .'_'. $group->term_id;

	// Setup content string
	$content = "<input name='{$args['name']}' type='checkbox' id='$id' ". checked( in_array( $group->term_id, $args['selected'] ), true, false ) ." value='{$group->term_id}' /> <label for='$id'>$group_name</label>";

	return $content;
}


/**
 * Display or retrieve the HTML dropdown list of groups.
 *
 * The list of arguments is below:
 *     'show_option_all' (string) - Text to display for showing all groups.
 *     'show_option_none' (string) - Text to display for showing no groups.
 *     'orderby' (string) default is 'ID' - What column to use for ordering the
 * groups.
 *     'order' (string) default is 'ASC' - What direction to order groups.
 *     'show_last_update' (bool|int) default is 0 - See {@link get_groups()}
 *     'show_count' (bool|int) default is 0 - Whether to show how many posts are
 * in the group.
 *     'hide_empty' (bool|int) default is 1 - Whether to hide groups that
 * don't have any posts attached to them.
 *     'child_of' (int) default is 0 - See {@link get_groups()}.
 *     'exclude' (string) - See {@link get_groups()}.
 *     'echo' (bool|int) default is 1 - Whether to display or retrieve content.
 *     'depth' (int) - The max depth.
 *     'tab_index' (int) - Tab index for select element.
 *     'name' (string) - The name attribute value for select element.
 *     'id' (string) - The ID attribute value for select element. Defaults to name if omitted.
 *     'class' (string) - The class attribute value for select element.
 *     'selected' (int) - Which group ID is or are selected.
 *     'taxonomy' (string) - The name of the taxonomy to retrieve. Defaults to group.
 *
 * The 'hierarchical' argument, which is disabled by default, will override the
 * depth argument, unless it is true. When the argument is false, it will
 * display all of the groups. When it is enabled it will use the value in
 * the 'depth' argument.
 *
 * @param string|array $args Optional. Override default arguments.
 * @return string HTML content only if 'echo' argument is 0.
 */
function dropdown_groups( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => '',
		'orderby' => 'id', 'order' => 'ASC',
		'show_last_update' => 0, 'show_count' => 0,
		'hide_empty' => 0, 'child_of' => 0,
		'exclude' => '', 'echo' => 1,
		'selected' => 0, 'hierarchical' => 0,
		'name' => 'dropdown_groups', 'id' => '',
		'class' => 'postform', 'depth' => 0,
		'tab_index' => 0, 'taxonomy' => groupz_get_group_tax_id(), 
		'multiple' => 0, 'hide_if_empty' => false
	);

	$r = wp_parse_args( $args, $defaults );

	if ( !isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] ) {
		$r['pad_counts'] = true;
	}

	$r['include_last_update_time'] = $r['show_last_update'];
	extract( $r );

	$tab_index_attribute = '';
	if ( (int) $tab_index > 0 )
		$tab_index_attribute = " tabindex=\"$tab_index\"";

	$multiple_select = '';
	if ( $multiple )
		$multiple_select = ' multiple';

	$groups = get_terms( $taxonomy, $r );
	$name = esc_attr( $name );
	$class = esc_attr( $class );
	$id = $id ? esc_attr( $id ) : $name;

	if ( !isset( $style ) )
		$style = '';

	if ( ! $r['hide_if_empty'] || ! empty($groups) )
		$output = "<select name='$name' id='$id' class='$class' $multiple_select $tab_index_attribute $style>\n";
	else
		$output = '';

	if ( empty($groups) && ! $r['hide_if_empty'] && !empty($show_option_none) ) {
		$show_option_none = apply_filters( 'list_groups', $show_option_none );
		$output .= "\t<option value='-1' selected='selected'>$show_option_none</option>\n";
	}

	if ( ! empty( $groups ) ) {

		if ( $show_option_all ) {
			$show_option_all = apply_filters( 'list_groups', $show_option_all );
			$selected = ( '0' === strval($r['selected']) ) ? " selected='selected'" : '';
			$output .= "\t<option value='0'$selected>$show_option_all</option>\n";
		}

		if ( $show_option_none ) {
			$show_option_none = apply_filters( 'list_groups', $show_option_none );
			$selected = ( '-1' === strval($r['selected']) ) ? " selected='selected'" : '';
			$output .= "\t<option value='-1'$selected>$show_option_none</option>\n";
		}

		if ( $hierarchical )
			$depth = $r['depth'];  // Walk the full depth.
		else
			$depth = -1; // Flat.

		$output .= groupz_walk_group_dropdown_tree( $groups, $depth, $r );
	}
	if ( ! $r['hide_if_empty'] || ! empty($groups) )
		$output .= "</select>\n";


	$output = apply_filters( 'groupz_dropdown_groups', $output );

	if ( $echo )
		echo $output;

	return $output;
}

/**
 * Display or retrieve the HTML list of groups.
 *
 * The list of arguments is below:
 *     'show_option_all' (string) - Text to display for showing all groups.
 *     'orderby' (string) default is 'ID' - What column to use for ordering the
 * groups.
 *     'order' (string) default is 'ASC' - What direction to order groups.
 *     'show_last_update' (bool|int) default is 0 - See {@link
 * walk_group_dropdown_tree()}
 *     'show_count' (bool|int) default is 0 - Whether to show how many posts are
 * in the group.
 *     'hide_empty' (bool|int) default is 1 - Whether to hide groups that
 * don't have any posts attached to them.
 *     'use_desc_for_title' (bool|int) default is 1 - Whether to use the
 * description instead of the group title.
 *     'feed' - See {@link get_groups()}.
 *     'feed_type' - See {@link get_groups()}.
 *     'feed_image' - See {@link get_groups()}.
 *     'child_of' (int) default is 0 - See {@link get_groups()}.
 *     'exclude' (string) - See {@link get_groups()}.
 *     'exclude_tree' (string) - See {@link get_groups()}.
 *     'echo' (bool|int) default is 1 - Whether to display or retrieve content.
 *     'current_group' (int) - See {@link get_groups()}.
 *     'hierarchical' (bool) - See {@link get_groups()}.
 *     'title_li' (string) - See {@link get_groups()}.
 *     'depth' (int) - The max depth.
 *
 * @param string|array $args Optional. Override default arguments.
 * @return string HTML content only if 'echo' argument is 0.
 */
function list_groups( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => __('No groups', 'groupz'),
		'orderby' => 'name', 'order' => 'ASC',
		'show_last_update' => 0, 'style' => 'list',
		'show_count' => 0, 'hide_empty' => 0, 'class' => 'list_groups',
		'use_desc_for_title' => 1, 'child_of' => 0,
		'feed' => '', 'feed_type' => '',
		'feed_image' => '', 'exclude' => '',
		'exclude_tree' => '', 'current_group' => 0,
		'hierarchical' => 1, 'echo' => 1, 'depth' => 0, 
		'taxonomy' => groupz_get_group_tax_id()
	);

	$r = wp_parse_args( $args, $defaults );

	if ( !isset( $r['pad_counts'] ) && $r['show_count'] && $r['hierarchical'] )
		$r['pad_counts'] = true;

	if ( isset( $r['show_date'] ) )
		$r['include_last_update_time'] = $r['show_date'];

	if ( true == $r['hierarchical'] ) {
		$r['exclude_tree'] = $r['exclude'];
		$r['exclude'] = '';
	}

	if ( !isset( $r['class'] ) )
		$r['class'] = ( 'group' == $r['taxonomy'] ) ? 'groups' : $r['taxonomy'];

	extract( $r );

	if ( !taxonomy_exists($taxonomy) )
		return false;

	$groups = get_groups( $r );

	$output = '';
	if ( 'list' == $style )
			$output = "<ul class='$class'>";

	if ( empty( $groups ) ) {
		if ( ! empty( $show_option_none ) ) {
			if ( 'list' == $style )
				$output .= '<li>' . $show_option_none . '</li>';
			else
				$output .= $show_option_none;
		}
	} else {
		if ( ! empty( $show_option_all ) ) {
			$edit_link = sprintf( '<a href="%s">$show_option_all</a>', esc_url( admin_url( sprintf( 'edit_tags.php?taxonomy=%s', groupz_get_group_tax_id() ) ) ) );
			$edit_link = apply_filters( 'list_groups', $edit_link );
			if ( 'list' == $style )
				$output .= "<li>$edit_link</li>";
			else
				$output .= $edit_link;
		}

		// if ( empty( $r['current_group'] ) && ( groupz_is_group() || is_tax() || is_tag() ) ) {
		// 	$current_term_object = get_queried_object();
		// 	if ( $r['taxonomy'] == $current_term_object->taxonomy )
		// 		$r['current_group'] = get_queried_object_id();
		// }

		if ( $hierarchical )
			$depth = $r['depth'];
		else
			$depth = -1; // Flat.

		$output .= groupz_walk_group_tree( $groups, $depth, $r );
	}

	if ( 'list' == $style )
		$output .= '</ul>';

	$output = apply_filters( 'groupz_list_groups', $output, $args );

	if ( $echo )
		echo $output;
	else
		return $output;
}

/**
 * Retrieve HTML list content for group list.
 *
 * @uses Groupz_Walker_Group to create HTML list content.
 * @see Groupz_Walker_Group::walk() for parameters and return description.
 */
function groupz_walk_group_tree() {
	$args = func_get_args();
	// the user's options are the third parameter
	if ( empty($args[2]['walker']) || !is_a($args[2]['walker'], 'Walker') )
		$walker = new Groupz_Walker_Group;
	else
		$walker = $args[2]['walker'];

	return call_user_func_array(array( &$walker, 'walk' ), $args );
}

/**
 * Retrieve HTML dropdown (select) content for group list.
 *
 * @uses Groupz_Walker_GroupDropdown to create HTML dropdown content.
 * @see Groupz_Walker_GroupDropdown::walk() for parameters and return description.
 */
function groupz_walk_group_dropdown_tree() {
	$args = func_get_args();
	// the user's options are the third parameter
	if ( empty($args[2]['walker']) || !is_a($args[2]['walker'], 'Walker') )
		$walker = new Groupz_Walker_GroupDropdown;
	else
		$walker = $args[2]['walker'];

	return call_user_func_array(array( &$walker, 'walk' ), $args );
}

/**
 * Create HTML list of groups.
 *
 * @uses Walker
 */
class Groupz_Walker_Group extends Walker {

	/**
	 * @see Walker::$tree_type
	 * @var string
	 */
	var $tree_type = 'group';

	/**
	 * @see Walker::$db_fields
	 * @var array
	 */
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

	/**
	 * @see Walker::start_lvl()
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of group. Used for tab indentation.
	 * @param array $args Will only append content if style argument value is 'list'.
	 */
	function start_lvl(&$output, $depth, $args) {
		if ( 'list' != $args['style'] )
			return;

		$indent = str_repeat("\t", $depth);
		$output .= "<ul class='children'>\n";
	}

	/**
	 * @see Walker::end_lvl()
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param int $depth Depth of group. Used for tab indentation.
	 * @param array $args Will only append content if style argument value is 'list'.
	 */
	function end_lvl(&$output, $depth, $args) {
		if ( 'list' != $args['style'] )
			return;

		$indent = str_repeat("\t", $depth);
		$output .= "$indent</ul>\n";
	}

	/**
	 * @see Walker::start_el()
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $group Group data object.
	 * @param int $depth Depth of group in reference to parents.
	 * @param array $args
	 */
	function start_el(&$output, $group, $depth, $args) {
		extract($args);

		$pad = str_repeat("&nbsp;", $depth * 3);

		$group_name = esc_attr( $group->name );
		$group_name = apply_filters('list_groups', $group_name, $group, $args );
		$link = $pad.$group_name;

		if ( !empty($show_count) )
			$link .= ' (' . intval($group->count) . ')';

		if ( !empty($show_date) )
			$link .= ' ' . gmdate('Y-m-d', $group->last_update_timestamp);

		if ( 'list' == $args['style'] ) {
			$output .= "\t<li";
			$class = 'group-item group-item-' . $group->term_id;
			// if ( !empty($current_group) ) {
			// 	$_current_group = get_term( $current_group, $group->taxonomy );
			// 	if ( $group->term_id == $current_group )
			// 		$class .=  ' current-group';
			// 	elseif ( $group->term_id == $_current_group->parent )
			// 		$class .=  ' current-group-parent';
			// }
			$output .=  ' class="' . $class . '"';
			$output .= ">$link\n";
		} else {
			$output .= "\t$link<br />\n";
		}
	}

	/**
	 * @see Walker::end_el()
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $page Not used.
	 * @param int $depth Depth of group. Not used.
	 * @param array $args Only uses 'list' for whether should append to output.
	 */
	function end_el(&$output, $page, $depth, $args) {
		if ( 'list' != $args['style'] )
			return;

		$output .= "</li>\n";
	}

}

/**
 * Create HTML dropdown list of groups.
 * 
 * @uses Walker
 */
class Groupz_Walker_GroupDropdown extends Walker {

	/**
	 * @see Walker::$tree_type
	 * @var string
	 */
	var $tree_type = 'group';

	/**
	 * @see Walker::$db_fields
	 * @var array
	 */
	var $db_fields = array ('parent' => 'parent', 'id' => 'term_id');

	/**
	 * @see Walker::start_el()
	 *
	 * @param string $output Passed by reference. Used to append additional content.
	 * @param object $group Group data object.
	 * @param int $depth Depth of group. Used for padding.
	 * @param array $args Uses 'selected', 'show_count', and 'show_last_update' keys, if they exist.
	 */
	function start_el(&$output, $group, $depth, $args) {

		$pad = str_repeat("&nbsp;", $depth * 3);

		$group_name = apply_filters('list_groups', $group->name, $group);
		$output .= "\t<option class=\"level-$depth\" value=\"".$group->term_id."\"";
		if ( in_array( $group->term_id, (array) $args['selected'] ) ) // Support multiple select
			$output .= ' selected="selected"';
		$output .= '>';
		$output .= $pad.$group_name;
		if ( $args['show_count'] )
			$output .= '&nbsp;&nbsp;('. $group->count .')';
		if ( $args['show_last_update'] ) {
			$format = 'Y-m-d';
			$output .= '&nbsp;&nbsp;' . gmdate($format, $group->last_update_timestamp);
		}
		$output .= "</option>\n";
	}

}

/**
 * Create dropdown HTML content of users.
 * 
 * Custom args supported
 * - multiple
 * - disabled
 * - custom style (data-placeholder & style=width)
 *
 * @see wp_dropdown_users()
 *
 * @param string|array $args Optional. Override defaults.
 * @return string|null Null on display. String of HTML content on retrieve.
 */
function groupz_dropdown_users( $args = '' ) {
	$defaults = array(
		'show_option_all' => '', 'show_option_none' => '', 'hide_if_only_one_author' => '',
		'orderby' => 'display_name', 'order' => 'ASC',
		'include' => '', 'exclude' => '', 'multi' => 0, 'multiple' => 0,
		'show' => 'display_name', 'echo' => 1, 'disabled' => 0,
		'selected' => 0, 'name' => 'user', 'class' => '', 'id' => '',
		'blog_id' => $GLOBALS['blog_id'], 'who' => '', 'include_selected' => false
	);

	$defaults['selected'] = is_author() ? get_query_var( 'author' ) : 0;

	$r = wp_parse_args( $args, $defaults );
	extract( $r, EXTR_SKIP );

	$query_args = wp_array_slice_assoc( $r, array( 'blog_id', 'include', 'exclude', 'orderby', 'order', 'who' ) );
	$query_args['fields'] = array( 'ID', $show );
	$users = get_users( $query_args );

	$output = '';
	if ( !empty($users) && ( empty($hide_if_only_one_author) || count($users) > 1 ) ) {
		$name = esc_attr( $name );
		if ( $multi && ! $id )
			$id = '';
		else
			$id = $id ? " id='" . esc_attr( $id ) . "'" : " id='$name'";

		$multiple = $multiple ? ' multiple="multiple"' : '';
		$disabled = $disabled ? ' disabled="disabled"' : '';
		$selected = (array) $selected;

		// ! Can do better
		if ( !isset( $style ) )
			$style = '';

		$output = "<select name='{$name}'{$id}{$multiple}{$disabled}{$style} class='$class' >\n";

		if ( $show_option_all )
			$output .= "\t<option value='0'>$show_option_all</option>\n";

		if ( $show_option_none ) {
			$_selected = selected( in_array( -1, $selected ), true, false );
			$output .= "\t<option value='-1'$_selected>$show_option_none</option>\n";
		}

		$found_selected = false;
		foreach ( (array) $users as $user ) {
			$user->ID = (int) $user->ID;
			$_selected = selected( in_array( $user->ID, $selected ), true, false );
			if ( $_selected )
				$found_selected = true;
			$display = !empty($user->$show) ? $user->$show : '('. $user->user_login . ')';
			$output .= "\t<option value='$user->ID'$_selected>" . esc_html($display) . "</option>\n";
		}

		if ( $include_selected && ! $found_selected ) {
			foreach ( $selected as $selected_id ) {
				if ( $selected_id <= 0 )
					continue;
				$user = get_userdata( $selected_id );
				$_selected = selected( $user->ID, $selected_id, false );
				$display = !empty($user->$show) ? $user->$show : '('. $user->user_login . ')';
				$output .= "\t<option value='$user->ID'$_selected>" . esc_html($display) . "</option>\n";
			}
		}

		$output .= "</select>";
	}

	$output = apply_filters('groupz_dropdown_users', $output);

	if ( $echo )
		echo $output;

	return $output;
}

/** Wrapper functions ********************************************/

if ( !function_exists( 'dropdown_groups' ) ) :
/**
 * Return dropdown to select groups
 *
 * Wrapper function for wp_dropdown_categories(). Does
 * not support multiple select.
 * 
 * @param array $args Arguments for wp_dropdown_categories
 * @return string HTML content only if 'echo' argument is 0
 */
function dropdown_groups( $args = array() ){
	$defaults = array(
		'taxonomy' => groupz_get_group_tax_id(), 
		'hide_empty' => 0, 'echo' => 1
		);
	$args = wp_parse_args( $args, $defaults );

	return wp_dropdown_categories( $args ); // Or in the future: wp_dropdown_terms( $args );
}
endif;

if ( !function_exists( 'list_groups' ) ) :
/**
 * Return list of groups
 *
 * Wrapper function for wp_list_categories(). Only
 * returns list items wrapped in <a> tags. Cannot be rewritten.
 * 
 * @param array $args Arguments for wp_list_categories()
 * @return string HTML content only if 'echo' argument is 0
 */
function list_groups( $args = array() ){
	$defaults = array(
		'taxonomy' => groupz_get_group_tax_id(), 'hide_empty' => 0,
		'show_option_none' => __('No groups', 'groupz'),
		'title_li' => __('Groups', 'groupz'), 'echo' => 1
		);
	$args = wp_parse_args( $args, $defaults ); 

	return wp_list_categories( $args ); // Or in the future: wp_list_terms( $args );
}
endif;

/** Other functions **********************************************/

if ( !function_exists( 'query_find_post_type' ) ) :
/**
 * Return the queried post type from a query string
 *
 * Assumes single post type query. Used to filter the
 * query in the wpdb class.
 * 
 * @param string $query The query to search
 * @return mixed String post type, boolean False if not found
 */
function query_find_post_type( $query ){
	$start     = strpos( $query, "post_type = '" ) + 13;
	$length    = strpos( substr( $query, $start ), "'" );
	$post_type = substr( $query, $start, $length );

	return $post_type;
}
endif;

if ( !function_exists( 'debug_backtrace_function' ) ) :
/**
 * Find a given function in debug_backtrace()
 * 
 * @param string $fn The function name
 * @return boolean
 */
function debug_backtrace_function( $fn ){

	// PHP < 5.3.6
	if ( !defined( 'DEBUG_BACKTRACE_IGNORE_ARGS' ) )
		define( 'DEBUG_BACKTRACE_IGNORE_ARGS', false );

	$fns = array();
	foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $b ){
		$fns[] = $b['function'];
	}

	return in_array( $fn, $fns );
}
endif;

if ( !function_exists( 'array_get_value' ) ) :
/**
 * Return array value at given position
 *
 * $pos holds at each next value the numerical postion
 * at it's key's level in $array.
 * 
 * @param array $array Array to search
 * @param array $pos Course to position
 * @return mixed Found value or NULL if not found
 */
function array_get_value( $arr, $pos ){
	foreach ( $pos as $p ){
		if ( array_key_exists( $p, $arr ) ) 
			$arr = $arr[$p];
		else
			return null;
	}
	return $arr;
}
endif;

if ( !function_exists( 'array_set_value' ) ) :
/**
 * Set new array value at given position
 * 
 * @param array $arr Array to alter
 * @param array $pos Course to position
 * @param mixed $val Value to set
 * @return array $arr Altered array
 */
function array_set_value( $arr, $pos, $val ){
	$r = &$arr;
	foreach ( $pos as $p )
		$r = &$r[$p]; // Move pointer to subarray
	$r = $val;

	return $arr;
}
endif;