<?php

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    echo json_encode(getZenuContactbyID($id));
} elseif (isset($_GET['page'])){
    $page = $_GET['page'];
    echo json_encode(showZenuContacts($page));
} elseif (isset($_GET['phone'])) {
    $number = $_GET['phone'];
    $phone = substr($number, 3);
    $mobile = '0' . substr($number, 2);
    $contact = queryDatabase("SELECT * FROM contacts 
                    WHERE phone = '$phone' OR mobile = '$mobile'
                    ORDER BY updated_at DESC
                    LIMIT 1");
    if (isset($contact['id'])) {
        $zenuContact = getZenuContactbyID($contact['zenu_id']);
        if(isset($zenuContact)){
            createZenuContact($zenuContact);
        }
    } else {
        updateZenuContacts();
    }
    $contact = queryDatabase("SELECT * FROM contacts 
                    WHERE phone = '$phone' OR mobile = '$mobile'
                    ORDER BY updated_at DESC
                    LIMIT 1");

    if(strpos($contact['type'], 'Owner') !== false || strpos($contact['type'], 'Landlord') !== false ){
        $contact['type'] = "Landlord";
    } elseif (strpos($contact['type'],'Tenant') !== false){
        $contact['type'] = "Tenant";
    } else {
        $contact['type'] = '';
    }

    echo json_encode($contact);
}

function queryDatabase($query){
    $config = parse_ini_file('../config.ini');
    $dsn = "mysql:host=".$config['db_host'].";dbname=".$config['db_name'].";charset=utf8mb4;port=3307";
    $options = [
        \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
        \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        \PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    try {
        $db = new \PDO($dsn, $config['db_username'], $config['db_password'], $options);
    } catch (\PDOException $e) {
        throw new \PDOException($e->getMessage(), (int)$e->getCode());
    }
    $sql = $db->query($query);
    return $sql->fetch();
}

function updateZenuContacts(){
    $last_request = queryDatabase("SELECT * FROM requests 
                    ORDER BY updated_at DESC
                    LIMIT 1");
    $config = parse_ini_file('../config.ini');
    $url = $config['ZENU_URL'] .'/contacts/'; //'https://api.zenu.com.au/api/v1'
    if(isset($last_request)){
        $timestamp = DateTime::createFromFormat ( "Y-m-d H:i:s", $last_request["updated_at"] );
//        $timestamp->add(new DateInterval('PT10H')); //add 10 hours
        $url = $config['ZENU_URL'] .'/contacts?filter[last_modified_from]='.str_replace(' ','T',$timestamp->format("Y-m-d H:i:s"));
    }
    $response = requestToZenu($url);
    if(isset($response)){
        $page = 1;
        $total_pages = $response->pagination->total_pages;
        ini_set('max_execution_time', 300);
        while($page <= $total_pages) {
            $zenuContacts = $response->data;
            foreach ($zenuContacts as $contact) {
                createZenuContact($contact);
            }
            $url = $config['ZENU_URL'] .'/contacts?page[number]='.$page++;
            $response = requestToZenu($url);
        }
        $timestamp = (new DateTime("now"))->format("Y-m-d H:i:s");
        $desc = 'zenu';
        $query = "INSERT INTO `requests`  (`updated_at` ,`created_at`, `desc`) VALUES ('$timestamp','$timestamp','$desc')";
        queryDatabase($query);
    }

}

function createZenuContact($zenuContact){
    if($zenuContact->phone->work || $zenuContact->phone->mobile || $zenuContact->phone->home) {
        echo implode($zenuContact->types);
        $id = $zenuContact->id;
        $phone = preg_replace('/\D+/', '', $zenuContact->phone->work ?: $zenuContact->phone->home);
        $mobile = preg_replace('/\D+/', '', $zenuContact->phone->mobile);
        $fname =   $zenuContact->first_name;
        $lname =  $zenuContact->last_name;
        $company = isset($zenuContact->company) ? $zenuContact->company->name : '';
        $type = implode(', ',$zenuContact->types);
        $timestamp = (new DateTime("now"))->format("Y-m-d H:i:s");

        $contacts = queryDatabase("SELECT count(*) FROM contacts WHERE zenu_id = '$id'");
        if($contacts['count(*)'] > 0) {
            $query = "UPDATE contacts SET updated_at='$timestamp',phone='$phone', mobile='$mobile', first_name='$fname', last_name='$lname', company='$company', type='$type' WHERE zenu_id = '$id'";
        } else {
            $query = "INSERT INTO contacts (created_at, updated_at, zenu_id, phone, mobile, first_name, last_name, company, type) 
			VALUES ('$timestamp','$timestamp','$id','$phone', '$mobile', '$fname', '$lname', '$company', '$type')";
        }
        queryDatabase($query);
    }
}

function getZenuContactbyID($id){
    $config = parse_ini_file('../config.ini');
    $url = $config['ZENU_URL'] .'/contacts/'.$id; //'https://api.zenu.com.au/api/v1'
    $response = requestToZenu($url);
    return $response->data;
}

function showZenuContacts($page)
{
    if ($page == 0) $page = 1;
    $config = parse_ini_file('../config.ini');
    $url = $config['ZENU_URL'] . '/contacts?page[number]=' . $page;
    $response = requestToZenu($url);
    return $response->data;
}

function requestToZenu($url){
    $config = parse_ini_file('../config.ini');
    $username=$config['ZENU_ID'];
    $password=$config['ZENU_TOKEN'];
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); //this is important!
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); //timeout after 30 seconds
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
    curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
    curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
    $response=curl_exec ($ch);
    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);   //get status code
    return json_decode($response);
}