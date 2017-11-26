<?php

/**
 *	phpIPAM API
 *
 *		please visit http://phpipam.net/api-documentation/ on how to use API
 *
 *		To implement:
 *
 * 		http://www.restapitutorial.com/resources.html
 * 			Querying, Filtering and Pagination
 * 			Limiting Results
 * 			Pagination
 * 			Filtering
 * 			Sorting
 * 			versioning
 *
 */

# include funtions
require( dirname(__FILE__) . '/../functions/functions.php');		// functions and objects from phpipam
require( dirname(__FILE__) . '/controllers/Common.php');			// common methods
require( dirname(__FILE__) . '/controllers/Responses.php');			// exception, header and response handling

# settings
$enable_authentication = true;
$aes_compliant_crypt   = false;         // Default to false for backward compatibility. Use true to use AES-256 compliant RIJNDAEL algorythm (rijndael-128)
$time_response         = true;          // adds [time] to response
$lock_file             = "";            // (optional) file to write lock to

# database and exceptions/result object
$Database = new Database_PDO;
$Tools    = new Tools ($Database);
$Response = new Responses ();

# get phpipam settings
if(SETTINGS===null)
$settings = $Tools->fetch_object ("settings", "id", 1);

# set empty controller for options
if($_SERVER['REQUEST_METHOD']=="OPTIONS") {
	if( !isset($_GET['controller']) || $_GET['controller']=="")	{ $_GET['controller'] = "Tools"; }
}

/* wrap in a try-catch block to catch exceptions */
try {
	// start measuring
	$start = microtime(true);

	/* Validate application ---------- */

	// verify that API is enabled on server
	if($settings->api!=1) 									{ $Response->throw_exception(503, "API server disabled");}

	# fetch app
	$app = $Tools->fetch_object ("api", "app_id", $_GET['app_id']);

	// verify app_id
	if($app === false) 										{ $Response->throw_exception(400, "Invalid application id"); }
	// check that app is enabled
	if($app->app_permissions==="0") 						{ $Response->throw_exception(503, "Application disabled"); }


	/* Check app security and prepare request parameters ---------- */

	// crypt check
	if($app->app_security=="crypt") {
		// verify php extensions
		foreach (array("mcrypt") as $extension) {
	    	if (!in_array($extension, get_loaded_extensions()))
	    													{ $Response->throw_exception(500, 'php extension '.$extension.' missing'); }
		}
		// decrypt request - form_encoded
        if(strpos($_SERVER['CONTENT_TYPE'], "application/x-www-form-urlencoded")!==false) {
        	$decoded = trim(mcrypt_decrypt($aes_compliant_crypt?MCRYPT_RIJNDAEL_128:MCRYPT_RIJNDAEL_256, $app->app_code, base64_decode($_GET['enc_request']), MCRYPT_MODE_ECB));
        	$decoded = $decoded[0]=="?" ? substr($decoded, 1) : $decoded;
			parse_str($decoded, $params);
			$params = (object) $params;
        }
        // json_encoded
		else {
			$params = json_decode(trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $app->app_code, base64_decode($_GET['enc_request']), MCRYPT_MODE_ECB)));
		}
	}
	// SSL checks
	elseif($app->app_security=="ssl") {
		// verify SSL
		if (!((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443)) {
															{ $Response->throw_exception(503, 'App requires SSL connection'); }
		}
		// save request parameters
		$params = (object) $_GET;
	}
	// no security
	elseif($app->app_security=="none") {
		$params = (object) $_GET;
	}
	// error, invalid security
	else {
		$Response->throw_exception(503, 'Invalid app security');
	}


	// append POST parameters if POST or PATCH
	if($_SERVER['REQUEST_METHOD']=="POST" || $_SERVER['REQUEST_METHOD']=="PATCH" || $_SERVER['REQUEST_METHOD']=="DELETE") {
		// if application tupe is JSON (application/json)
        if(strpos($_SERVER['CONTENT_TYPE'], "application/json")!==false){
            $rawPostData = file_get_contents('php://input');
            $json = json_decode($rawPostData,true);
            if(is_array($json))
            $params = array_merge((array) $params, $json);
            $params = (object) $params;
        }
		// if application tupe is XML (application/json)
        elseif(strpos($_SERVER['CONTENT_TYPE'], "application/xml")!==false){
            $rawPostData = file_get_contents('php://input');
            $xml = $Response->xml_to_array($rawPostData);
            if(is_array($xml))
            $params = array_merge((array) $params, $xml);
            $params = (object) $params;
        }
		//if application type is default (application/x-www-form-urlencoded)
        elseif(sizeof(@$_POST)>0) {
            $params = array_merge((array) $params, $_POST);
            $params = (object) $params;
        }
        //url encoded input
        else {
            // input
            $input = file_get_contents('php://input');
            if (strlen($input)>0) {;
                parse_str($input, $out);
                if(is_array($out)) {
                    $params = array_merge((array) $params, $out);
                    $params = (object) $params;
                }
            }
        }
    }

	/* Authentication ---------- */

	// authenticate user if required
	if (@$params->controller != "user" && $enable_authentication) {
		if($app->app_security=="ssl" || $app->app_security=="none") {
			// start auth class and validate connection
			require( dirname(__FILE__) . '/controllers/User.php');				// authentication and token handling
			$Authentication = new User_controller ($Database, $Tools, $params, $Response);
			$Authentication->check_auth ();
		}
	}


	/* verify request ---------- */

	// check if the request is valid by checking if it's an array and looking for the controller and action
	if( $params == false || isset($params->controller) == false ) {
		$Response->throw_exception(400, 'Request is not valid');
	}
	// verify permissions for delete/create/edit if controller is not user (needed for auth)
	if (@$params->controller != "user") {
    	if( ($_SERVER['REQUEST_METHOD']=="POST" || $_SERVER['REQUEST_METHOD']=="PATCH"
    	  || $_SERVER['REQUEST_METHOD']=="PUT"  || $_SERVER['REQUEST_METHOD']=="DELETE"
    	  )
    	  && $app->app_permissions<2) {
    		$Response->throw_exception(401, 'invalid permissions');
    	}
	}
	// verify content type
	$Response->validate_content_type ();


	/* Initialize controller ---------- */

	// get the controller and format it correctly
	$controller 	 = ucfirst(strtolower($params->controller))."_controller";
	$controller_file = ucfirst(strtolower($params->controller));

	// check if the controller exists. if not, throw an exception
	if( file_exists( dirname(__FILE__) . "/controllers/$controller_file.php") ) {
		require( dirname(__FILE__) . "/controllers/$controller_file.php");
	}
	// check custom controllers
	elseif( file_exists( dirname(__FILE__) . "/controllers/custom/$controller_file.php") ) {
		require( dirname(__FILE__) . "/controllers/custom/$controller_file.php");
	}
	else {
		$Response->throw_exception(400, 'Invalid controller');
	}

	// create a new instance of the controller, and pass
	// it the parameters from the request and Database object
	$controller = new $controller($Database, $Tools, $params, $Response);

	// pass app params for links result
	$controller->app = $app;

	// Unmarshal the custom_fields JSON object into the main object for
	// POST and PATCH. This only works for controllers that support custom
	// fields and if the app has nested custom fields enabled, otherwise
	// this is skipped.
	if (strtoupper($_SERVER['REQUEST_METHOD']) == 'POST' || strtoupper($_SERVER['REQUEST_METHOD']) == 'PATCH') {
		$controller->unmarshal_nested_custom_fields();
	}

	// check if the action exists in the controller. if not, throw an exception.
	if( method_exists($controller, strtolower($_SERVER['REQUEST_METHOD'])) === false ) {
		$Response->throw_exception(501, $Response->errors[501]);
	}

	// if lock is enabled wait until it clears
	if( $app->app_lock==1 && strtoupper($_SERVER['REQUEST_METHOD'])=="POST") {
    	// set transaction lock file name
    	$controller->set_transaction_lock_file ($lock_file);

    	// check if locked form previous process
    	while ($controller->is_transaction_locked ()) {
        	// max ?
        	if ((microtime(true) - $start) > $app->app_lock_wait) {
            	$Response->throw_exception(503, "Transaction timed out after $app->app_lock_wait seconds because of transaction lock");
        	}
        	// add random delay
        	usleep(rand(250000,500000));
    	}

    	// add new lock
    	$controller->add_transaction_lock ();
    	// execute the action
    	$result = $controller->{$_SERVER['REQUEST_METHOD']} ();
    }
    else {
    	// execute the action
    	$result = $controller->{$_SERVER['REQUEST_METHOD']} ();
    }

    // remove transaction lock
    if(is_object($controller) && $app->app_lock==1 && strtoupper($_SERVER['REQUEST_METHOD'])=="POST") {
        if($controller->is_transaction_locked ()) {
            $controller->remove_transaction_lock ();
        }
    }
} catch ( Exception $e ) {
	// catch any exceptions and report the problem
	$result = $e->getMessage();

	// set flag if it came from Result, just to be sure
	if($Response->exception!==true) {
		$Response->exception = true;
		$Response->result['success'] = 0;
		$Response->result['code'] 	 = 500;
		$Response->result['message'] = $result;
	}

    // remove transaction lock
    if(is_object($controller) && $app->app_lock==1 && strtoupper($_SERVER['REQUEST_METHOD'])=="POST") {
        if($controller->is_transaction_locked ()) {
            $controller->remove_transaction_lock ();
        }
    }
}

// stop measuring
$stop = microtime(true);

// add stop time
if($time_response) {
    $time = $stop - $start;
}

//output result
echo $Response->formulate_result ($result, $time, $app->app_nest_custom_fields, $controller->custom_fields);

// exit
exit();