<?php
//setup
include "../auth/db.php"; 
header('Content-Type: application/json');
//#setup

//validate JWT
require __DIR__ . '/vendor/autoload.php';//using composer packages...
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

//can be exposed
$publicKey = <<<EOD
-----BEGIN PUBLIC KEY-----
MIIBITANBgkqhkiG9w0BAQEFAAOCAQ4AMIIBCQKCAQB+vFFNyn+lbtnHwjSnWt6e
GUtrAWPSZJIqEwNFoh5hLe80G5V+7FtBfgRw3LJdMRX6ZnuN8PAZFvNtXAa7y4P1
oV16xTr7IGPV66daFDCBndf43GgsAOpG/KYVnGobW0ojem6keaOAt+/TB5+5yXY8
olwdgqnuGRWOMfZwwqedxPVsgQaNVNOc5iUgOYt/t2TpaSiFRjcd5hc7WHXK3ML5    
mZVknHBYkt46DdhgEozMzTQaQ3vijNeInREqk/dFp7J6kVUYGnUBy1tOVa6Q+sZB
JnE9EHODQtx/aQXfJ3oW1L2hwi6zIXNT2vjkSqic55JfAZEuJ1npcDqrlBSXv3Ij
AgMBAAE=
-----END PUBLIC KEY-----
EOD;
//validate JWT
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
    $status = ["code"=> 401, "message" => "No Authorization token detected, please read the documentation - docs-nba-api.sebastian7.cz  or visit nba-api.sebastian7.cz to get the token"]; 
    echo json_encode(["status" => $status]);
    exit;
}

try{
    $decoded = JWT::decode($jwt, new Key($publicKey, 'RS256'));
} catch (Exception $e) {
    $status = ["code" => 401, "message" => $e->getMessage()];
    echo json_encode(["status" => $status]);
    exit();
}
//validate JWT


// general validation
if ($_SERVER['REQUEST_METHOD'] !== 'GET'){
    http_response_code(405);
    $status = ["code" => 405, "message" => "Invalid request method."];
    echo json_encode(["status" => $status]);
    exit;
}

if(count($_GET) !== 1){
    http_response_code(400);
    $status = ["code" => 400, "message" => "Invalid number of parameters (only accepting 'player_name')"];
    echo json_encode(["status" => $status]);
    exit();
}
if(!isset($_GET['player_name'])) {
    http_response_code(400);
    $status = ["code" => 400, "message" => "Missing 'player_name' parameter."];
    echo json_encode(["status" => $status]);
    exit();
}
// #general validation

$pattern = '%'.$_GET['player_name'].'%';


try {   
    $stmt = $db->prepare("SELECT * FROM nba_data where player_name LIKE :pattern limit 100");
    $stmt->bindParam(':pattern', $pattern);//preventing malicious activity
    $stmt->execute();
    $row_count = $stmt->rowCount();
    $player = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($player) {
                
        $status = ["code" => 200, "message" => "Successful request."];
        $info = ["count" => $row_count];
        echo json_encode(["content" => $player,"info" => $info, "status" => $status]);
        exit();
    } else {    
        http_response_code(404);
        $status =["code" => 404, "message" => "Resource not found."];
        echo json_encode(["status" => $status]);
        exit();
    }
} catch(PDOException $e) {
    $end_time = microtime(true);
    $processing_time = $start_time - $end_time;
    http_response_code(500);
    $status = ["code" => 500, "message" => "Database error: " . $e->getMessage()];
    echo json_encode(["status" => $status]);
    exit();
}
?>
