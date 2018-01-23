<?php
/*
 * Print graph of Top IPv4 / IPv6 hosts by percentage
 *
 * 		Inout must be IPv4 or IPv6!
 **********************************************/

# required functions
if(!is_object(@$User)) {
	require( dirname(__FILE__) . '/../../../functions/functions.php' );
	# classes
	$Database	= new Database_PDO;
	$User 		= new User ($Database);
	$Tools 		= new Tools ($Database);
	$Subnets 	= new Subnets ($Database);
	$Result		= new Result ();
}

# user must be authenticated
$User->check_user_session ();

# no errors!
//ini_set('display_errors', 0);

# set size parameters
$height = 200;
$slimit = 10;			//we dont need this, we will recalculate

# if direct request include plot JS
if($_SERVER['HTTP_X_REQUESTED_WITH']!="XMLHttpRequest")	{
	# get widget details
	if(!$widget = $Tools->fetch_object ("widgets", "wfile", $_GET['section'])) { $Result->show("danger", _("Invalid widget"), true); }
	# reset size and limit
	$height = 350;
	$slimit = 20;
	# include flot JS
	print '<script language="javascript" type="text/javascript" src="js/'.SCRIPT_PREFIX.'/flot/jquery.flot.js"></script>';
	print '<script language="javascript" type="text/javascript" src="js/'.SCRIPT_PREFIX.'/flot/jquery.flot.categories.js"></script>';
	print '<!--[if lte IE 8]><script language="javascript" type="text/javascript" src="js/'.SCRIPT_PREFIX.'/flot/excanvas.min.js"></script><![endif]-->';
	# and print title
	print "<div class='container'>";
	print "<h4 style='margin-top:40px;'>$widget->wtitle</h4><hr>";
	print "</div>";
}

# get subnets statistic
$type = 'IPv4';

include "top10_hosts_lib.php";
