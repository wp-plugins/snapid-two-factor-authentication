<?php

// SnapID REST path
define('SN_REST2', 'https://secure.textkey.com/SnapID/Handlers/SnapIDJsonAPIV2');

// Constants
define('SN_RESULT_ERROR', 0);
define('SN_RESULT_TIMEUP', 1);
define('SN_RESULT_VALIDATED', 2);
define('SN_RESULT_WAITING', 3);

// matchtouser status values
define('SN_NOMATCH', 0);
define('SN_MATCH', 1);
define('SN_NOTFOUND', -1);
define('SN_EXCEPTION', -2);
define('SN_DATAFAILURE', -3);

// Class object for all SnapID Requests
class SnapID {

	// Application Settings
	private $CustomerID;
	private $ApplicationID;
	private $ApplicationSubID;

	// Handle setting up the default values
	public function __construct($CustomerID = "", $ApplicationID = "", $ApplicationSubID = "") {
		// Set the Customer information
		$this->CustomerID = $CustomerID;
		$this->ApplicationID = $ApplicationID;
		$this->ApplicationSubID = $ApplicationSubID;
	}

	public function sendAPIRequest($url, $postdata) {
		// Setup
		$error_msg = "API error - no JSON payload";

		// Handle the API request via CURL
		$curl = curl_init($url);

		// Set the CURL params and make sure it is a JSON request
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_POST, true);
		curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type:application/json'));
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);  // Wildcard certificate
		curl_setopt($curl, CURLOPT_COOKIEJAR, 'cookie.txt');// set where cookies will be stored
		curl_setopt($curl, CURLOPT_COOKIEFILE, 'cookie.txt');// from where it will get cookies

		$response = curl_exec($curl);

		curl_close($curl);

		// Handle the payload
		$snapid_payload = json_decode($response);
		$snapid_result = $snapid_payload;

		// Handle the return object
		if (!($snapid_result)) {
			$snapid_result = new stdclass();
			$snapid_result->errordescr = $error_msg;
		}

		return $snapid_result;
	}

	/* JSON inputs and outputs
	**
	** For all methods, status is true if successful, false if not. errordescr is blank if status=true, non-blank if status=false
	**
	** If an invalid method is invoked or a valid method is invoked with an invalid parameter name, the response will be:
	** { "method":"InvalidMethod","errordescr":"errordescr","status":false }
	**
	** If a parameter error such as a missing parameter, is present on any method, the response will be:
	** { "method":"method","errordescr":"errordescr","status":false }
	** The value of the method parameter will be the method that was invoked.
	*/

	/*
	** Tests of various SnapID join scenarios:
	**
	** =============================================================================
	**
	** Simple success on new join.
	**
	** Join Initiation:
	**
	** Request:  { "method":"join","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","pin":"","ownershipphrase":"","invitekey":"","emailaddress":"bob@myemail.com","ipaddress" :"68.104.196.231" }
	** Response: { "method":"join","errordescr":"","status":true,"joincode":"5928240","keycheckid":"74197c97-919a-4d49-82f5-90f682f283c3","tocode":"48510" }
	**
	** Keycheck: No MO (i.e. text was not sent yet)
	**
	** Request:  { "method":"checkjoin","keycheckid":"74197c97-919a-4d49-82f5-90f682f283c3" }
	** Response: { "method":"checkjoin","errordescr":"","status":true,"keyreceived":false }
	**
	** Keycheck: MO (i.e. text was received)
	**
	** Request:  { "method":"checkjoin","keycheckid":"74197c97-919a-4d49-82f5-90f682f283c3" }
	** Response: { "method":"checkjoin","errordescr":"","status":true,"keyreceived":true }
	**
	** Note: MO Check not shown in following examples for space reasons as there are no variations in this API call
	**
	** Get user proxy information
	**
	** Request:  { "method":"getuserproxy","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","joincode":"5928240" }
	** Response: { "method":"getuserproxy","errordescr":"","status":true,"userproxy":"b50f114a-2c31-4b5a-8201-d4e070271adf" }
	**
	** =============================================================================
	**
	** Attempt to join a number that is already joined
	**
	** Request:  { "method":"join","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","pin":"","ownershipphrase":"","invitekey":"","emailaddress":"bob@myemail.com","ipaddress" :"68.104.196.231" }
	** Response: { "method":"join","errordescr":"","status":true,"joincode":"0594740","keycheckid":"952373b0-fda6-4f2c-91ad-472370566b2b","tocode":"48510" }
	**
	** Request:  { "method":"getuserproxy","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","joincode":"0594740" }
	** Response: { "method":"getuserproxy","errordescr":"","status":true,"userproxy":"b50f114a-2c31-4b5a-8201-d4e070271adf","userwasalreadyjoined":true,"emailupdated":false }
	**
	** =============================================================================
	**
	** Attempt to join a number that is already joined but using a different email
	**
	** Request:  { "method":"join","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","pin":"","ownershipphrase":"","invitekey":"","emailaddress":"mary@myemail.com","ipaddress" :"68.104.196.231" }
	** Response: { "method":"join","errordescr":"","status":true,"joincode":"1971955","keycheckid":"4876ca45-6683-4e36-8fc4-a885939ec9b6","tocode":"48510" }
	**
	** Request:  { "method":"getUserProxy","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","joincode":"1971955" }
	** Response: { "method":"getuserproxy","errordescr":"","status":true,"userproxy":"b50f114a-2c31-4b5a-8201-d4e070271adf","userwasalreadyjoined":true,"emailupdated":true }
	**
	** =============================================================================
	**
	** Attempt to join a number that has not texted in the join code, texted in an invalid join code or texted in after expiration of the original join code record
	**
	** Request:  { "method":"getUserProxy","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","joincode":"0594741" }
	** Response: { "method":"getuserproxy","errordescr":"Join code not found for this application","status":false,"userproxy":"","userwasalreadyjoined":false,"emailupdated":false }
	**
	** =============================================================================
	*/

	/*
	** Handle the join request
	**
	** For method="join"
	** { "method":"join","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","pin":"pinval","ownershipphrase":"ownershipphraseval","invitekey":"invitekeyval", “emailaddress”:"emailaddressval","ipaddress":"ipaddressval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** pin is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** ownershipphrase is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** invitekey is an advanced item for corporate systems that cannot be used with basic systems. Leave blank.
	** emailaddress is required unless the user has registered previously and stored their email address. If you are doing a join with auto-registration, it will always be required since no email can previously exist. Safe way to play it; Always get an email address!.
	** ipaddress is the IP address of the end user who is trying to log on to your system. It is not the IP address of your server! IP address may be blank, but if it is, your web site cannot be accessed via SnapID from locations outside the United States. The reason is that the messaging systems of different countries have different access codes. If SnapID does not know where you are, it cannot send you the correct access information. All common web servers make the IP Address easily available in their variable collection.
	**
	** Example: { "method":"join","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","pin":"","ownershipphrase":"","invitekey":"", “emailaddress”:rfoster@textpower.com,"ipaddress":"68.104.196.131" }
	**
	** Response
	** { "method":"join","errordescr":"errordescrval","status":"statusval","joincode":" joincodeval","keycheckid":"keycheckidval","tocode","tocodeval" }
	**
	** joincode is a 7 digit number in string form. This is the key that the user should send to the indicated tocode. Do not delete any leading zeros! You will also use the joincode later to retrieve your userproxy. Do not store it on the client! Protect it on the server!
	** keycheckid is a GUID that is used to poll for a response to your join request. Keycheckid can be safely used on the client side as it is always unique, un-guessable, has limited access and expires quickly.
	** tocode is the SMS address that the JoinCode is to be sent to. It may be anywhere from 5 digits to 13 depending upon what country the person trying to join is currently physically located in.
	**
	** Example: { "method":"join","errordescr":"","status":true, "joincode":" 7175729","keycheckid":"8e1f65d2-5ef8-42fc-81e4-5fa9abc7bd18","tocode","48510" }
	**
	*/
   	public function perform_join($pin = '',
   								 $ownershipphrase = '',
   								 $invitekey = '',
   								 $emailaddress,
   								 $ipaddress = '') {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("join"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'pin' => urlencode($pin),
				'ownershipphrase' => urlencode($ownershipphrase),
				'invitekey' => urlencode($invitekey),
				'emailaddress' => urlencode($emailaddress),
				'ipaddress' => urlencode($ipaddress)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the checkjoin request
	**
	** For method="checkjoin"
	** { "method":"checkjoin","keycheckid":"keycheckidval" }
	**
	** keycheckid is the GUID returned from the join call. You may execute this call server side. They keycheckid will remain valid for 3 minutes only.
	**
	** Example: { "method":"checkjoin","keycheckid":"d145b0e1-aa55-4049-ab27-411a2f290f21" }
	**
	** Response
	** { "method":"checkjoin","errordescr":"errordescrval","status":"statusval","keyreceived":"keyreceivedval" }
	**
	** keyreceived - if false then keep polling till time runs out. Total time limited is recommended to be 2 minutes. This is over double the normal response time.
	**
	** Example:
	**     { "method":"checkjoin","errordescr":"","status":true, "keyreceived":false }
	**     { "method":"checkjoin","errordescr":"","status":true, "keyreceived":true }
	**     { "method":"checkjoin","errordescr":"Description of error","status":false, "keyreceived":true }
	**
	*/
	public function perform_checkJoin($keycheckid) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
					'method' => urlencode("checkjoin"),
					'keycheckid' => urlencode($keycheckid)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the getuserproxy request
	**
	** For method="getuserproxy"
	** { "method":"getuserproxy","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval"," appsubid ":"appsubidval","joincode":"joincodeval" }
	**
	** Execute only after the checkjoin has indicated that the key was received.
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** joincode is the joincode returned by the join method
	**
	** Example: { "method":"getuserproxy","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280"," appsubid ":"","joincode":"7175729" }
	**
	** Response
	** { "method":"getuserproxy","errordescr":"errordescrval","status":"statusval","userproxy":"userproxyval","userwasalreadyjoined": true/false, "emailupdated": true/false }
	**
	** userproxy is the a unique identifier for the user (i.e. returned from the join request). If an unsuccessful join occurs, it will be blank.
	** userwasalreadyjoined is a Boolean. If false, the user was added as a new user. If true, the user had joined already.
	** emailupdated is a Boolean. If true the email parameter on the join was different than the current stored email value and that value was updated to the new one. If false, the existing email was not changed.
	**
	** Example:
	**     { "method":"getuserproxy","errordescr":"","status":true, "userproxy":"80bbbaa7-3041-46ab-a06c-460f26d169a2","userwasalreadyjoined": false, "emailupdated": false }
	**     { "method":"getuserproxy","errordescr":"Description of error","status": false, "userproxy":"","userwasalreadyjoined": false, "emailupdated": false }
	**
	*/
   	public function perform_getUserProxy($joincode) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("getuserproxy"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'joincode' => urlencode($joincode)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the issuesnapidchallenge request
	**
	** For method="issuesnapidchallenge"
	** { "method":"issuesnapidchallenge","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","deviceid":"deviceidval","pin":"pinval","ownershipphrase":"ownershipphraseval","ipaddress":"ipaddressval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** deviceid is the identifier for the device that the user is using. This applies only to the Durable Logins feature which is not available for the basic system. Use the entire browser identifier string for the identity.
	** pin is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** ownershipphrase is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** ipaddress is the IP address of the end user who is trying to log on to your system. It is not the IP address of your server! IP address may be blank, but if it is, your web site cannot be accessed via SnapID from locations outside the United States. The reason is that the messaging systems of different countries have different access codes. If SnapID does not know where you are, it cannot send you the correct access information. All common web servers make the IP Address easily available in their variable collection.
	**
	** Example: { "method":"issueSnapIDChallenge","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"" , "deviceid":"","pin":"","ownershipphrase":"","ipaddress":""}
	**
	** Response
	**  { "method":"issuesnapidchallenge","errordescr":"errordescrval","status":"statusval","loginaccessidentifier":"loginaccessidentifierval","applicationtitle":"applicationtitleval","snapidkey","snapidkeyval","validationcode":"validationcodeval","keycheckid":"keycheckidval","tocode","tocodeval" ,"userproxy":"userproxyval"}
	**
	** loginaccessidentifier is a quantity which you will need to echo back in the final step. Save it server side only for that step.
	** applicationtitle is the name of the application that you gave to SnapID when you set it up.
	** snapidkey is the SnapID code that must be sent to the tocode that is listed.
	** validationcode is a supplementary verification code that can be used for additional server side authentication if the authentication process is split between the client and server sides. In these examples, all the authentication is server side and you can ignore this value.
	** keycheckid is a GUID used to poll for the reception of the SnapID key. Use this in the next step.
	** tocode is the SMS code to which the user will text their SnapID key. It is determined by the IP address you sent in.
	** userproxy is the userproxy to use if you are configured to use the Durable Login process. Since in the basic process, you cannot use Durable Logins in a basic configuration, the userproxy will be blank.
	**
	** Example:  { "method": "issuesnapidchallenge", "errorDescr" : "", "status": true, "loginaccessidentifier":"1087CB52-2FAF-443C-91EC-74BBEC767FB1", "applicationtitle":"My #1 Application","snapidkey":"1345864","validationcode": "65ocde","keycheckid":"8C0C3510-4BE3-42BC-8D30-AB174E4ED5C0","tocode":"48510" ,"userproxy": ""}
	*/
	public function perform_issueSnapIDChallenge($deviceid = '',
												 $pin = '',
												 $ownershipphrase = '',
				   								 $ipaddress = '') {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
					'method' => urlencode("issuesnapidchallenge"),
					'customeridentifier' => urlencode($this->CustomerID),
					'applicationidentifier' => urlencode($this->ApplicationID),
					'appsubid' => urlencode($this->ApplicationSubID),
					'deviceid' => urlencode($deviceid),
					'pin' => urlencode($pin),
					'ownershipphrase' => urlencode($ownershipphrase),
					'ipaddress' => urlencode($ipaddress)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the checkkey request
	**
	** For method="checkkey"
	** { "method":"checkkey","keycheckid":"keycheckidval”}
	**
	** keycheckid is the key received in the issuesnapidchallenge API call
	**
	** Keep polling to a maximum of 2 minutes from the initial issuesnapidchallenge call.
	*
	** Example: { "method":"checkkey","keycheckid":"8C0C3510-4BE3-42BC-8D30-AB174E4ED5C0”}
	**
	** Response
	**  { "method":"checkkey","errordescr":"errordescrval","status":"statusval","keyreceived":"keyreceivedval" }
	**
	** keyreceived - if false then keep polling till time runs out. Total time limited is recommended to be 2 minutes. This is over double the normal response time.
	**
	** Example:
	**    { "method":"checkkey","errordescr":"","status": true, "keyReceived", true }
	**    { "method":"checkkey","errordescr":"","status": true, "keyReceived", false }
	*/
	public function perform_checkKey($keycheckid) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
					'method' => urlencode("checkkey"),
					'keycheckid' => urlencode($keycheckid)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the matchtouser request
	**
	** For method="matchtouser"
	** { "method":"matchtouser","customeridentifier":"customeridentifierval","loginaccessidentifier":"loginaccessidentifierval","appsubid":"appsubidval","snapidkey":"snapidkeyval”, “keycheckid” : “keycheckidval”, “emailaddress”: "emailaddressval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** loginaccessidentifier is that returned by the previous issnapidchallenge method.
	** snapidkey is that returned by the previous issnapidchallenge method.
	** keycheckid is that returned by the previous issnapidchallenge method.
	** pin is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** ownershipphrase is an advanced authentication item that cannot be used with basic systems. Leave blank.
	** emailaddress is optional. If your application supports auto-registration, an email address must be recorded. If the customer is already registered from version 2, an email address will exist and the email address can be blank. If the customer is already registered from a version 1 application, an email may or may not be present. If it is not present, login will fail. If an email address is present and a different email is given, the existing email will not be altered. The customer will need to go to the SnapID registration page to change it. If your application does not support auto-registration, the emailaddress parameter may be left blank.
	**
	** Example: { "method":"matchtouser", “customeridentifier” : “d5c748a1-3ebd-4593-9fd7-b8227f171207”, "loginaccessidentifier":"1087CB52-2FAF-443C-91EC-74BBEC767FB1","appsubid":"","snapidkey":"1345864”, “keycheckid” : “8C0C3510-4BE3-42BC-8D30-AB174E4ED5C0”, “pin”: “”, “ownershipphrase” : “”, “emailaddress”: "users%40textpower.com" }
	**
	** Response
	** { "method":"matchtouser","errordescr":"errordescrval","status":"statusval","userexists":"userexistsval","userproxy": userproxyval" }
	**
	** userexists is an Enum for the status of the match:
	**      0 = No Match. Error.
	**      1 = Match. Success
	**     -1 = SnapID key not found. Error.
	**     -2 = Exception
	**     -3 = Data Failure. Means that either the SnapIDKey or the KeycheckID is wrong.
	**     -4 = Tried to create an auto-registration but an invalid email was sent in on the emailaddress parameter
	**     -5 = SnapIDKey is either expired or already redeemed
	**     -11 = Pin Code required but supplied Pin Code does not match user’s Pin Code in registration
	**     -12 = Ownership phrase required but supplied Ownership phrase does not match user’s Ownership phrase in registration
	**     -20 = LoginAccessIdentifier not found
	**     -30 = Internal failure in attempting to mark fraud
	**     -31 = Internal failure on creating the join
	**     -32 = Failed update of transaction record to redeem the SnapID key
	**     -41 = Insert into UserRegistration table failed when doing autoregistration
	**     -42 = Data Insert error attempting to join user to application.
	** userproxy is the proxy number for the correct user. If userexists is not = 1, userproxy is blank.
	**
	** Example: { "method":"matchtouser","errordescr":"","status":true, "userexists": 1, "userproxy":"02d03b63-becc-437d-bc66-5c63173fd340" }
	**
	*/
	public function perform_matchToUser($loginaccessidentifier,
										$snapidkey,
										$keycheckid,
										$pin = '',
										$ownershipphrase = '',
										$emailaddress = '') {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
					'method' => urlencode("matchtouser"),
					'customeridentifier' => urlencode($this->CustomerID),
					'appsubid' => urlencode($this->ApplicationSubID),
					'loginaccessidentifier' => urlencode($loginaccessidentifier),
					'snapidkey' => urlencode($snapidkey),
					'keycheckid' => urlencode($keycheckid),
					'pin' => urlencode($pin),
					'ownershipphrase' => urlencode($ownershipphrase),
					'emailaddress' => urlencode($emailaddress),
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the remove request
	**
	** The remove method removes a user from a customer and application.
	**
	** For method="remove"
	** { "method":"remove","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval" ,"userproxy":"userproxyval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** userproxy provides a unique identifier of the customer and is a GUID
	**
	** Example: { "method":"putfields","errordescr":"","status":true","userid":"Myuid","password":"Mypwd","suppl1":"","suppl2":"","suppl3":"345"}
	**
	** Response
	** { "method":"remove","errordescr":"errordescrval","status":"statusval"}
	**
	** errordescr is blank if no error occurred.
	** status is true if user was removed, false if not removed
	**
	** Example:
	**    { "method":"remove","errordescr":"","status":true" } - if a user was removed
	**    { "method":"remove","errordescr":"","status":false" } - if a user was not removed
	*/
    public function perform_remove($userproxy) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("remove"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'userproxy' => urlencode($userproxy)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the getfields request
	**
	** The getFields method retrieves the 5 listed regular storage fields for the listed user
	** in a particular customer and application.
	**
	** For method="getfields"
	** { "method":"getfields","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","userproxy":"userproxyval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** userproxy provides a unique identifier of the customer and is a GUID
	**
	** Example: { "method":"getfields","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","userproxy":"7AF4D2D2-D4F3-42F0-A7C0-4A44D835F6BB " }
	**
	** Response
	** { "method":"getfields","errordescr":"errordescrval","status":"statusval","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","userproxy":"userproxyval","userid":"useridval" ,"password":"passwordval","suppl1":"suppl1data","suppl2":"suppl2data","suppl3":"suppl3data"}
	**
	** errordescr is blank if no error occurred.
	**
	** Example: { "method":"getfields","errordescr":"","status":true","userid":"Myuid","password":"Mypwd","suppl1":"","suppl2":"","suppl3":"345"}
	*/
    public function perform_getFields($userproxy) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("getFields"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'userproxy' => urlencode($userproxy)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the putfields request
	**
	** The putfields allows the client to store information in any of the 5 regular storage fields for the listed user in a particular customer and application.
	**
	** For method="putfields"
	** { "method":"putfields","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","userproxy":"userproxyval","userid":"useridval" ,"password":"passwordval,"suppl1":"suppl1data","suppl2":"suppl2data","suppl3":"suppl3data"}
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** userproxy provides a unique identifier of the customer and is a GUID
	** userid, password and the first three supplementary fields.
	** If the contents of the field is %#%, no data will be stored for that field.
	** All fields are returned with any new data in them regardless of whether new data was stored.
	**
	** Field Sizes
	**
	** userid – 50 unicode characters
	** password – 50 unicode characters
	** suppl1 -100 unicode characters
	** suppl2 -100 unicode characters
	** suppl3 – 100 unicode characters
	**
	** General Guidelines
	**
	** If you have an existing store of userids and passwords, you could store them in any two of the fields using fields of appropriate length. Then when you get your userproxy back, you just retrieve the userid and password from the supplementary store.
	** You have database keys where your existing login information is located. You could store the DB keys and go directly to your existing user database.
	** You want to store information about users or other information in a JSON string and then retrieve the JSON for some other purpose.  You can do this if you follow one simple rule. Since the SnapID APIs use double quotes for their parameters, be sure to only use single quotes inside any double quote to prevent parsing troubles.
	** As a general rule, do not use double quotes inside any data string that you submit. Escape them to something else.
	**
	** Example: { "method":"putfields","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","userproxy":"7AF4D2D2-D4F3-42F0-A7C0-4A44D835F6BB","userid":"Myuid","password":"Mypwd","suppl1":"","suppl2":"","suppl3":"345"}
	**
  	** Response
	** { "method":"putfields","errordescr":"errordescr","status":"statusval","customeridentifier":"customeridentifier","applicationidentifier":"applicationidentifier","appsubid":"appsubid","userproxy":"userProxy"}
	**
	** errordescr is blank if no error occurred.
	**
	** Example: { "method":"putfields","errordescr":"","status":true","userid":"Myuid","password":"Mypwd","suppl1":"","suppl2":"","suppl3":"345"}
	*/
    public function perform_putFields($userproxy,
    								  $userid,
    								  $password = '',
    								  $suppl1 = '',
    								  $suppl2 = '',
    								  $suppl3 = '') {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("putfields"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'userproxy' => urlencode($userproxy),
				'userid' => urlencode($userid),
				'password' => urlencode($password),
				'suppl1' => urlencode($suppl1),
				'suppl2' => urlencode($suppl2),
				'suppl3' => urlencode($suppl3)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the getid request
	**
	** This method gets the long binary field(up to 1MB) that can contain bio-metric information or other ID information
	**
	** For method="getid"
	** { "method":"getid","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","userproxy":"userproxyval" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** userproxy provides a unique identifier of the customer and is a GUID
	**
	** Example: { "method":"getid","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","userproxy":"7AF4D2D2-D4F3-42F0-A7C0-4A44D835F6BB" }
	**
	** Response
	** { "method":"getid","errordescr":"errordescr","status":"statusval","customeridentifier":"customeridentifier","applicationidentifier":"applicationidentifier","appsubid":"appsubid","userproxy":"userProxy","data":"data"}
	**
	** Example: { "method":"putfields","errordescr":"","status":true","userid":"Myuid","password":"Mypwd","suppl1":"","suppl2":"","suppl3":"345"}
	**
	** errordescr is blank if no error occurred.
	** The data is returned as base64 encoded data
	**
	** Example: { "method":"getid","errordescr":"","status":true","data":"{'abc': 123, 'def':'345' }" }
	**
	*/
    public function perform_getid($userproxy) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("getid"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'userproxy' => urlencode($userproxy)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}

	/*
	** Handle the putId request
	**
	** This method saves the long binary field(up to 1MB) that can contain bio-metric information or other ID information
	**
	** For method="putId"
	** { "method":"putId","customeridentifier":"customeridentifier","applicationidentifier":"applicationidentifier","appsubid":"appsubid","userproxy":"userproxyval","data":"data" }
	**
	** customeridentifier is the string representation of your customer identifier GUID
	** applicationidentifier is the string representation of your application identifier GUID
	** appsubid is blank unless this user will have multiple login identities on this application. Otherwise, it is the unique string to identify which appsubid should be joined.
	** userproxy provides a unique identifier of the customer and is a GUID
	** data is the data to be saved (i.e. JSON formatted data)
	**
	** Field Sizes
	**
	** $data – 1MB of Unicode text or 0.5 MB of binary data in base64 format.
	**
	** General Guidelines
	**
	** You want to store information about users or other information in a JSON string and then retrieve the JSON for some other purpose.  You can do this if you follow one simple rule. Since the SnapID APIs use double quotes for their parameters, be sure to only use single quotes inside any double quote to prevent parsing troubles.
	** As a general rule, do not use double quotes inside any data string that you submit. Escape them to something else.
	**
	** Example: { "method":"putid","customeridentifier":"33458682-F393-4130-80C0-63346AA00BDD","applicationidentifier":"4142ad58-ccb9-4cb2-a090-ef8f58b4e280","appsubid":"","userproxy":"7AF4D2D2-D4F3-42F0-A7C0-4A44D835F6BB","data":"{'abc': 123, 'def':'345' }"}
	**
	** Response
	** { "method":"putid","errordescr":"errordescr","status":"statusval","customeridentifier":"customeridentifierval","applicationidentifier":"applicationidentifierval","appsubid":"appsubidval","userproxy":"userproxyval","data":"dataval"}
	**
	** errordescr is blank if no error occurred.
	** The data returned in the data field is the new data.
	**
	** Example: { "method":"putid","errordescr":"","status":true","data":"{'abc': 123, 'def':'345' }" }
	**
	*/
    public function perform_putId($userproxy,
    							  $data) {
		// Setup
		$error_msg = "";
		$url = SN_REST2;

		// Setup data
		$postdata = json_encode(
			array(
				'method' => urlencode("putId"),
				'customeridentifier' => urlencode($this->CustomerID),
				'applicationidentifier' => urlencode($this->ApplicationID),
				'appsubid' => urlencode($this->ApplicationSubID),
				'userproxy' => urlencode($userproxy),
				'data' => urlencode($data)
			),
		JSON_PRETTY_PRINT);

		// Handle the API Call
		return $this->sendAPIRequest($url, $postdata);
	}
}
?>
