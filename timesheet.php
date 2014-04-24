<?php

// ==========================================================================================
// handles the database stuff, helper functions
// ==========================================================================================

class Timesheet {
	var $title = "Timesheet";
	
	var $db;
	var $hostname = "localhost";
	var $database = "timesheet";
	var $table = "entries";
	var $username = "username";
	var $password = "password";

	var $ts;
	var $prev;
	var $next;
	var $today;
	var $week;
	
	var $reminder_start_timesheet;
	var $reminder_start_payday;
	
	var $lookup = array();
	
	function __construct() {
		$this->prev = new stdClass();
		$this->next = new stdClass();

		/*
		$ts = strtotime("2013-01-01 00:00:00");
		for( $h = 0; $h <= 23; $h++ ) {
			for( $m = 0; $m < 60; $m++ ) {
				$ts_f = strtotime("+{$h} hour +{$m} minute", $ts);
				$key = "{$h}:{$m}";
				$this->lookup[$key] = date("g:i a", $ts_f );
			}
		}
		*/
	}
	
	function shift_end_default($ts) {
		return( "+8 hour +45 minute" );
	}
	
	function shift_end($ts) {
		return( $this->shift_end_default($ts) );
	}
	
	function save_data() {

		// insert row if one does not exist
		$stmt = $this->db->prepare( "select * from {$this->table} where day=?" );
		$stmt->execute( array($_POST['selected_date']) );
		$r = $stmt->fetchAll();
		if( empty($r) ) {
			$stmt = $this->db->prepare( "insert into {$this->table} (day) values (?)" );
			$stmt->execute( array($_POST['selected_date']) );
		}
		
		if( $_POST['selected_mode'] == 'in' ) {
			$time_field = 'time_in';
		} else {
			$time_field = 'time_out';
		}


		// set time
		$stmt = $this->db->prepare( 
			"update {$this->table} set {$time_field}=?, telework=?, note=? where day=?"
		);
		$stmt->execute(
			array(
				(isset($_POST['skip_time']) ? null : "{$_POST['hour']}:{$_POST['minute']}"),
				(isset($_POST['telework']) ? 'y' : 'n'),
				$_POST['note'],
				$_POST['selected_date']
			)
		);

/*		
		if( isset( $_POST['skip_time'] ) ) {
			
			// unset time
			$stmt = $this->db->prepare( "update {$this->table} set {$time_field}=? where day=?" );
			$stmt->execute( array(null, $_POST['selected_date']) );
			
		} else {
		
		
		}
*/		
		
		/*echo "<PRE>";
		print_r( $r );
		print_r( $_GET );
		print_r( $_POST );
		die;
		*/
	}
	
	function after_connect() {
		
		// navigation start
		$this->ts = strtotime("Last Sunday");
		if( isset( $_GET['date'] ) ) $this->ts = strtotime( $_GET['date'] );
		$this->today = date("Y-m-d", $this->ts );

		// compute sql-format next/prev dates
		$this->prev->ts = strtotime( "-7 day", $this->ts );
		$this->prev->day = date("Y-m-d", $this->prev->ts );

		$this->next->ts = strtotime( "+7 day", $this->ts );
		$this->next->day = date("Y-m-d", $this->next->ts );

		// render week
		$this->week = $this->get_week( $this->today );
	}

	function display_day( $entry ) {
		
		$day_length = 0;
		
		// if signed in, compute + display exit time:
		if( !is_null( $entry->time_in ) ) {

			// if NOT signed out, expected time
			if( is_null( $entry->time_out ) ) {

				$ts_day_started = strtotime("{$entry->day} {$entry->time_in}");
				$ts_day_should_end = strtotime($this->shift_end($ts_day_started), $ts_day_started);
				
				echo date("g:i a", $ts_day_should_end );
				
			} else {
				// if signed out, time in between

				$day_length = strtotime($entry->time_out) - strtotime($entry->time_in);
				
				echo $this->human($day_length);
			}
		} # end if signed in

		// telework?
		if( $entry->telework == 'y' ) echo " TW";
		
		// display any plain text notes (AL, SL, etc)
		if( strlen(trim($entry->note)) > 0 ) echo " {$entry->note}";
		
		return( $day_length );
	}
	
	function get_day_classes( $today_ts, $day ) {
		$classes = array( "day_" . strtolower(date("D", $today_ts)) );
		if( date("Y-m-d") == $day ) $classes[] = "day_today";

		if( $this->is_nth_day( $this->reminder_start_timesheet, $today_ts, 14 ) ) $classes[] = "day_timesheet";
		if( $this->is_nth_day( $this->reminder_start_payday, $today_ts, 14 ) ) $classes[] = "day_payday";
		return( $classes );
	}
	
	function human($seconds) {
		$minutes = $seconds / 60;
		$hours = $minutes / 60;

		return( round($hours,2) );
	}

	function is_nth_day( $start, $current, $interval ) {
		$days_diff = date("z", $current) - date("z", $start );
		$remainder = $days_diff / $interval;
		
		if( $remainder == intval($remainder) ) return( true );
		
		return( false );
	}
	
	function check_install() {
		$stmt = $this->db->prepare( "describe {$this->table}" );
		$stmt->execute();
		$r = $stmt->fetchAll();
		if( empty( $r ) ) $this->offer_install();
	}
	
	function offer_install() {
	
		echo "<p>Database connection seems to work, but database/table is not created.</p>";
		echo "<p>Dump this into MySQL:</p>";
		echo "<PRE><blockquote style='background-color:rgb(220,220,220); padding:1em'>";
		
echo "CREATE DATABASE `{$this->database}` DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci;

CREATE TABLE IF NOT EXISTS `{$this->database}`.`{$this->table}` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `day` date NOT NULL,
  `time_in` time DEFAULT NULL,
  `time_out` time DEFAULT NULL,
  `telework` enum('y','n') COLLATE utf8_unicode_ci NOT NULL DEFAULT 'n',
  `note` varchar(50) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `day` (`day`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1;";

		echo "</blockquote></PRE>";
		die;
	
	}

	function connect() {

		try {

			$this->db = new PDO(
				"mysql:host={$this->hostname};dbname={$this->database}",
				$this->username, 
				$this->password 
			);
			
		} catch( Exception $e ) {
			
			echo "<h4>Error connecting to MySQL</h4>";
			
			echo "<PRE>";
			print_r( $e );
			die;
		}
		
		$this->check_install();
		
		$this->after_connect();
	}

	function get_day( $day ) {
		$stmt = $this->db->prepare( "select * from {$this->table} where day=?" );
		$stmt->execute( array( $day ) );
		$r = $stmt->fetchAll( PDO::FETCH_CLASS );
		return( $r );
	}

	function get_week( $starting_day ) {
		$r = array();
		$start_timestamp = strtotime( $starting_day );
		
		for( $i = 0; $i < 7; $i++ ) {
			$day = date("Y-m-d", strtotime( "+{$i} day", $start_timestamp ) );
			$r[$day] = array_shift( $this->get_day( $day ) );
		}
		return( $r );
	}
	
	
}
