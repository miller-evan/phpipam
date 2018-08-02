<?php

# verify that user is logged in
$User->check_user_session(true, true);

# if 2fa is not needed redirect to /
if ($User->twofa_required()===false) {
	header("Location:".$url.create_link (null));
}
# generate and print code
else {
	# init class
	require_once (dirname(__FILE__)."/../../../functions/GoogleAuthenticator/PHPGangsta/GoogleAuthenticator.php");
	$ga = new PHPGangsta_GoogleAuthenticator();
	// create secret
	$secret = $ga->createSecret($User->settings->{'2fa_length'});
	// get QR code
	$username = strtolower($User->user->username)."@".$User->settings->{'2fa_name'};
	$qrCodeUrl = $ga->getQRCodeGoogleUrl($username, $secret);

	// save secret to DB
	try {
		$Admin = new Admin ($Database, false);
		$Admin->object_modify ("users", "edit", "id", ["id"=>$User->user->id, "2fa_secret"=>$secret]);
	}
	catch (exception $e) {
		$Result->show ("danger", $e->getMessage());
	}

	// set HTML content
	$html = [];
	$html[] = '<div id="login">';
	$html[] = '<div class="loginForm row">';
	$html[] = '	<div>';
	$html[] = '		<legend style="margin-top:10px;">'._('Two-factor authentication details').'</legend>';
	$html[] = '	</div>';
	$html[] = '	<div>';
	$html[] = '		'._('Details for your Google Authenticator are below. Please write down your details, otherwise you will not be able to login to phpipam');
	$html[] = '		<div style="border: 2px dashed red;margin:20px;padding: 10px" class="text-center row">';
	$html[] = '			<div class="col-xs-12" style="padding:5px 10px 3px 20px;"><strong>'._('Account').': <span style="color:red; font-size: 16px">'.$username.'</span></strong></div>';
	$html[] = '			<div class="col-xs-12" style="padding:0px 10px 3px 20px;"><strong>'._('Secret').' : <span style="color:red; font-size: 16px">'.$secret.'</span></strong></div>';
	$html[] = '		</div>';
	$html[] = '		<div class="text-center">';
	$html[] = '		<hr>'._('You can also scan followign QR code with Google Authenticator application').':<br><br>';
	$html[] = '			<img src="'.$qrCodeUrl.'" style="margin:auto"><br>';
	$html[] = '		</div>';
	$html[] = '		<div class="text-right" style="margin-top:10px;">';
	$html[] = '			<hr><a class="btn bt-sm btn-default" href="'.$url.create_link (null).'">'._('Validate').'</a>';
	$html[] = '		</div>';
	$html[] = '	</div>';
	$html[] = '</div>';
	$html[] = '</div>';

	print implode("\n", $html);
}