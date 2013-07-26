<?php

/**
 * Groupz Access Functions
 *
 * @package Groupz
 * @subpackage Core
 */

// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

/*****************************************************************/

/**
 * Filtering WP_Query for group assigned posts should be easy.
 * Since groups are stored as a taxonomy, it would be a matter 
 * of an additional tax_query argument adding to the query and
 * that is that. But no.
 *
 * Groupz allows for both post hierarchy and group hierarchy
 * in group assignment. This means that all child posts of
 * group A assigned posts are strictly accessible to group A
 * without explicitly stating so for the child posts AND that
 * all subgroups of group A possess the same privileges. It is
 * however rather difficult to build these hierarchy loops in
 * the WP_Query object, to not say impossible. You cannot loop
 * with SQL to search for post parents being based on groups 
 * and do that for their post parents and for their post parents 
 * etcetera. Additionally posts can be assigned to multiple groups 
 * where both groups have read privilege. The build in tax_query 
 * does not provide a distinction between posts assigned to one 
 * or multiple terms without altering the possibly custom set
 * 'relation' flag. Messing with custom queries is evil.
 *
 * The main consideration for not using the tax_query way of
 * filtering the query is that these additions can alter custom
 * queries so badly, it messes with the whole setup and returns
 * only undesired results. 
 * Again: messing with custom queries is evil.
 *
 **//**
 *
 * Fixing the problem we can use three ways to target this issue.
 *
 * 1. Filter method
 *
 * This method ignores the whole query setup and takes action
 * after the query is executed. The result is then filtered
 * to remove any unreadable posts for the current user. If the
 * query requires to return a certain amount of posts, we repeat
 * the query to fill the return array to the requested amount
 * of posts, also filtering for unreadable posts.
 * 
 * Functions used for this setup are
 * - the_posts()
 *
 * 2. Exclude method
 *
 * This method adds to each query an array of unreadable posts to
 * be excluded from what the DB returns by filling the 'post__not_in'
 * query argument. It is a complete pre-query fix, so no hacks are 
 * involved. This method does two queries to find all excludable
 * posts: one for the directly unaccessible and one for the posts
 * that are not assigned to any group, being a potential child of
 * the afornamed and therefor also not accessible.
 * Since we do not know the structure of the site install and the 
 * amount of posts, pages and perhaps topics and replies (in case 
 * of bbPress) assigned to groups the system has, it has the 
 * potential to create huge arrays of excludable IDs, burdening the 
 * SQL queries with enormous NOT IN statements to process. This can
 * get very heavy.
 * 
 * Functions used for this setup are
 * - set_exclude_posts()
 *
 * 3. Include method
 *
 * This method acts as the opposite of the Exclude method by filling
 * the 'post__in' query var, thereby forcing the query to return
 * posts limited to match IDs within the given array instead of 
 * excluding the matched IDs.
 * The same caveat appears here: this method has the potential to
 * create huge SQL IN statements, resulting in heavy calculations.
 * 
 * Functions used for this setup are
 * - set_include_posts()
 *
 * 4. Propagate method
 *
 * Dealing with the impossibility to capture all sub posts that are
 * not directly assigned to a group in a efficient SQL query, this
 * method chooses to only select posts in the SQL query that ARE
 * assigned to groups. This implies that assigning groups to posts
 * requires to propagate the group assignment to all sub posts.
 * Therefor a save and a delete propagation function are activated.
 * This method though fails to act as perfect as we'd like, because
 * when sub posts are created later on, they'd have to be explicitly
 * assigned to the groups of their parent post or else they'll appear
 * in the search results unintended.
 *
 * NB.: This method totally ignores the previously stated notion that
 * Groupz implies post hierarchy when it comes to group assignment.
 * 
 * Functions used for this setup are
 * - posts_where()
 * - add_post_tree()
 * - remove_post_tree()
 * 
 **//**
 *
 * This works directly on the post feeds.
 *
 * NOTE: There is no filtering on the get_post() function.
 * 
 * @todo comment feed filter
 */

if ( !class_exists( 'Groupz_Access' ) ) :

/**
 * Groupz Access class
 */
class Groupz_Access {

	public function __construct(){
		$this->setup_globals();
		$this->setup_actions();
	}

	public function setup_globals(){

		// Set method usage
		$this->method = apply_filters( 'groupz_access_method', 'propagate' );

		// Array to store posts to filter
		$this->filter_posts = array();

		// Post marking
		$this->post_marking = get_option( 'groupz_post_marking', '' );
	}

	public function setup_actions(){

		// Filter method
		if ( $this->method_filter() ) {
			add_filter( 'the_posts',              array( $this, 'the_posts' ),               90, 2 ); // Post get filter after query

		// Exclude method
		} elseif ( $this->method_exclude() ) {
			add_action( 'init',                   array( $this, 'set_exclude_posts' )              ); // Main WP hook
			add_action( 'pre_get_posts',          array( $this, 'pre_get_posts' ),           90    ); // Main post get filter

		// Include method
		} elseif ( $this->method_include() ) {
			add_action( 'init',                   array( $this, 'set_include_posts' )              ); // Main WP hook
			add_action( 'pre_get_posts',          array( $this, 'pre_get_posts' ),           90    ); // Main post get filter

		// Propagate method
		} elseif ( $this->method_propagate() ) {

			// Post Propagation save/delete
			add_action( 'groupz_added_post_group',    array( $this, 'add_post_tree'    ),    10, 2 );
			add_action( 'groupz_removed_post_groups', array( $this, 'remove_post_tree' ),    10, 2 );

			// Filters
			add_filter( 'posts_where',            array( $this, 'posts_where' ),             90, 2 ); // Main post where filter
		}

		// Main filters
		add_filter( 'get_next_post_where',        array( $this, 'get_adjacent_post_where' )        ); // Adjacent post where filter
		add_filter( 'get_previous_post_where',    array( $this, 'get_adjacent_post_where' )        ); // Adjacent post where filter
		add_filter( 'comments_clauses',           array( $this, 'comments_clauses' ),        90, 2 ); // Main comments filter
		add_filter( 'comment_feed_where',         array( $this, 'comment_feed_where' ),      90, 2 ); // Comment feed where filter
		add_filter( 'get_pages',                  array( $this, 'get_pages' ),               90, 2 ); // Pages filter
		add_filter( 'wp_nav_menu_objects',        array( $this, 'wp_nav_menu_objects' ),     90, 2 ); // Nav menu item filter

		// Capability filters
		add_filter( 'map_meta_cap',               array( $this, 'map_meta_cap' ),            90, 4 ); // Main cap filter

		// WPDB Query
		add_filter( 'query',                      array( $this, 'wp_count_posts_filter' )          ); // Post count filter
		// Filter get_comment_count() through query filter ?
	}

	/** Methods *******************************************************/

	public function using_method( $method = '' ){
		return $method == $this->method;
	}

	public function method_propagate(){
		return $this->using_method( 'propagate' );
	}

	public function method_filter(){
		return $this->using_method( 'filter' );
	}

	public function method_exclude(){
		return $this->using_method( 'exclude' );
	}

	public function method_include(){
		return $this->using_method( 'include' );
	}

	public function empty_filter_posts(){
		if ( $this->method_exclude() || $this->method_include() )
			return empty( $this->filter_posts );
		else
			return true;
	}

	public function return_filter_posts(){
		if ( $this->method_exclude() || $this->method_include() )
			return $this->filter_posts;
		else
			return array();
	}

	/**
	 * Append the where clause for post querying
	 * 
	 * @param string $match_post_id The post ID column
	 * @param string $match_post_type The post type column
	 * @return string Appended where clause
	 */
	public function create_where_statement( $match_post_id ){
		global $wpdb;

		// Setup return var
		$where = '';

		// Nothing to filter for
		if ( array() === groupz()->get_read_post_types() )
			return $where;

		// Exclude or include method
		if ( $this->method_exclude() || $this->method_include() ) {
			$ids = implode( ',', $this->return_filter_posts() );
			
			// Only if there are posts to filter for
			if ( ! empty( $ids ) )
				$where = sprintf( " AND $match_post_id %s ($ids)", $this->method_exclude() ? 'NOT IN' : 'IN' );		

		// Propagate method
		} elseif ( $this->method_propagate() ) {

			// Get user and all groups		
			$user_groups = implode( ',', get_user_groups( get_current_user_id(), true, true ) );
			$all_groups  = implode( ',', get_groups( array( 'fields' => 'ids' ) ) );
			$post_types  = "'" . implode( "','", groupz()->get_read_post_types() ) . "'";

			// We have groups to filter for
			if ( ! empty( $all_groups ) ) {

				$subquery = "SELECT
							object_id
						FROM
							$wpdb->term_relationships
						INNER JOIN
							$wpdb->posts ON ( $wpdb->term_relationships.object_id = $wpdb->posts.ID )		
						WHERE
							$wpdb->term_relationships.term_taxonomy_id IN (%s)
						AND
							$wpdb->posts.post_type IN ($post_types)";

				// Posts with user groups
				if ( ! empty( $user_groups ) )
					$where .= "$match_post_id IN ( ". sprintf( $subquery, $user_groups ) ." ) OR ";

				// Posts having no group at all
				$where .= "$match_post_id NOT IN ( ". sprintf( $subquery, $all_groups ) ." )";

				// Create AND statement
				$where = ' AND ( '. $where .' )';
			}
		}

		return $where;
	}

	/**
	 * Return whether a given post is readable for the current user
	 *
	 * @uses user_in_post_groups() To find if the user can read given post
	 * @param int $post_id Post ID
	 * @return boolean Post is readable
	 */
	public function is_readable( $post_id ){

		// Excluding
		if ( $this->method_exclude() )
			return ! in_array( $post_id, $this->filter_posts );

		// Including
		elseif ( $this->method_include() )
			return in_array( $post_id, $this->filter_posts );

		// Other
		else
			return user_in_post_groups( $post_id );
	}

	/** Propagate method **********************************************/

	/**
	 * Walk the post child tree to propagate group assignement
	 *
	 * @uses get_option() To check whether to use propatagion
	 * @uses get_children() To get the post children
	 * @uses groupz_is_read_post_type() To check the readabilty
	 * @uses groupz_post_in_group() To check if the group is already assigned
	 * @uses wp_set_object_terms() To add groups to the post children
	 * 
	 * @param int $post_id Post id
	 * @param int $group_id Group id
	 */
	public function add_post_tree( $post_id, $group_id ) {

		if ( ! get_option( 'groupz_propagate' ) ) return;

		// Loop post children 
		if ( $children = new WP_Query( array(
			'post_parent' => $post_id,
			'post_type'   => groupz()->get_read_post_types(),
			'fields'      => 'ids',
		) ) ) {
			foreach ( $children->posts as $child_id ) {

				// Do this function for the children
				call_user_func_array( array( $this, __FUNCTION__ ), array( (int) $child_id, $group_id ) );
			}
		}

		// Add group if not yet added. Do not loop for hierarchy
		if ( ! groupz_post_in_group( $group_id, $post_id, false ) ) {

			// Prevent looping
			remove_action( 'groupz_added_post_group', array( $this, __FUNCTION__ ) );

			// Append group to object
			wp_set_object_terms( $post_id, $group_id, groupz_get_group_tax_id(), true );

			// Reset action
			add_action( 'groupz_added_post_group', array( $this, __FUNCTION__ ), 10, 2 );
		}
	}

	/**
	 * Walk the post tree to propagate group unassignment
	 *
	 * @uses get_option() To check whether to use propatagion
	 * @uses get_children() To get the post children
	 * @uses groupz_is_read_post_type() To check the readabilty
	 * @uses wp_delete_object_term_relationship() To remove groups from the post children
	 * 
	 * @param int $post_id Post id
	 * @param array $group_ids Group ids
	 */
	public function remove_post_tree( $post_id, $group_ids ) {

		if ( ! get_option( 'groupz_propagate' ) ) return;

		// Loop post children 
		if ( $children = new WP_Query( array(
			'post_parent' => $post_id,
			'post_type'   => groupz()->get_read_post_types(),
			'fields'      => 'ids',
		) ) ) {
			foreach ( $children->posts as $child_id ) {

				// Do this function for the children
				call_user_func_array( array( $this, __FUNCTION__ ), array( (int) $child_id, $group_ids ) );
			}
		}

		// Prevent looping
		remove_action( 'groupz_removed_post_groups', array( $this, __FUNCTION__ ) );

		// Remove groups from object
		wp_set_object_terms( $post_id, array_diff( get_post_groups( $post_id ), $group_ids ), groupz_get_group_tax_id() );

		// Reset action
		add_action( 'groupz_removed_post_groups', array( $this, __FUNCTION__ ), 10, 2 );
	}

	/** Filter method *************************************************/

	/** Exclude method ************************************************/

	/**
	 * Create an array with all unreadable post IDs for current user
	 * and set the value to self::filter_posts
	 *
	 * Does only two queries to find all excludable posts.
	 *
	 * @uses get_not_user_groups()
	 * @uses get_user_groups()
	 */
	public function set_exclude_posts(){

		// Bail if user can ignore groups
		if ( current_user_can( 'ignore_groups' ) )
			return;

		// Bail if no post types are currently supported
		if ( array() === groupz()->get_read_post_types() )
			return;

		add_filter( 'query', array( $this, 'dump_query' ) );

		// Detach action
		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Get groups user is not in
		$groups = get_not_user_groups();

		// Fetch all posts user is not in group of
		$get_posts = new WP_Query;
		$args      = array(
			'post_type' => groupz()->get_read_post_types(), // Only find supported post types
			'showposts' => -1,
			'fields'    => 'ids',
			'tax_query' => array(
				'relation'     => 'AND', // Default, but explicitated for method
				array(
					'taxonomy' => groupz_get_group_tax_id(),
					'terms'    => $groups, // All groups user is not in
					'operator' => 'IN'
					),
				array(
					'taxonomy' => groupz_get_group_tax_id(),
					'terms'    => get_user_groups( get_current_user_id(), true, true ), // Include group ancestors
					'operator' => 'NOT IN'
					)
				)
			);
		$exclude   = empty( $groups ) ? array() : array_map( 'intval', $get_posts->query( $args ) );

		// Fetch all posts that have no group assigned as ID => parent_ID
		$args      = array(
			'post_type' => groupz()->get_read_post_types(), // Only find supported post types
			'showposts' => -1,
			'fields'    => 'id=>parent',
			'tax_query' => array(
				array(
					'taxonomy' => groupz_get_group_tax_id(),
					'terms'    => get_groups( array( 'fields' => 'ids' ) ), // All groups
					'operator' => 'NOT IN' // Find posts not in any group
					)
				)
			);
		$empty     = array_map( 'intval', $get_posts->query( $args ) );

		// Backup empty var for future reference. Ultimately the difference of the empty backup and empty original are the posts to exclude.
		$empty_copy = $empty;

		// Loop empty posts to find unaccessible posts
		foreach ( $empty as $id => $parent_id ){

			// Loop as long as we're still here
			while ( isset( $empty[$id] ) && isset( $empty_copy[$id] ) ){

				// Empty post has no parent => accessible
				if ( 0 == $parent_id )
					unset( $empty_copy[$id] ); // Does not need to be referenced

				// Empty post has excluded parent => unaccessible
				elseif ( in_array( $parent_id, $exclude ) )
					unset( $empty[$id] );

				// Empty post has no empty parent = empty post has allowed parent => accessible
				elseif ( !in_array( $parent_id, array_keys( $empty_copy ) ) )
					unset( $empty_copy[$id] ); // Does not need to be referenced

				// Empty post has empty parent
				else
					$parent_id = $empty_copy[$parent_id];
			}
		}

		// Find excludable posts
		$empty_exclude = array_diff( array_keys( $empty_copy ), array_keys( $empty ) );

		// Filter post IDs
		$post_ids = apply_filters( 'groupz_set_exclude_posts', array_merge( $exclude, $empty_exclude ) );

		// Set exclude posts
		$this->filter_posts = $post_ids;

		// Reattach action
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
	}

	/** Include method ************************************************/

	/**
	 * Create an array with all readable post IDs for current user
	 * and set the value to self::filter_posts
	 *
	 * Does only two queries to find all includable posts.
	 *
	 * @uses get_user_groups()
	 */
	public function set_include_posts(){

		// Bail if user can ignore groups
		if ( current_user_can( 'ignore_groups' ) )
			return;

		// Bail if no post types are currently supported
		if ( array() === groupz()->get_read_post_types() )
			return;

		// Detach action
		remove_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );

		// Get user groups and group ancestors
		$groups = get_user_groups( get_current_user_id(), true, true );

		// Fetch all posts user is in group of
		$get_posts = new WP_Query;
		$args      = array(
			'post_type' => groupz()->get_read_post_types(), // Only find supported post types
			'showposts' => -1,
			'fields'    => 'ids',
			'tax_query' => array(
				array(
					'taxonomy' => groupz_get_group_tax_id(),
					'terms'    => $groups // Include group ancestors
					)
				)
			);
		$include   = empty( $groups ) ? array() : array_map( 'intval', $get_posts->query( $args ) );

		// Fetch all posts that have no group assigned as ID => parent_ID
		$args      = array(
			'post_type' => groupz()->get_read_post_types(), // Only find supported post types
			'showposts' => -1,
			'fields'    => 'id=>parent',
			'tax_query' => array(
				array(
					'taxonomy' => groupz_get_group_tax_id(),
					'terms'    => get_groups( array( 'fields' => 'ids' ) ), // All groups
					'operator' => 'NOT IN' // Find posts not in any group
					)
				)
			);
		$empty     = array_map( 'intval', $get_posts->query( $args ) );

		// Backup empty var for future reference. Ultimately the difference of the empty backup and empty original are the posts to include.
		$empty_copy = $empty;

		// Loop empty posts to find accessible posts
		foreach ( $empty as $id => $parent_id ){

			// Loop as long as we're still here
			while ( isset( $empty[$id] ) && isset( $empty_copy[$id] ) ){

				// Empty post has no parent => accessible
				if ( 0 == $parent_id )
					unset( $empty[$id] );

				// Empty post has included parent => accessible
				elseif ( in_array( $parent_id, $include ) )
					unset( $empty[$id] );

				// Empty post has no empty parent = empty post has restricted parent => unaccessible
				elseif ( !in_array( $parent_id, array_keys( $empty_copy ) ) )
					unset( $empty_copy[$id] ); // Does not need to be referenced

				// Empty post has empty parent
				else
					$parent_id = $empty_copy[$parent_id];
			}
		}

		// Find includable posts
		$empty_include = array_diff( array_keys( $empty_copy ), array_keys( $empty ) );

		// Filter post IDs
		$post_ids = apply_filters( 'groupz_set_include_posts', array_merge( $include, $empty_include ) );

		// Set include posts
		$this->filter_posts = $post_ids;

		// Reattach action
		add_action( 'pre_get_posts', array( $this, 'pre_get_posts' ) );
	}

	/** Main filters *************************************************/

	/**
	 * Manipulate the post query to filter for groups
	 *
	 * Adds post IDs to the 'post__in' or 'post__not_in' query argument,
	 * according to the active method. Thereby forcing the query to only 
	 * return posts that are in or not in this array of IDs.
	 * 
	 * @param WP_Query $posts_query The query object
	 */
	public function pre_get_posts( $posts_query ){

		// Bail if $posts_query is not an object or of incorrect class
		if ( !is_object( $posts_query ) || !is_a( $posts_query, 'WP_Query' ) )
			return;

		// Bail if given post type is not supported 
		if ( !groupz_is_read_post_type_maybe_array( $posts_query->get( 'post_type' ), $posts_query ) )
			return;

		// Bail if user is capable of ignoring groups
		if ( current_user_can( 'ignore_groups' ) )
			return;

		// Bail if no filterable posts found. Only continue if post__in or post__not_in seem valid
		if ( $this->empty_filter_posts() )
			return;

		// Which method are we using
		$method = $this->method_include() ? 'post__in' : 'post__not_in';

		// Get query var
		$post_ids = $posts_query->get( $method );

		// Add to query var
		$post_ids = array_unique( array_merge( (array) $post_ids, $this->return_filter_posts() ) );

		// Set query var
		$posts_query->set( $method, $post_ids );
	}

	/**
	 * Excludes posts from the WP_Query results when the
	 * current user is not in the required groups
	 *
	 * NOTE: This filter only works if suppress_filters query
	 * argument is set to FALSE. This is not the default case 
	 * for get_posts().
	 *
	 * This filter does not run when 'fields' argument equals
	 * 'ids' or 'id=>parent'. Those aren't filtered after the
	 * SQL query is run.
	 *
	 * @uses groupz_is_read_post_type()
	 * @uses groupz_is_readable()
	 * 
	 * @param array $posts Found posts
	 * @param WP_Query $query The query object
	 * @return array $posts Filtered posts
	 */
	public function the_posts( $posts, $query ){

		// Bail if no posts are found
		if ( empty( $posts ) )
			return array();

		// Set reference count var
		else
			$num_posts = count( $posts );

		// Loop over all posts
		foreach ( $posts as $k => $post ){

			// Ignore if not supported post type
			if ( ! groupz_is_read_post_type( $post->post_type ) )
				continue;

			// Ignore groups if user is capable
			if ( current_user_can( 'ignore_groups' ) )
				continue;

			// Remove post if user is not in groups
			if ( ! groupz_is_readable( $post->ID ) )
				unset( $posts[$k] );
		}

		// Reset numerical array keys
		$posts = array_values( $posts );

		// Get post count
		$count = count( $posts );

		// Get posts per page value
		$ppp = (int) $query->get( 'posts_per_page' );

		// Make sure we return the requested amount of posts
		if ( $count !== $num_posts  // Ignore untouched queries
			&& -1 !== $ppp          // Ignore limitless queries
			&& !$query->is_singular // Ignore single page requests
			&& $count <= $ppp       // Posts can excede posts per page request in case of menus
			){

			// Calculate additional posts to query
			$add_count = $ppp - $count;

			// Set posts per page var for new query
			$query->set( 'showposts', $add_count );

			// Set offset var for new query
			$query->set( 'offset', (int) $query->get( 'offset' ) + $ppp );

			// Setup new query
			$new_query = new WP_Query;

			// Fetch remaining posts WITH this filter
			$new_posts = $new_query->query( $query->query_vars );

			// Merge new posts
			$posts = array_merge( $posts, $new_posts );
		}

		return $posts;
	}

	/**
	 * Filter the main post query where statement
	 * 
	 * @param string $where Where clause
	 * @param WP_Query $query
	 * @return string Appended where clause
	 */
	public function posts_where( $where, $query ) {
		global $wpdb;

		// Ignore groups if user is capable
		if ( current_user_can( 'ignore_groups' ) )
			return $where;

		// Append where statement to where clause
		$where .= $this->create_where_statement( "$wpdb->posts.ID" );

		return $where;
	}

	/**
	 * Filter posts from adjacent posts when the current
	 * user is not in the required groups
	 *
	 * @param string $where The where clause
	 * @return string $where Appended where clause
	 */
	public function get_adjacent_post_where( $where ){

		// Ignore groups if user is capable
		if ( current_user_can( 'ignore_groups' ) )
			return $where;

		// Append where statement to where clause
		$where .= $this->create_where_statement( 'p.id' );

		return $where;
	}

	/**
	 * Filters comments from the WP_Comment_Query queries
	 * when the current user is not in the required groups
	 * 
	 * @param array $clauses The query clauses
	 * @param WP_Comment_Query $query Query object
	 * @return array $clauses
	 */
	public function comments_clauses( $clauses, $query ){
		global $wpdb;

		// Ignore groups if user is capable
		if ( current_user_can( 'ignore_groups' ) )
			return $clauses;

		// Append where statement to where clause
		$clauses['where'] .= $this->create_where_statement( "$wpdb->comments.comment_post_ID" );

		return $clauses;
	}

	/**
	 * Filters the comment feed for only public posts
	 * by extending the where query clause
	 * 
	 * @param string $cwhere Comment where clause
	 * @param WP_Query $query
	 * @return string Appended where clause
	 */
	public function comment_feed_where( $cwhere, $query ) {
		global $wpdb;

		// Don't override main where query filter. Only for front?
		if ( ! $query->is_archive && ! $query->is_search )
			return $cwhere;

		// Append where statement to where clause
		$cwhere .= $this->create_where_statement( "$wpdb->comments.comment_post_ID" );

		return $cwhere;
	}

	/**
	 * Filters pages from the get_pages() function when the
	 * current user is not in the required groups
	 *
	 * Additionally adds page title marking if user is capable
	 *
	 * @uses groupz_is_read_post_type()
	 * @uses groupz_mark_post_title()
	 * @uses groupz_is_readable()
	 * 
	 * @param array $pages Found pages
	 * @param array $args Query arguments
	 * @return array $pages Filtered pages
	 */
	public function get_pages( $pages, $args ){

		// Bail if not supported post type
		if ( ! groupz_is_read_post_type( 'page' ) )
			return $pages;

		// Loop over all pages
		foreach ( $pages as $k => &$page ){

			// Add group marking if user is capable
			if ( current_user_can( 'view_group_markings' ) )
				groupz_mark_post_title( $page );

			// Ignore groups if user is capable
			if ( current_user_can( 'ignore_groups' ) )
				continue;

			// Remove page if user is not in groups
			if ( ! groupz_is_readable( $page->ID ) )
				unset( $pages[$k] );
		}

		return apply_filters( 'groupz_get_pages_filter', $pages, $args );
	}

	/**
	 * Filters items from the wp nav menu's when the current
	 * user is not in the required groups
	 * 
	 * Additionally adds item title marking if user is capable
	 *
	 * @uses groupz_is_read_post_type()
	 * @uses groupz_mark_nav_item_title()
	 * @uses groupz_is_readable()
	 * 
	 * @param array $nav_menu_items The menu items
	 * @param array $args Query arguments
	 * @return array $nav_menu_items Filtered items
	 */
	public function wp_nav_menu_objects( $nav_menu_items, $args ){
		
		// Loop over all items
		foreach ( $nav_menu_items as $k => &$item ){

			// Ignore if not supported post type
			if ( isset( $item->object ) && ! groupz_is_read_post_type( $item->object ) )
				continue;

			// Add group marking if user is capable
			if ( current_user_can( 'view_group_markings' ) )
				groupz_mark_nav_item_title( $item );

			// Ignore groups if user is capable
			if ( current_user_can( 'ignore_groups' ) )
				continue;

			// Remove nav item if user is not in groups
			if ( isset( $item->object_id ) && ! groupz_is_readable( $item->object_id ) )
				unset( $nav_menu_items[$k] );
		}

		return apply_filters( 'groupz_wp_nav_menu_objects', $nav_menu_items, $args );
	}

	/** Capability filters *******************************************/

	/**
	 * Grant user edit access for edit group assigned posts
	 *
	 * @uses groupz_is_edit_post_type()
	 * @uses user_in_post_edit_groups()
	 * 
	 * @param array $all_caps User caps
	 * @param array $caps Required caps
	 * @param array $args Additional arguments
	 * @return array $all_caps
	 */
	public function map_meta_cap( $caps = array(), $cap = '', $user_id = 0, $args = array() ) {

		// Are we handling a post of some sorts?
		if ( ! isset( $args[0] ) )
			return $caps;

		// Get post
		$_post = get_post( $args[0] ); // @todo Query post for each cap?

		// Post is of edit post type
		if ( ! empty( $_post ) && groupz_is_edit_post_type( $_post->post_type ) ) {

			// Get post type object
			$_pto = get_post_type_object( $_post->post_type );

			// What capability is being checked?
			switch ( $cap ) {

				// Post editing
				case $_pto->cap->edit_posts        :
				case $_pto->cap->edit_others_posts :

					// Ignore groups if user is capable
					if ( current_user_can( 'ignore_groups' ) )
						break;

					// User can read and edit
					if ( user_in_post_edit_groups( $_post->ID, $user_id ) && current_user_can( 'read' ) )
						$caps = array( 'read' );

					break;
			}
		}

		return apply_filters( 'groupz_map_meta_cap', $caps, $cap, $user_id, $args );
	}

	/** WPDB Query ***************************************************/

	/**
	 * These filters all hook into the 'query' filter in the $wpdb
	 * global, which is passed through for each query in WP. Although 
	 * not preferable, it's necessary since the targeted functions 
	 * lack the required filters for this. To do this, we use 
	 * debug_backtrace() to find if we're using the function we want
	 * to filter. Problem is, it runs for all queries. Needs fixin'!
	 */

	/**
	 * Filters posts from wp_count_posts when the current
	 * user is not in the required groups
	 *
	 * @link http://core.trac.wordpress.org/ticket/16603
	 *
	 * @uses debug_backtrace_function() To find if we're executing wp_count_posts()
	 * @uses groupz_is_read_post_type()
	 * @uses groupz_query_find_post_type()
	 *
	 * @param string $query The current query
	 * @return string $query Modified query
	 */
	public function wp_count_posts_filter( $query ){

		// Find wp_count_posts function
		if ( debug_backtrace_function( 'wp_count_posts' ) ){

			// Bail if not supported post type
			if ( !groupz_is_read_post_type( query_find_post_type( $query ) ) )
				return $query;
			
			// Ignore groups if user is capable
			if ( current_user_can( 'ignore_groups' ) )
				return $query;

			// Manipulate query string
			$replacement = substr( $this->create_where_statement( 'id' ), 4 ); // Eliminate starting ' AND'
			$pos         = strpos( $query, 'WHERE' ) + 5;
			$query       = substr_replace( $query, $replacement, $pos, 0 );
		}

		return $query;
	}

}

/**
 * Hook Access class into Groupz
 * 
 * @return void
 */
function groupz_access(){
	groupz()->access = new Groupz_Access();
}

endif; // class_exists

/** Main access **************************************************/

/**
 * Return whether the current user can read a given post
 *
 * Checks post ID against methods post IDs
 * 
 * @param int $post_id The post ID
 * @return boolean Post is readable
 */
function groupz_is_readable( $post_id ){
	return groupz()->access->is_readable( $post_id );
}

/** Post marking *************************************************/

/**
 * Append a marking to the post title by reference
 *
 * @uses groupz_add_marking()
 * @uses do_action() To call 'groupz_mark_post_title'
 * 
 * @param object $post The post to alter
 * @return void
 */
function groupz_mark_post_title( $post ){

	// Add marking
	$post->post_title = groupz_add_marking( $post->post_title, $post->ID );

	// Hook
	do_action( 'groupz_mark_post_title', $post );
}

/**
 * Append a marking to the nav item title by reference
 * 
 * @uses groupz_add_marking()
 * @uses do_action() To call 'groupz_mark_nav_item_title'
 * 
 * @param object $item The nav item to alter. Passed by reference
 * @return void
 */
function groupz_mark_nav_item_title( $item ){

	// Bail for items without a title or for non-queriable objects
	if ( !isset( $item->title ) || !in_array( $item->object, get_post_types( array( 'public' => true ), 'names' ) ) )
		return;

	// Add marking 
	$item->title = groupz_add_marking( $item->title, $item->object_id );

	// Hook
	do_action( 'groupz_mark_nav_item_title', $item );	
}

/**
 * Return post title appended with group marking
 * 
 * @param string $post_title The post title
 * @param int $post_id The post ID
 * @return string $post_title Appended with group marking
 */
function groupz_add_marking( $post_title, $post_id ){

	// Marking requires group assignment
	if ( groupz_post_has_group( $post_id, true ) )
		$post_title .= groupz()->access->post_marking;

	return apply_filters( 'groupz_add_marking', $post_title, $post_id );	
}

