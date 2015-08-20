<?php

/**
 *	Site settings
 **************************/

/* functions */
require( dirname(__FILE__) . '/../../../functions/functions.php');

# initialize user object
$Database 	= new Database_PDO;
$User 		= new User ($Database);
$Admin	 	= new Admin ($Database);
$Result 	= new Result ();

# verify that user is logged in
$User->check_user_session();

// validations
if(strlen($_POST['name'])==0)			{ $Result->show("danger", "Invalid database name", true); }
if(strlen($_POST['port'])==0)			{ $_POST['port'] = 3306; }
elseif (!is_numeric($_POST['port']))	{ $Result->show("danger", "Invalid port number", true); }

// formulate json
$values = new StdClass ();

$values->host 		= $_POST['host'];
$values->name 		= $_POST['name'];
$values->username 	= $_POST['username'];
$values->password 	= $_POST['password'];
$values->port 		= $_POST['port'];

// get old settings for defaults
$old_values = json_decode($User->settings->powerDNS);

$values->ns			= $old_values->ns;
$values->hostmaster	= $old_values->hostmaster;
$values->refresh 	= $old_values->refresh;
$values->retry 		= $old_values->retry;
$values->expire 	= $old_values->expire;
$values->nxdomain_ttl = $old_values->nxdomain_ttl;
$values->ttl 		= $old_values->ttl;

# set update values
$values = array("id"=>1,
				"powerDNS"=>json_encode($values),
				);
if(!$Admin->object_modify("settings", "edit", "id", $values))	{ $Result->show("danger",  _("Cannot update settings"), true); }
else															{ $Result->show("success", _("Settings updated successfully"), true); }
?>