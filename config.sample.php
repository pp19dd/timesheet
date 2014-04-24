<?php

ob_start( "ob_gzhandler" );	// disable to switch off compression

// probably important to set
define( "CONFIG_TIMEZONE", 			"America/New_York" );


// database uses PDO
define( "CONFIG_HOSTNAME", 			"localhost" );
define( "CONFIG_TABLE", 			"entries" );
define( "CONFIG_DATABASE", 			"@@database@@" );
define( "CONFIG_USERNAME", 			"@@username@@" );
define( "CONFIG_PASSWORD", 			"@@password@@" );


// pay cycles, in two week intervals, from these dates
// define( "CONFIG_START_TIMESHEET",	"2013-01-11" );
// define( "CONFIG_START_PAYDAY",		"2013-01-04" );
define( "CONFIG_START_TIMESHEET",	"2013-01-10" );
define( "CONFIG_START_PAYDAY",		"2013-01-03" );
