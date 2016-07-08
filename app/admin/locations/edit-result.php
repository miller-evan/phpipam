<?php

/* functions */
require( dirname(__FILE__) . '/../../../functions/functions.php');

# initialize user object
$Database 	= new Database_PDO;
$User 		= new User ($Database);
$Admin	 	= new Admin ($Database);
$Result 	= new Result ();

# verify that user is logged in
$User->check_user_session();

# strip input tags
$_POST = $Admin->strip_input_tags($_POST);

# validate csrf cookie
$User->csrf_cookie ("validate", "location", $_POST['csrf_cookie']) === false ? $Result->show("danger", _("Invalid CSRF cookie"), true) : "";

# validations
if($_POST['action']=="delete" || $_POST['action']=="edit") {
    if($Admin->fetch_object ('locations', "id", $_POST['id'])===false) {
        $Result->show("danger",  _("Invalid Location object identifier"), false);
    }
}
if($_POST['action']=="add" || $_POST['action']=="edit") {
    // name
    if(strlen($_POST['name'])<3)                                            {  $Result->show("danger",  _("Name must have at least 3 characters"), true); }
    // lat, long
    if($_POST['action']!=="delete") {
        // lat
        if(strlen($_POST['lat'])>0) {
            if(!preg_match('/^(\-?\d+(\.\d+)?).\s*(\-?\d+(\.\d+)?)$/', $_POST['lat'])) { $Result->show("danger",  _("Invalid Latitude"), true); }
        }
        // long
        if(strlen($_POST['long'])>0) {
            if(!preg_match('/^(\-?\d+(\.\d+)?).\s*(\-?\d+(\.\d+)?)$/', $_POST['long'])) { $Result->show("danger",  _("Invalid Longitude"), true); }
        }
    }
}

// set values
$values = array(
    "id"=>@$_POST['id'],
    "name"=>$_POST['name'],
    "lat"=>$_POST['lat'],
    "long"=>$_POST['long'],
    "description"=>$_POST['description']
    );

# execute update
if(!$Admin->object_modify ("locations", $_POST['action'], "id", $values))   { $Result->show("danger",  _("Location $_POST[action] failed"), false); }
else																	    { $Result->show("success", _("Location $_POST[action] successful"), false); }

?>