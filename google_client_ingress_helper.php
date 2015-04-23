<?php

/*
 * Set $googleDocs as true in your main PHP file for the functions to work.
 */

$libs_dir = '';

require_once $libs_dir . 'debug.php';

/*
 * Globals
 *
 * The globals below are only required if $googleDocs is enabled
 *
 * Other globals required in the main PHP file are the following:
 * $html
 */
$client = '';
$accessToken = '';
$accessTokenJson = '';
$accessTokenObject = '';
$refreshToken = '';
$listFeed = '';

function googleDocs_is_user_authenticated() {

	global $googleDocs;
	if ( !$googleDocs ) return false;

	global $client;
	if ( ( $client != '' ) && ( $client->getAccessToken() ) ) {
		return true;
	}
	else {
		return false;
	}
}

function googleDocs_set_session_access_token() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $client;
	$_SESSION['access_token'] = $client->getAccessToken();
}

function googleDocs_create_auth_url() {

	global $googleDocs;
	if ( !$googleDocs ) return false;

	global $authUrl;
	global $client;
	$authUrl = $client->createAuthUrl();

	return $authUrl;

}

function googleDocs_authenticate() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $client;
	global $this_uri;

        $client = new Google_Client();
        $client->setScopes("https://spreadsheets.google.com/feeds https://docs.google.com/feeds"); // It's OK to have a space separated list

        $client->setApplicationName("Ingress Portal Spreadsheet");

        // Visit https://code.google.com/apis/console?api=plus to generate your
        // client id, client secret, and to register your redirect uri.
        /* Service Account */
        //$client->setClientId('863606867969-duikph99ecq2jt0c4sfdg2e653p3umam.apps.googleusercontent.com');

        /* Web Application */
        $client->setClientId('863606867969-uncl7nim8581lj7s8lvt4e6n8hecauj9.apps.googleusercontent.com');
        $client->setClientSecret('ch6VxvRkwF5SadJIIfaqVUw2');
        $client->setRedirectUri($this_uri);
        $client->setDeveloperKey('AIzaSyB-9nHW-se4-CkPoN283Wwq7lpa9zbc2Jk');

}

function googleDocs_authenticate_step_two() {
	
	global $googleDocs;
	if ( !$googleDocs ) return;

	global $client;
	$client->authenticate($_GET['code']);
	debug( 'Authenticated (2) And redirecting...', 'success' );
	googleDocs_set_session_access_token();
	header( 'Location: http://' . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'] );
}

function googleDocs_set_access_token() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $client;
	debug( "Session access token set.  Storing that information: '{$_SESSION['access_token']}'...", 'success' );
	$client->setAccessToken($_SESSION['access_token']);
	debug( 'Done' );
}

function googleDocs_display_access_token_status() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $html;
	global $client;
        $html = $html . "<div><p>Access token status</p></div>";
        $html = $html . "<div><table>";
        $html = $html . "<tr><td>Token</td><td>{$_SESSION['access_token']}</td></tr>";
        $html = $html . "<tr><td>isAccessTokenExpired</td><td>{$client->isAccessTokenExpired()}</td></tr>";
        $html = $html . "<tr><td>getScopes</td><td>".print_r($client->getScopes(), true)."</td></tr>";
        $html = $html . "<tr><td>clientID</td><td>{$client->getClientId()}</td></tr>";
        $html = $html . "<tr><td>clientSecret</td><td>{$client->getClientSecret()}</td></tr>";
        $html = $html . "</table></div>";

        debug( $html );
}

function googleDocs_set_token_vars() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $libs_dir;
        require_once $libs_dir . '/php-google-spreadsheet-client/src/Google/Spreadsheet/Autoloader.php';

        debug("Attempting to access Spreadsheets... ");

	global $accessToken;
	global $accessTokenJson;
	global $accessTokenObject;
	global $refreshToken;
	global $client;

        $accessTokenJson = $client->getAccessToken();
        $accessTokenObject = json_decode( $accessTokenJson );
        // Automatically refresh access token
        $refreshToken = $accessTokenObject->refresh_token;
        if ( $client->isAccessTokenExpired() ) {
                debug( 'Refreshing Access Token...' );
                $client->refreshToken( $refreshToken );
                $accessTokenJson = $client->getAccessToken();
                $accessTokenObject = json_decode( $accessTokenJson );
                debug( 'Done', 'success' );
        }
        $accessToken = $accessTokenObject->access_token;

        // Force refresh if 'refresh' is anywhere in the REQUEST
        if ( isset( $_REQUEST['refresh'] )) {
                debug( 'Force token refresh' );
                $client->refreshToken($refreshToken);
                $accessTokenJson = $client->getAccessToken();
                $accessTokenObject = json_decode( $accessTokenJson );
                $accessToken = $accessTokenObject->access_token;
                $refreshToken = $accessTokenObject->refresh_token;
        }
}

function googleDocs_connect_to_spreadsheet( $spreadsheetName, $worksheetName ) {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $accessToken;
	global $listFeed;

        debug( "Trying accessToken '$accessToken'..." );
        $request = new Google\Spreadsheet\Request($accessToken);
        $serviceRequest = new Google\Spreadsheet\DefaultServiceRequest($request);
        Google\Spreadsheet\ServiceRequestFactory::setInstance($serviceRequest);
        debug( 'Success!', 'success' );

        // Find and connect to spreadsheet
        $spreadsheetService = new Google\Spreadsheet\SpreadsheetService();
        $spreadsheetFeed = $spreadsheetService->getSpreadsheets();
        $spreadsheet = $spreadsheetFeed->getByTitle( $spreadsheetName );

        // Get all worksheets
        $worksheetFeed = $spreadsheet->getWorksheets();

        // Get the worksheet
        $worksheet = $worksheetFeed->getByTitle( $worksheetName );

        // Get all the rows
        $listFeed = $worksheet->getListFeed();

        /* Sample code */
        /*
        // Get all the rows
        $listFeed = $worksheet->getListFeed();

        // Loop over the rows
        foreach ($listFeed->getEntries() as $entry) {
        $values = $entry->getValues();
        echo '<p>';
        print_r($values);
        echo '</p>';
        }
        */

        // Insert row
        /*
        $row = array(
                'portalname' => 'Test',
            'recharge' => 'Test2',
                'ownsince' => '1/1/2014',
                'daysowned' => '',
                'zone' => 'Test3',
                'kmfromhome' => '999',
                'favorite' => '',
                'risk' => '10',
                'access' => '0',
                'record' => '0',
                'strategy' => 'none',
                'days' => 'some formula',
                'days_2'=> 'a formula',
                'dididiscoverit' => '',
                'enemies' => '',
                'allies' => '',
                'address' => '',
                'link' => '',
                'latitude' => '',
                'longitude' => '',
                'computationstuff' => 'formula',
                'computationstuff_2' => 'formula',
                'computationstuff_3' => 'formula'
        );

        $listFeed->insert($row);
        */

        // Display all the rows in the spreadsheet
        if ((isset($_REQUEST['test'])) && ($_REQUEST['test'] == 'read')) {

                // Loop over the rows
                foreach ($listFeed->getEntries() as $entry) {
                        $values = $entry->getValues();
                        echo '<p>';
                        print_r($values);
                        echo '</p>';
                }
        }
}

function googleDocs_update_portal() {

	global $googleDocs;
	if ( !$googleDocs ) return;

	global $listFeed;
	global $portal;
	global $row;
	global $threshold;

	//Insert OR Update, depending on guid
	$entries = $listFeed->getEntries();

        //Search
        $found = false;
        foreach ($listFeed->getEntries() as $entry) {
		$values = $entry->getValues();
                if ($values['guid'] == $portal->guid) {
                        $listEntry = $entry;
                        $found = true;
                        break;
                }
	}

        $result = '';
        if ($found) {
                debug( 'Merging' );
                //Update
                $old_row = $listEntry->getValues(); // Note the data here is not htmlspecialchars
                //Determine if the new row is much newer
                $old_row_date = $old_row['updatetime'];
                $row_date = $row['updatetime'];
                $intel_is_newer = (( $old_row_date + ( $threshold * 1000 )) < $row_date );
                if ( $portal->team->displayName == "Neutral" ) {
                        $intel_is_newer = true;
                        debug( 'Neutral portals are always "newer".' );
                }
                debug( 'intel_is_newer = ' . $intel_is_newer );

		//Merge
                foreach ( $old_row as $key => $cell ) {
                        //Compare old and new, replace with best data
                        //If using the old, re-encode it first
                        debug( "Key: $key | Cell: $cell" );
                        if ( !( isset( $row[ $key ] ))) $row[ $key ] = htmlspecialchars( $cell );
                        if ( !( $intel_is_newer )) $row[ $key ] = htmlspecialchars( $cell );
                        //Special cases
                        //Portal name - Always keep *longer* portal names
                        if (( $key == 'portalname' ) && ( strlen( $row[ $key ] ) < strlen( $cell ))) $row[ $key ] = htmlspecialchars( $cell );
                                //Record
                                if (( $key == 'record' ) && ( $row['daysowned'] > $cell )) $row[ $key ] = $row['daysowned'];
                                //Allies & Enemies
                                $oldPortalAllies = array();
                                $portalAllies = array();
                                if ( $key == 'allies' ) {
                                        if (( $row[ 'allies' ] == '' )) {
                                                $row[ 'allies' ] = htmlspecialchars( $cell );
                                        }
                                        else {
                                                $oldPortalAllies = explode( ',', $cell );
                                                $oldPortalAllies = array_map( 'trim', $oldPortalAllies );
                                                $oldPortalAllies = array_map( 'htmlspecialchars', $oldPortalAllies );
                                                $portalAllies = explode( ',', $row[ 'allies' ] );
                                                $portalAllies = array_map( 'trim', $portalAllies );
                                                $portalAllies = array_merge( $oldPortalAllies, $portalAllies );
                                        $portalAllies = array_unique( $portalAllies );
                                        $portalAllies = array_filter( $portalAllies );
                                        $portalAlliesCommas = implode( ', ', $portalAllies );
                                        $row['allies'] = $portalAlliesCommas;
                                        }
                                }
                               if ( $key == 'enemies' ) {
                                        if (( $row[ 'enemies' ] == '' )) {
                                                $row[ 'enemies' ] = htmlspecialchars( $cell );
                                        }
                                        else {
                                                $oldPortalAllies = explode( ',', $cell );
                                                $oldPortalAllies = array_map( 'trim', $oldPortalAllies );
                                                $oldPortalAllies = array_map( 'htmlspecialchars', $oldPortalAllies );
                                                $portalAllies = explode( ',', $row[ 'enemies' ] );
                                                $portalAllies = array_map( 'trim', $portalAllies );
                                                $portalAllies = array_merge( $oldPortalAllies, $portalAllies );
                                        $portalAllies = array_unique( $portalAllies );
                                        $portalAllies = array_filter( $portalAllies );
                                        $portalAlliesCommas = implode( ', ', $portalAllies );
                                        $row['enemies'] = $portalAlliesCommas;
                                        }
                                }
                }
                $listEntry->update($row);
                $result = 'updated';
            }
            else {
                debug( 'Inserting' );
                //Insert
                $row['guid'] = $portal->guid;
                //Special Cases
                        if ( isset( $row[ 'daysowned' ] ) ) $row[ 'record' ] = $row[ 'daysowned' ];
                if ( isset( $_REQUEST['debug'] )) {
                        foreach ( $row as $key => $cell ) {
                                debug( "Key: $key | Cell: $cell" );
                        }
                }
                $listFeed->insert($row);
                $result = 'added';
            }
}
