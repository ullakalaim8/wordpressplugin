<?php
//TODO EM_Events is currently static, better we make this non-static so we can loop sets of events, and standardize with other objects.
/**
 * Use this class to query and manipulate sets of events. If dealing with more than one event, you probably want to use this class in some way.
 *
 */
class EM_Events extends EM_Object implements Iterator {
	/**
	 * Array of EM_Event objects
	 * @var array EM_Event
	 */
	var $events = array();
	
	function EM_Events( $args = array() ){
		if( is_array($args) ){
			if( is_object(current($args)) && get_class(current($args)) == 'EM_Event' ){
				$this->events = $args;
			}else{
				$this->events = EM_Events::get($args);
			}
		}else{
			$this->events = EM_Events::get();
		}
		do_action('em_events',$this);
	}
	
	/**
	 * Returns an array of EM_Events that match the given specs in the argument, or returns a list of future evetnts in future 
	 * (see EM_Events::get_default_search() ) for explanation of possible search array values. You can also supply a numeric array
	 * containing the ids of the events you'd like to obtain 
	 * 
	 * @param array $args
	 * @return EM_Event array()
	 */
	function get( $args = array(), $count=false ) {
		global $wpdb;	 
		$events_table = EM_EVENTS_TABLE;
		$locations_table = EM_LOCATIONS_TABLE;
		
		//Quick version, we can accept an array of IDs, which is easy to retrieve
		if( self::array_is_numeric($args) ){ //Array of numbers, assume they are event IDs to retreive
			//We can just get all the events here and return them
			$sql = "
				SELECT * FROM $events_table
				LEFT JOIN $locations_table ON {$locations_table}.location_id={$events_table}.location_id
				WHERE event_id=".implode(" OR event_id=", $args)."
			";
			$results = $wpdb->get_results(apply_filters('em_events_get_sql',$sql),ARRAY_A);
			$events = array();
			foreach($results as $result){
				$events[$result['event_id']] = new EM_Event($result);
			}
			return $events; //We return all the events matched as an EM_Event array. 
		}
		
		//We assume it's either an empty array or array of search arguments to merge with defaults			
		$args = self::get_default_search($args);
		$limit = ( $args['limit'] && is_numeric($args['limit'])) ? "LIMIT {$args['limit']}" : '';
		$offset = ( $limit != "" && is_numeric($args['offset']) ) ? "OFFSET {$args['offset']}" : '';
		
		//Get the default conditions
		$conditions = self::build_sql_conditions($args);
		//Put it all together
		$where = ( count($conditions) > 0 ) ? " WHERE " . implode ( " AND ", $conditions ):'';
		
		//Get ordering instructions
		$EM_Event = new EM_Event();
		$accepted_fields = $EM_Event->get_fields(true);
		$orderby = self::build_sql_orderby($args, $accepted_fields, get_option('dbem_events_default_order'));
		//Now, build orderby sql
		$orderby_sql = ( count($orderby) > 0 ) ? 'ORDER BY '. implode(', ', $orderby) : '';
		
		//Create the SQL statement and execute
		$selectors = ( $count ) ?  'COUNT(event_id)':$events_table.'.post_id';
		$sql = "
			SELECT $selectors FROM $events_table
			LEFT JOIN $locations_table ON {$locations_table}.location_id={$events_table}.location_id
			$where
			$orderby_sql
			$limit $offset
		";
			
		//If we're only counting results, return the number of results
		if( $count ){
			return apply_filters('em_events_get_count', $wpdb->get_var($sql), $args);		
		}
		$results = $wpdb->get_results( apply_filters('em_events_get_sql',$sql, $args), ARRAY_A);

		//If we want results directly in an array, why not have a shortcut here?
		if( $args['array'] == true ){
			return apply_filters('em_events_get_array',$results, $args);
		}
		
		//Make returned results EM_Event objects
		$results = (is_array($results)) ? $results:array();
		$events = array();
		foreach ( $results as $event ){
			$events[] = em_get_event($event['post_id'], 'post_id');
		}
		
		return apply_filters('em_events_get', $events, $args);
	}
	
	/**
	 * Returns the number of events on a given date
	 * @param $date
	 * @return int
	 */
	function count_date($date){
		global $wpdb;
		$table_name = EM_EVENTS_TABLE;
		$sql = "SELECT COUNT(*) FROM  $table_name WHERE (event_start_date  like '$date') OR (event_start_date <= '$date' AND event_end_date >= '$date');";
		return apply_filters('em_events_count_date', $wpdb->get_var($sql));
	}
	
	function count( $args = array() ){
		return apply_filters('em_events_count', self::get($args, true), $args);
	}
	
	/**
	 * Will delete given an array of event_ids or EM_Event objects
	 * @param unknown_type $id_array
	 */
	function delete( $array ){
		//TODO add better cap checks in bulk actions
		global $wpdb;
		//Detect array type and generate SQL for event IDs
		$event_ids = array();
		if( @get_class(current($array)) == 'EM_Event' ){
			foreach($array as $EM_Event){
				$event_ids[] = $EM_Event->id;
			}
		}else{
			$event_ids = $array;
		}
		if(self::array_is_numeric($event_ids)){
			//First we have to check that they're all deletable by this user.
			if ( self::can_manage($event_ids) ) {
				apply_filters('em_events_delete_pre', $event_ids);
				$condition = "event_id IN (". implode(',',$event_ids).")";
				//Delete WP Posts etc.
				$post_ids = $wpdb->get_col("SELECT post_id FROM ".EM_EVENTS_TABLE." WHERE $condition");
				foreach($post_ids as $post_id){
					wp_delete_post($post_id,true);
				}
				//Delete EM Table data
				//Delete all tickets bookings
				$result_tickets_bookings = $wpdb->query("DELETE FROM ".EM_TICKETS_BOOKINGS_TABLE." WHERE booking_id IN (SELECT booking_id FROM ". EM_BOOKINGS_TABLE ." WHERE $condition)");
				//Delete all tickets
				$result_tickets = $wpdb->query("DELETE FROM ". EM_TICKETS_TABLE ." WHERE $condition;");
				//Delete all the bookings
				$result_bookings = $wpdb->query("DELETE FROM ". EM_BOOKINGS_TABLE ." WHERE $condition;");
				//Now delete the events
				$result_events = $wpdb->query ( "DELETE FROM ". EM_EVENTS_TABLE ." WHERE $condition;" );
				return apply_filters('em_events_delete', true, $event_ids);
			}else{
				return apply_filters('em_events_delete', true, $event_ids);
			}
		}
		//TODO add better error feedback on events delete fails
		return apply_filters('em_events_delete', true, $event_ids);
	}
	
	
	/**
	 * Output a set of matched of events. You can pass on an array of EM_Events as well, in this event you can pass args in second param.
	 * Note that you can pass a 'pagination' boolean attribute to enable pagination, default is enabled (true). 
	 * @param array $args
	 * @param array $secondary_args
	 * @return string
	 */
	function output( $args ){
		global $EM_Event;
		$EM_Event_old = $EM_Event; //When looping, we can replace EM_Event global with the current event in the loop
		//Can be either an array for the get search or an array of EM_Event objects
		$func_args = func_get_args();
		if( is_object(current($args)) && get_class((current($args))) == 'EM_Event' ){
			$func_args = func_get_args();
			$events = $func_args[0];
			$args = (!empty($func_args[1]) && is_array($func_args[1])) ? $func_args[1] : array();
			$args = apply_filters('em_events_output_args', self::get_default_search($args), $events);
			$limit = ( !empty($args['limit']) && is_numeric($args['limit']) ) ? $args['limit']:false;
			$offset = ( !empty($args['offset']) && is_numeric($args['offset']) ) ? $args['offset']:0;
			$page = ( !empty($args['page']) && is_numeric($args['page']) ) ? $args['page']:1;
		}else{
			//Firstly, let's check for a limit/offset here, because if there is we need to remove it and manually do this
			$args = apply_filters('em_events_output_args', self::get_default_search($args) );
			$limit = ( !empty($args['limit']) && is_numeric($args['limit']) ) ? $args['limit']:false;
			$offset = ( !empty($args['offset']) && is_numeric($args['offset']) ) ? $args['offset']:0;
			$page = ( !empty($args['page']) && is_numeric($args['page']) ) ? $args['page']:1;
			$args['limit'] = false;
			$args['offset'] = false;
			$args['page'] = false;
			$events = self::get( $args );
		}
		//What format shall we output this to, or use default
		$format = ( empty($args['format']) ) ? get_option( 'dbem_event_list_item_format' ) : $args['format'] ;
		
		$output = "";
		$events = apply_filters('em_events_output_events', $events);
		$events_count = count($events);
		if ( $events_count > 0 ) {
			$event_count = 0;
			$events_shown = 0;
			foreach ( $events as $EM_Event ) {
				if( ($events_shown < $limit || empty($limit)) && ($event_count >= $offset || $offset === 0) ){
					$output .= $EM_Event->output($format);
					$events_shown++;
				}
				$event_count++;
			}
			//Add headers and footers to output
			if( $format == get_option ( 'dbem_event_list_item_format' ) ){
				$format_header = ( get_option( 'dbem_event_list_item_format_header') == '' ) ? '':get_option ( 'dbem_event_list_item_format_header' );
				$format_footer = ( get_option ( 'dbem_event_list_item_format_footer' ) == '' ) ? '':get_option ( 'dbem_event_list_item_format_footer' );
			}else{
				$format_header = ( !empty($args['format_header']) ) ? $args['format_header']:'';
				$format_footer = ( !empty($args['format_footer']) ) ? $args['format_footer']:'';
			}
			$output = $format_header .  $output . $format_footer;
			//Pagination (if needed/requested)
			if( !empty($args['pagination']) && !empty($limit) && $events_count > $limit ){
				//Show the pagination links (unless there's less than $limit events)
				$page_link_template = preg_replace('/(&|\?)page=\d+/i','',$_SERVER['REQUEST_URI']);
				$page_link_template = em_add_get_params($page_link_template, array('page'=>'%PAGE%'));
				$output .= apply_filters('em_events_output_pagination', em_paginate( $page_link_template, $events_count, $limit, $page), $page_link_template, $events_count, $limit, $page);
			}
		} else {
			$output = get_option ( 'dbem_no_events_message' );
		}	
		
		//TODO check if reference is ok when restoring object, due to changes in php5 v 4
		$EM_Event = $EM_Event_old;
		$output = apply_filters('em_events_output', $output, $events, $args);
		return $output;		
	}
	
	function can_manage($event_ids){
		global $wpdb;
		if( current_user_can('edit_others_events') ){
			return apply_filters('em_events_can_manage', true, $event_ids);
		}
		if( EM_Object::array_is_numeric($event_ids) ){
			$condition = implode(" OR event_id=", $event_ids);
			//we try to find any of these events that don't belong to this user
			$results = $wpdb->get_var("SELECT COUNT(*) FROM ". EM_EVENTS_TABLE ." WHERE event_owner != '". get_current_user_id() ."' event_id=$condition;");
			return apply_filters('em_events_can_manage', ($results == 0), $event_ids);
		}
		return apply_filters('em_events_can_manage', false, $event_ids);
	}
	
	function get_post_search($args = array()){
		$accepted_searches = apply_filters('em_accepted_searches', array('scope','search','category','country','state','region','town'), $args);
		foreach($_REQUEST as $post_key => $post_value){
			if( in_array($post_key, $accepted_searches) && !empty($post_value) ){
				if(is_array($post_value)){
					$post_value = implode(',',$post_value);
				}
				if($post_value != ',' ){
					$args[$post_key] = $post_value;
				}
			}
		}
		return apply_filters('em_events_get_post_search', $args);
	}

	/* Overrides EM_Object method to apply a filter to result
	 * @see wp-content/plugins/events-manager/classes/EM_Object#build_sql_conditions()
	 */
	function build_sql_conditions( $args = array() ){
		$conditions = parent::build_sql_conditions($args);
		if( !empty($args['search']) ){
			$like_search = array('event_name','event_notes','location_name','location_address','location_town','location_postcode','location_state','location_country');
			$conditions['search'] = "(".implode(" LIKE '%{$args['search']}%' OR ", $like_search). "  LIKE '%{$args['search']}%')";
		}
		if( array_key_exists('status',$args) && is_numeric($args['status']) ){
			$null = ($args['status'] == 0) ? ' OR `event_status` = 0':'';
			$conditions['status'] = "(`event_status`={$args['status']}{$null} )";
		}
		if( is_multisite() && array_key_exists('blog',$args) && is_numeric($args['blog']) ){
			if( is_main_site($args['blog']) ){
				$conditions['blog'] = "(`blog_id`={$args['blog']} OR blog_id IS NULL)";
			}else{
				$conditions['blog'] = "(`blog_id`={$args['blog']})";
			}
		}
		if( $args['bookings'] === 'user' && is_user_logged_in()){
			//get bookings of user
			$EM_Person = new EM_Person(get_current_user_id());
			$booking_ids = $EM_Person->get_bookings(true);
			if( count($booking_ids) > 0 ){
				$conditions['bookings'] = "(event_id IN (SELECT event_id FROM ".EM_BOOKINGS_TABLE." WHERE booking_id IN (".implode(',',$booking_ids).")))";
			}else{
				$conditions['bookings'] = "(event_id = 0)";
			}
		}
		return apply_filters( 'em_events_build_sql_conditions', $conditions, $args );
	}
	
	/* Overrides EM_Object method to apply a filter to result
	 * @see wp-content/plugins/events-manager/classes/EM_Object#build_sql_orderby()
	 */
	function build_sql_orderby( $args, $accepted_fields, $default_order = 'ASC' ){
		return apply_filters( 'em_events_build_sql_orderby', parent::build_sql_orderby($args, $accepted_fields, get_option('dbem_events_default_order')), $args, $accepted_fields, $default_order );
	}
	
	/* 
	 * Adds custom Events search defaults
	 * @param array $array
	 * @return array
	 * @uses EM_Object#get_default_search()
	 */
	function get_default_search( $array = array() ){
		$defaults = array(
			'orderby' => get_option('dbem_events_default_orderby'),
			'order' => get_option('dbem_events_default_order'),
			'bookings' => false, //if set to true, only events with bookings enabled are returned
			'status' => 1, //approved events only
			'format_header' => '', //events can have custom html above the list
			'format_footer' => '', //events can have custom html below the list
			'town' => false,
			'state' => false,
			'country' => false,
			'region' => false,
			'blog' => get_current_blog_id(),
		);
		if(is_multisite()){
			global $bp;
			//echo "<pre>"; print_r($bp); echo "</pre>";
			if( !empty($bp->current_component) && $bp->current_component == 'events' && !empty($bp->current_action)){
				$array['blog'] = false; //This is the buddypress root blog so we also show all event data
			}elseif( !is_main_site() ){
				//not the main blog, force single blog search
				$array['blog'] = get_current_blog_id();
			}elseif( empty($array['blog']) && get_site_option('dbem_ms_global_events') ) {
				$array['blog'] = false;
			}
		}
		if( is_admin() ){
			//figure out default owning permissions
			$defaults['owner'] = !current_user_can('edit_others_events') ? get_current_user_id() : false;
			if( !array_key_exists('status', $array) && current_user_can('edit_others_events') ){
				$defaults['status'] = false; //by default, admins see pending and live events
			}
		}
		return apply_filters('em_events_get_default_search', parent::get_default_search($defaults,$array), $array, $defaults);
	}

	//TODO Implement object and interators for handling groups of events.
    public function rewind(){
        reset($this->events);
    }
  
    public function current(){
        $var = current($this->events);
        return $var;
    }
  
    public function key(){
        $var = key($this->events);
        return $var;
    }
  
    public function next(){
        $var = next($this->events);
        return $var;
    }
  
    public function valid(){
        $key = key($this->events);
        $var = ($key !== NULL && $key !== FALSE);
        return $var;
    }
}
?>