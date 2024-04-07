<?php
//setup
include "db.php"; 
header('Content-Type: application/json');
//#setup

//api token/key auth
$headers = getallheaders();//inserting all headers into this variable
function extract_token_from_bearer_string($header) {
    if (substr($header, 0, 7) !== 'Bearer ') {
        return false;
    }
    
    return trim(substr($header, 7));
}
if (isset($headers["Authorization"])) {
    $token = extract_token_from_bearer_string($headers["Authorization"]);//the TOKEN sent by the USER 
} else{
    http_response_code(401);
    $status = ["code"=> 401, "message" => "No Authorization token detected, please read the documentation."]; 
    echo json_encode(["status" => $status]);
    exit;
}

date_default_timezone_set('Europe/Prague');
$curr_datetime = date('Y-m-d H:i:s', time());
$stmt = $db->prepare("SELECT expiry_datetime from nba_api_global_token WHERE id = 1");
$stmt->execute();
$token_expiry_datetime = $stmt->fetchColumn();
if ($curr_datetime < $token_expiry_datetime){//checking if DB token is NOT expired, if it is we'll notify the user to refresh
   $stmt = $db->prepare("SELECT token from nba_api_global_token WHERE id = 1");
   $stmt->execute();
   $global_token = $stmt->fetchColumn();//the current VALID TOKEN

   if ($global_token === $token) {
    $stmt = $db->prepare("SELECT request_count from nba_api_global_token WHERE id = 1");
    $stmt->execute();
    $request_count = $stmt->fetchColumn();
    if ($request_count < 1000) {
        //allow request to pass...

    } else {
        http_response_code(429);
        $status = ["code" => 429, "message" => "The global token has reached it's 24hr limit, please try later."];
        echo json_encode(["status" => $status]);
        exit;
    }
   } else {
    http_response_code(401);
    $status = ["code" => 401, "message" => "Denied access. Invalid or expired access token."];
    echo json_encode(["status" => $status]);
    exit;
   }
} else {
    //expired token
    http_response_code(403);
    $status = ["code" => 403, "message" => "The global token has expired, please visit nba-api.sebastian7.com/token_status and refresh the token."];
    echo json_encode(["status" => $status]);
    exit;
}
//#api token/key auth

if ($_SERVER['REQUEST_METHOD'] !== 'GET'){
    echo json_encode(["error_code" => 405, "error" => "Invalid request method."]);
    exit;
}

if(count($_GET) !== 1){
    $status = ["code" => 400, "message" => "Invalid number of parameters (only accepting 'id')"];
    echo json_encode(["status" => $status]);
    exit;
}
if(!isset($_GET['id'])) {
    $status = ["code" => 400, "message" => "Missing 'id' parameter."];
    echo json_encode(["status" => $status]);
    exit;
}

if (!is_numeric($_GET['id'])) {
    $status = ["code" => 400, "message" => "'id' parameter has to be numeric.(check for accidental spaces)"];
    echo json_encode(["status" => $status]);
    exit;
}

if ($_GET['id'] < 0){
    $status = ["code" => 400, "message" => "id cannot be lower than 0"];
    echo json_encode(["status" => $status]);
    exit;
}       

$id = intval($_GET['id']);

try {
    // $stmt = $db->prepare("SELECT * FROM api_tottenham where id = :id");
    $stmt = $db->prepare("SELECT * FROM nba_data where id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $player = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($player) {

        $status = ["code" => 200, "message" => "Successful request."];
        echo json_encode(["content" => $player, "status" => $status]);
        //Incrementing the number of completed requests in our DB on the current Global token
        $stmt = $db->prepare("UPDATE nba_api_global_token SET request_count = request_count + 1");
        $stmt->execute();
        exit;
    } else {
        $status =["code" => 404, "message" => "Resource not found."];
        echo json_encode(["status" => $status]);
        exit;
    }
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>