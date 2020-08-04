<?php
include('api.php');

$config = parse_ini_file('../config.ini');

$authorize_url = $config['PROPME_AUTH'];
$token_url = $config['PROPME_TOKEN'];

//	callback URL specified when the application was defined--has to match what the application says
$callback_uri = $config['PROPME_CALL'];


//	client (application) credentials
$client_id = $config['PROPME_ID'];
$client_secret = $config['PROPME_SECRET'];


$last_request = queryDatabase("SELECT * FROM requests 
                    ORDER BY updated_at DESC
                    LIMIT 1");
if(isset($last_request)) {
    $timestamp = DateTime::createFromFormat("Y-m-d H:i:s", $last_request["updated_at"])->format("U");
} else {
    $timestamp = 1;
}
$test_api_url = $config['PROPME_API'].'/api/v1/contacts?TimeStamp='.$timestamp;



if ($_GET["code"]) {
    $access_token = getAccessToken($_GET["code"]);
    $resource = getResource($access_token);
    //save contacts to database
    foreach($resource as $contact) {
        createPropMEContact($contact);
    }

    $timestamp = (new DateTime("now"))->format("Y-m-d H:i:s");
    $desc = 'propme';
    $query = "INSERT INTO `requests`  (`updated_at` ,`created_at`, `desc`) VALUES ('$timestamp','$timestamp','$desc')";
    queryDatabase($query);
} else {
//	what to do if there's no authorization code
getAuthorizationCode();
}

//update zenu contacts
updateZenuContacts();




function getAuthorizationCode() {
    global $authorize_url, $client_id, $callback_uri;

    $authorization_redirect_url = $authorize_url . "?response_type=code&client_id=" . $client_id . "&redirect_uri=" . $callback_uri . "&scope=contact:read";

    header("Location: " . $authorization_redirect_url);

    //	if you don't want to redirect
    // echo "Go <a href='$authorization_redirect_url'>here</a>, copy the code, and paste it into the box below.<br /><form action=" . $_SERVER["PHP_SELF"] . " method = 'post'><input type='text' name='authorization_code' /><br /><input type='submit'></form>";
}

    //	turn the authorization code into an access token, etc.
function getAccessToken($authorization_code) {
    global $token_url, $client_id, $client_secret, $callback_uri;

    $authorization = base64_encode("$client_id:$client_secret");
    $header = array("Authorization: Basic {$authorization}","Content-Type: application/x-www-form-urlencoded");
    $content = "grant_type=authorization_code&code=$authorization_code&redirect_uri=$callback_uri";

    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => $token_url,
    CURLOPT_HTTPHEADER => $header,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $content
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    if ($response === false) {
        echo "Failed";
        echo curl_error($curl);
        echo "Failed";
    } elseif (json_decode($response)->error) {
        echo "Error:<br />";
        echo $authorization_code;
        echo $response;
    }

    return json_decode($response)->access_token;
}

//	we can now use the access_token as much as we want to access protected resources
function getResource($access_token) {
    global $test_api_url;

    $header = array("Authorization: Bearer {$access_token}");

    $curl = curl_init();
    curl_setopt_array($curl, array(
    CURLOPT_URL => $test_api_url,
    CURLOPT_HTTPHEADER => $header,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_RETURNTRANSFER => true
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    return json_decode($response, true);
}

function createPropMEContact($propContact){
    $contact = $propContact->PrimaryContactPerson;
    if($contact->WorkPhone || $contact->CellPhone || $contact->HomePhone) {
        $id = $contact->Id;
        $phone = preg_replace('/\D+/', '', $contact->WorkPhone ?: $contact->HomePhone);
        $mobile = preg_replace('/\D+/', '', $contact->CellPhone);
        $fname =   $contact->FirstName;
        $lname =  $contact->LastName;
        $company = isset($contact->CompanyName) ? $contact->CompanyName : '';
        $type = implode(', ',$propContact->Roles);
        $timestamp = (new DateTime("now"))->format("Y-m-d H:i:s");
        $email = $contact->Email;

        $contacts = queryDatabase("SELECT count(*) FROM contacts WHERE prop_id = '$id'");
        if($contacts['count(*)'] > 0) {
            $query = "UPDATE contacts SET updated_at='$timestamp',phone='$phone', mobile='$mobile', first_name='$fname', last_name='$lname', company='$company', type='$type', email='$email' WHERE prop_id = '$id'";
        } else {
            $query = "INSERT INTO contacts (created_at, updated_at, prop_id, phone, mobile, first_name, last_name, company, type, email) 
			VALUES ('$timestamp','$timestamp','$id','$phone', '$mobile', '$fname', '$lname', '$company', '$type', '$email')";
        }
        queryDatabase($query);
    }
}
