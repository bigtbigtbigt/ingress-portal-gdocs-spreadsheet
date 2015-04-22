<?php header('Access-Control-Allow-Origin: http://www.ingress.com'); ?><?php

/*
 * Ways to call this file
 *
 * ?refresh=true&json={json from the intel page}
 *   NOTE: Will return a 1x1 pixel unless &html is specified
 *
 * ?refresh=true  (to refresh the token)
 *
 * ?logout (to logout)
 *
 * ?test=read (to read the database in)
 *
 * ?debug (to turn on debug mode)
 *
 */

/*
 * Limits
 *
 * Number of Cells: Total of 400,000 cells across all sheets
 * Number of Columns: 256 columns per sheet
 *
 * More: https://support.google.com/drive/answer/37603?hl=en
 *
 */

/*
 * TODO
 *
 * Check spreadsheet to make sure all the columns we need exist
 *
 * Way to display error messages on the spreadsheet itself
 *
 * Log and keep track of portal ownership records
 *
 * Add support for mySQL for faster execution
 *
 * Use mySQL to queue portal locations and execute separately
 *
 */

$html = '';
$threshold = 0; //seconds
$timeZoneAdjust = -18000; //EST is -5 hours from GMT

$northBoundary = 41.138538;
$eastBoundary = -73.866403;
$southBoundary = 40.711114;
$westBoundary = -74.310378;

// Enable (true) / Disable (false) Google Docs integration and (in the future) mySQL integration below
$googleDocs = true;

require_once 'debug.php';

debug( 'Requiring Google Client' ); // More details: https://github.com/google/google-api-php-client
require_once 'google_client_ingress_helper.php';
require_once 'google-api-php-client/src/Google_Client.php';
if ( $googleDocs ) {

}
else {
	debug( "WARNING: Google Docs Disabled!", 'warning' );
}

session_start();

googleDocs_authenticate();

/* Look at the $_REQUEST to see what the user wants to do */

if (isset($_REQUEST['logout'])) {
	debug('Logout is set.');
	unset($_SESSION['access_token']);
}

if (isset($_GET['code'])) {
	debug( 'Got code. About to authenticate...' );
	googleDocs_authenticate_step_two();
}

if (isset($_SESSION['access_token'])) {
	googleDocs_set_access_token();
}

if ( googleDocs_is_user_authenticated() ) {
	// We are authenticated with Google.  Continue like normal.
}
else {
	// We are not authenticated.  Disable GoogleDocs mode and proceed anyway.
	$googleDocs = false;
}

if ( $googleDocs && $client->getAccessToken() ) {

	// Connect to spreadsheet
	googleDocs_display_access_token_status();
	googleDocs_set_token_vars();
	googleDocs_connect_to_spreadsheet( 'Ingress Portals', 'Portal Listing' ); // Spreadsheet name, Worksheet name

	// Decode JSON and add data to spreadsheet
	if (isset($_REQUEST['json'])) {

		$portal = json_decode( $_REQUEST['json'] );
		// TODO check if $portal->entityType == 'portal';

        //Check that the portal is within the boundaries (this is done to prevent overloading Google)
        if ( $portal->lat > $northBoundary ) doExit( 'Portal too far north.  Exiting.' );
        if ( $portal->lat < $southBoundary ) doExit( 'Portal too far south.  Exiting.' );
        if ( $portal->lng > $eastBoundary ) doExit( 'Portal too far east.  Exiting.' );
        if ( $portal->lng < $westBoundary ) doExit( 'Portal too far west.  Exiting.' );

        //Create the $row array, which will be added to the spreadsheet
	    $row = array(
		'portalname' => htmlspecialchars( $portal->title ),
		'team' => $portal->team->displayName,
		'updatetime' => $portal->lastUpdated, // 1390070520377 = 1/18/2014
	    	'lastupdated' => gmdate("Y-m-d H:i:s \E\S\T", $portal->lastUpdated/1000 + $timeZoneAdjust ),
	    	'latitude' => $portal->lat,
	    	'longitude' => $portal->lng,
	    	'link' => "http://www.ingress.com/intel?ll={$portal->lat},{$portal->lng}&amp;z=18",
	    	'level' => $portal->level,
	    	'resonatorcount' => $portal->resonatorCount,
	    	'health' => $portal->health
	    	//,'image' => 'http:'.$portal->image
	    );

	    if ( $portal->team->displayName == "Neutral" ) {
	    	$row['lastupdated--'] = gmdate("Y-m-d H:i:s \E\S\T", $portal->lastUpdated/1000 + $timeZoneAdjust );
	    	$row['ownsince'] = '';
	    	$row['own150days'] = '';
	    	$row['own90days'] = '';
	    	$row['daysowned'] = '';
	    	$row['owner'] = 'Neutral';
	    }

	    if ( isset( $portal->capturedTimeExact )) {
	    	// $row['json'] = $_REQUEST['json']; // For debugging - make sure to add to spreadsheet
	    	$row['lastupdated--'] = gmdate("Y-m-d H:i:s \E\S\T", $portal->lastUpdated/1000 + $timeZoneAdjust );
	    	$row['ownsince'] = gmdate("Y-m-d H:i:s \E\S\T", $portal->capturedTimeExact/1000 + $timeZoneAdjust );
	    	$row['own150days'] = gmdate("Y-m-d", ( $portal->capturedTimeExact/1000 ) + 12960000 + $timeZoneAdjust );
	    	$row['own90days'] = gmdate("Y-m-d", ( $portal->capturedTimeExact/1000 ) + 7776000 + $timeZoneAdjust );
	    	$row['daysowned'] = round((( time() - $portal->capturedTimeExact/1000 ) / 86400 ) , 1 );
	    }

	    if ( isset( $portal->capturingPlayer )) {
	    	if ( $portal->capturedTime != 0 ) {
		    	$row['owner'] = $portal->capturingPlayer;
	    	}
	    }

	    //Calculate allies/enemies by looking at mods and resonators;
	    $portalAllies = array();
	    if ( isset( $portal->capturingPlayer )) {
	    	$portalAllies[] = $portal->capturingPlayer;
	    }
	    if ( isset( $portal->linkedMods )) {
	    	foreach ( $portal->linkedMods as $mod) {
	    		$portalAllies[] = $mod->installer;
	    	}
	    }
	    if ( isset( $portal->linkedResonators )) {
	    	foreach ( $portal->linkedResonators as $resonator ) {
	    		$portalAllies[] = $resonator->owner;
	    	}
	    }

	    $row[ 'enemies' ] = '';
	    $row[ 'allies' ] = '';
	    if ( count( $portalAllies ) > 0 ) {
	    	$portalAllies = array_unique( $portalAllies );
	    	$portalAlliesCommas = implode( ', ', $portalAllies );
	    	if ( $row['team'] == "Enlightened" ) {
	    		$row['allies'] = $portalAlliesCommas;
	    	}
	    	elseif ( $row['team'] == "Resistance" ) {
	    		$row['enemies'] = $portalAlliesCommas;
	    	}
	    }

        googleDocs_update_portal();

		if (isset($_REQUEST['html'])) {
			$html = $html . "<p>Data $result for {$portal->title}</p>";
		}
		else {
			if ( isset( $_REQUEST[ 'debug' ] )) {
				debug( 'SUCCESS!', 'success' );
			}
			else {
				header('Content-Type: image/gif');
				echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
				exit();
			}
		}

	}

	// The access token may have been updated lazily.
	googleDocs_set_session_access_token();

} else {

    googleDocs_create_auth_url();

}

function doExit( $message = '' ) {
	if ( isset( $_REQUEST[ 'debug' ] )) {
		debug( "$message" );
		exit();
	}
	else {
		header('Content-Type: image/gif');
		echo base64_decode('R0lGODlhAQABAJAAAP8AAAAAACH5BAUQAAAALAAAAAABAAEAAAICBAEAOw==');
		exit();
	}
}
?><!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <link rel='stylesheet' href='style.css' />
  <?php
  if ( isset( $_REQUEST[ 'debug' ] )) { ?>
  <link rel='stylesheet' href='/css/debug.css' />
  <?php	
  }
?>
</head>
<body>
<header><h1>Ingress Portal Google Docs Spreadsheet</h1></header>
<div class="box">
<?php echo $html ?>
<?php
  if(isset($authUrl)) {
    print "<a class='login' href='$authUrl'>Connect Me!</a>";
  } else {
   print "<a class='logout' href='?logout'>Logout</a>";
  }
?>
</div>
</body>
</html>
