<?php
# verify php build
include('functions/checks/check_php_build.php');		# check for support for PHP modules and database connection
define("TOOLKIT_PATH", '/var/www/html/phpipam/functions/php-saml/');
require_once(TOOLKIT_PATH . '_toolkit_loader.php');   // We load the SAML2 lib

//get SAML2 settings from db 
$dbobj=$Tools->fetch_object("usersAuthMethod", "type", "SAML2");
if(!$dbobj){
echo "SAML settings not found in database";
die();
}
//decode authentication module params
$params=json_decode($dbobj->params);
//if using advanced settings, instantiate without db settings
if($params->advanced=="1"){
	$auth = new OneLogin_Saml2_Auth(); 
}else{

	$settings = array (
        'sp' => array (
            'entityId' => $Tools->createURL(),
            'assertionConsumerService' => array (
                'url' => $Tools->createURL()."/saml2",
            ),
            'singleLogoutService' => array (
                'url' => $Tools->createURL(),
            ),
            'NameIDFormat' => 'urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified',
        ),
        'idp' => array (
            'entityId' => $params->idpissuer,
            'singleSignOnService' => array (
                'url' => $params->idplogin,
            ),
            'singleLogoutService' => array (
                'url' => $params->idplogout,
            ),
            'certFingerprint' => $params->idpcertfingerprint,
	        'certFingerprintAlgorithm' => $params->idpcertalgorithm,
        ),
    );	
	$auth = new OneLogin_Saml2_Auth($settings); 
}
//if SAMLResponse is not in the request, create an authnrequest and send it to the idp
if(!isset($_POST["SAMLResponse"])){
	$ssoBuiltUrl = $auth->login(null, array(), false, false, true);
	$_SESSION['AuthNRequestID'] = $auth->getLastRequestID();
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, must-revalidate');
	header('Location: ' . $ssoBuiltUrl);
	exit();
}else{
//process the authentication response
	if (isset($_SESSION) && isset($_SESSION['AuthNRequestID'])) {
	    $requestID = $_SESSION['AuthNRequestID'];
	} else {
	    $requestID = null;
	}
	
	$auth->processResponse($requestID);

	$errors = $auth->getErrors();
	
	if (!empty($errors)) {
	    print_r('<p>'.implode(', ', $errors).'</p>');
	    exit();
	}
	
	if (!$auth->isAuthenticated()) {
	    echo "<p>Not authenticated</p>";
	    exit();
	}
	
	// try to authenticate in phpipam
	$User->authenticate ($auth->getNameId(), '');
	// Redirect user where he came from, if unknown go to dashboard.
	if( isset($_COOKIE['phpipamredirect']) )    { header("Location: ".$_COOKIE['phpipamredirect']); }
	else                                        { header("Location: ".create_link("dashboard")); }

}