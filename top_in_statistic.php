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
if ($curr_datetime < $token_expiry_datetime){//checking if DB token is NOT expired
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
    $status = ["code" => 405, "message" => "Invalid request method."];
    echo json_encode(["status" => $status]);
    exit;
}

if(count($_GET) !== 3){
    $status = ["code" => 400, "message" => "Invalid number of parameters (only accepting 'option','limit' and 'offset')"];
    echo json_encode(["status" => $status]);
    exit;
}
if(!isset($_GET['option']) OR !isset($_GET['limit']) OR !isset($_GET['offset'])) {
    $status = ["code" => 400, "message" => "Missing  'option' or 'limit' or 'offset' parameter."];
    echo json_encode(["status" => $status]);
    exit;
}

if (!is_numeric($_GET['limit']) or !is_numeric($_GET['offset'])){
    $status = ["code" => 400, "message" => "limit & offset need to be numeric.(also check for accidental spaces)"];
    echo json_encode(["status" => $status]);
    exit;
}

if ($_GET['limit'] < 1){
    $status = ["code" => 400, "message" => "limit cannot be lower than 1"];
    echo json_encode(["status" => $status]);
    exit;
}
if ($_GET['limit'] > 100) {
    $status = ["code" => 400, "message" => "you cannot retrieve more than 100 records in one request!(lower the limit)"];
    echo json_encode(["status" => $status]);
    exit;
}

if ($_GET['offset'] < 0){
    $status = ["code" => 400, "message" => "offset cannot be lower than 0"];
    echo json_encode(["status" => $status]);
    exit;
}       

$option = $_GET['option'];
if($option != 'pts' AND $option != 'ast' AND $option != 'gp' AND $option != 'reb' AND $option != 'net_rating' AND $option != 'oreb_pct' AND $option != 'usg_pct' AND $option != 'ts_pct' AND $option != 'ast_pct'){
    $status = ["code" => 400, "message" => "Not a valid 'option' value."];
    echo json_encode(["status" => $status]);
    exit;
}

$limit = intval($_GET['limit']);
$offset = intval($_GET['offset']);


try {
    $stmt = $db->prepare("SELECT * FROM nba_data 
    ORDER BY 
        CASE 
            WHEN :option = 'pts' THEN pts
            WHEN :option = 'ast' THEN ast 
            WHEN :option = 'gp' THEN gp
            WHEN :option = 'reb' THEN reb
            WHEN :option = 'net_rating' THEN net_rating
            WHEN :option = 'oreb_pct' THEN oreb_pct
            WHEN :option = 'usg_pct' THEN usg_pct
            WHEN :option = 'ts_pct' THEN ts_pct
            WHEN :option = 'ast_pct' THEN ast_pct
            ELSE gp 
        END DESC LIMIT :limit OFFSET :offset");     
    $stmt->bindParam(':option', $option);
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
     $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $row_count = $stmt->rowCount();
    $player = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($player) {

        $status = ["code" => 200, "message" => "Successful request."];
        $info = ["count" => $row_count];
        echo json_encode(["content" => $player,"info" => $info, "status" => $status]);
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