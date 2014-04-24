<?php
require_once( "config.php" );
require_once( "timesheet.php" );

date_default_timezone_set( CONFIG_TIMEZONE );


// changing to zero lunch
// lame hack, these rules should go
// somewhere into web-config
class timesheet2014 extends Timesheet {
	function shift_end($ts) {
		if( $ts < strtotime( "2014-04-25" ) ) return( $this->shift_end_default($ts) );
		
		return( "+8 hour" );
	}
}

$timesheet = new timesheet2014();

// ==========================================================================================
// located in config
// ==========================================================================================
$timesheet->hostname = 	CONFIG_HOSTNAME;
$timesheet->database = 	CONFIG_DATABASE;
$timesheet->table = 	CONFIG_TABLE;
$timesheet->username = 	CONFIG_USERNAME;
$timesheet->password = 	CONFIG_PASSWORD;

// ==========================================================================================
// pay period of this year, helps mark payday / timesheet due date
// ==========================================================================================
$timesheet->reminder_start_timesheet = strtotime(CONFIG_START_TIMESHEET);
$timesheet->reminder_start_payday = strtotime(CONFIG_START_PAYDAY);
// ==========================================================================================

// try the database thing - needs PDO
$timesheet->connect();

// save data if needed
if( isset( $_GET['save'] ) && isset( $_POST['button_submit'] ) ) {
	$timesheet->save_data();
	header("location:?" . (isset( $_GET['date'] ) ? "date={$_GET['date']}" : '') );
	die;
}

?>
<!doctype html>
<html>
<head>
<title>Timesheet: <?php echo $timesheet->today ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" />
<style type="text/css">
body, html { margin:0; padding:0; font-family: Arial; height:100% }
.week { border-collapse: collapse }
.week td { font-size: 14px; text-align: center }
.day { width:60px }
.time_in, .time_out { width:80px; }

th.time_in, td.time_in { border-left: 2px solid black }
th.time_out, td.time_out { border-right: 2px solid black }

.day_word, .day_word_longer { font-weight: bold; font-size:1.1em }
.day_word_longer { display: none }
.day_date { font-size:0.9em }

.day_sun, .day_sat { background-color: silver }
.day_today { background-color: #FFD27F }

.day_payday .day { background-color: #AABF50 }
.day_timesheet .day { background-color: #DD9BDD }


.navigation td { text-align: center; width: 33% }
.navigation button {  }

@media all and (orientation:landscape) {
   .day_word { display: none }
   .day_word_longer { display: block }
   .day { width: 120px }
   .time_in, .time_out { width: 160px }
}

#id_week, #id_edit {
	transition: transform 0.5s;
	-webkit-transition: -webkit-transform 0.5s;
}

td.time_in, td.time_out {
	transition: scale 1s;
	-webkit-transition: -webkit-scale 1s;
}

.div_edit { }
.div_edit h1, .div_edit h2 { font-size:18px; color: gray }
#id_edit_mode.in { }
#id_edit_mode.out { text-align: right}
.div_edit_inner { padding:10px }

#id_minute, #id_hour { width:150px; font-size:1.5em }

#clock_container { margin-top: 50px }
#telework_container { margin-top: 10px; font-size:1.1em }
#id_skip_time { margin-left: 20px }

#save_container { clear: both; margin-top: 30px; text-align: center }

#note_container p { margin:0; }
#note_container { margin-top: 10px }
#id_note { font-size:1.1em; width:280px }

#id_button_submit { width: 140px; padding:20px; font-size:1.5em}


</style>

<!--
-->
<script type="text/javascript" src="jquery.2.0.3.min.js"></script>
<script type="text/javascript" src="hammer.min.js"></script>
<script type="text/javascript">

/*
$ = function(id) {
	return( document.getElementById(id) );
}
*/

$(document).ready( function() {

	var lookup = <?php echo json_encode($timesheet->lookup); ?>;

	function flashover( after, trans ) {
		$("#id_week").css({
			transform: "translate(" + trans + ")"
		});
		setTimeout( after, 300 );
		//$("td.time_in, td.time_out, div.day_date, td.total, th.weekly_total").animate( { opacity: 0 }, 100, after );
		//$("#id_week").animate({
		
		//}, 100, after);
	}

	Hammer($("#id_week")[0], { prevent_default: true }).on("swipeleft", function(ev) {
		flashover( function() {
			window.location = '?date=<?php echo $timesheet->next->day ?>';
		}, "-400px,0px");
	}).on("swiperight", function(ev) {
		flashover( function() {
			window.location = '?date=<?php echo $timesheet->prev->day ?>';
		}, "400px,0px");
	}).on("swipedown", function(ev) {
		flashover( function() {
			window.location = '?';
		}, "0px,500px");
	});
	
	
	Hammer($("#id_edit")[0]).on("dragleft dragright dragup dragdown", function(ev){ ev.gesture.preventDefault(); })
	
	Hammer($("#id_edit")[0], { prevent_default: false }).on("swipedown", function(ev) {
		/* flashover( function() {		}, "-400px,0px");*/
		
		$("#id_week").css({ transform: "translate(0px,0px)" });
		$("#id_edit").css({ transform: "translate(0px,0px)" });
		
	});

	function edit_node( node ) {
		var day = node.getAttribute("day");					// ex: 2013-08-01
		var day_e = node.getAttribute("day_e");				// ex: Wednesday
		var clock_type = node.getAttribute("clock_type");	// ex: "in" or "out"
		var val = node.getAttribute("field");
		var tw = node.getAttribute("telework");				// y or n
		var note = node.getAttribute("note");				// ex: SL, AL, etc
		
		var hour = <?php echo date("G") ?>;					// ex: 17
		var minute = <?php echo date("i") ?>;				// ex: 5
		
		if( val.length > 0 ) {
			var hm = val.split(":");
			hour = parseInt(hm[0]);							// ex: 8
			minute = parseInt(hm[1]);						// ex: 30
		}

		
		$("#id_week").css({ transform: "translate(0,-440px)" });
		$("#id_edit").css({ transform: "translate(0,-440px)" });
		
		// node.innerHTML = hour + ":" + minute;
		$("#id_selected_date").val( day );
		$("#id_selected_mode").val( clock_type );
		
		$("#id_edit_title").html( "Editing " + day + " (" + day_e + ")" );
		$("#id_edit_mode").html( "Signing " + clock_type );
		$("#id_edit_mode").removeClass("in").removeClass("out").addClass(clock_type);
		
		$("#id_hour").val( hour );
		$("#id_minute").val( minute );
		$("#id_telework").prop( "checked", (tw == 'y' ? true : false) );
		$("#id_skip_time").prop( "checked", false );
		$("#id_note").val( note );
		
	}
	
	function scan_nodes( nodes ) {
		for( i = 0; i < nodes.length; i++ )(function(el) {
			Hammer( el, { prevent_default: true }).on("doubletap", function(ev) {
				edit_node( el );
			});
		})(nodes[i]);
	}

	scan_nodes( $(".time_in, .time_out") );
	
});

</script>

</head>
<body>

<table id="id_week" class="week" width="100%" height="100%" border="1">
<thead>
<tr>
	<th class="day"></th>
	<th class="time_in">In</th>
	<th class="time_out">Out</th>
	<th class="total"></th>
</tr>
</thead>
<tbody>
<?php

$total = 0;

foreach( $timesheet->week as $day => $entry ) { 

	$today_ts = strtotime( $day );
	$classes = $timesheet->get_day_classes( $today_ts, $day );
	
	$time_in = ""; $time_out = ""; $time_in_f = ""; $time_out_f = ""; $tw = 'n'; $note = '';
	
	
	if( is_object( $entry ) ) {
		$tw = $entry->telework;
		$note = $entry->note;
	
		if( !is_null($entry->time_in) ) {
			$time_in = date("g:i a", strtotime("{$entry->day} {$entry->time_in}") );
			$time_in_f = $entry->time_in;
		}
		if( !is_null($entry->time_out) ) {
			$time_out = date("g:i a", strtotime("{$entry->day} {$entry->time_out}") );
			$time_out_f = $entry->time_out;
		}
	}

?>
<tr class="<?php echo implode(" ", $classes ) ?>">
	<td class="day"><div class="day_word"><?php echo date("D", $today_ts) ?></div><div class="day_word_longer"><?php echo date("l", $today_ts) ?></div><div class="day_date"><?php echo date("n/d", $today_ts) ?></div></td>
	<td class="time_in"  clock_type="in"  telework="<?php echo $tw ?>" day="<?php echo $day ?>" day_e="<?php echo date("l", $today_ts) ?>" field="<?php echo $time_in_f ?>" note="<?php echo $note ?>"><?php echo $time_in ?></td>
	<td class="time_out" clock_type="out" telework="<?php echo $tw ?>" day="<?php echo $day ?>" day_e="<?php echo date("l", $today_ts) ?>" field="<?php echo $time_out_f ?>" note="<?php echo $note ?>"><?php echo $time_out ?></td>
	<td class="total"><?php

if( is_object( $entry ) ) {
	$total += $timesheet->display_day( $entry );
}

?></td>
</tr>
<?php } ?>
</tbody>
<tfoot>
<tr>
	<th colspan="3"></th>
	<th class="weekly_total"><?php echo $timesheet->human($total) ?></th>
</tr>
</tfoot>
</table>

<div id="id_edit" class="div_edit">
<form method="post" action="?<?php if( isset( $_GET['date'] ) ) { ?>date=<?php echo $_GET['date'] ?><?php } ?>&save">

	<input type="hidden" id="id_selected_date" name="selected_date" value="" />
	<input type="hidden" id="id_selected_mode" name="selected_mode" value="" />

	<div class="div_edit_inner">
		<h1 id="id_edit_title">editing</h1>
		
		<h2 id="id_edit_mode">sign in/out</h2>

		<table id="clock_container" width="100%">
			<tr>
				<th style="width:50%">Hour</th>
				<th style="width:50%">Minute</th>
			</tr>
			<tr>
				<td style="width:50%">
					<select name="hour" id="id_hour">
<?php for( $i = 0; $i <= 23; $i++ ) { ?><option value="<?php echo $i ?>"><?php echo date("g a", strtotime("{$i}:00")) ?></option><?php } ?>
					</select>
				</td>
				<td style="width:50%">
					<select name="minute" id="id_minute">
<?php for( $i = 0; $i <= 59; $i++ ) { ?><option value="<?php echo $i ?>"><?php echo date("i", strtotime("00:{$i}")) ?></option><?php } ?>
					</select>
				</td>
			</tr>
		</table>
		
		<div id="telework_container">
			<label for="id_telework"><input name="telework" id="id_telework" type="checkbox"/> Telework</label>
			<label for="id_skip_time"><input name="skip_time" id="id_skip_time" type="checkbox"/> Erase time?</label>
		</div>
		
		<div id="note_container">
			<p>Note:</p>
			<input type="text" autocomplete="off" name="note" id="id_note" />
		</div>
		
		<div id="save_container">
			<input type="submit" name="button_submit" id="id_button_submit" value="Save" />
		</div>
	</div>
</form>
</div>


</body>
</html>
