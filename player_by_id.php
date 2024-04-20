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

$headers = getallheaders();//inserting all headers into this variable
function extract_token_from_bearer_string($header) {
    if (substr($header, 0, 7) !== 'Bearer ') {
        return false;
    }
    
    return trim(substr($header, 7));
}
if (isset($headers["Authorization"])) {
    $jwt = extract_token_from_bearer_string($headers["Authorization"]);//the TOKEN sent by the USER 
} else{
    http_response_code(401);
    $status = ["code"=> 401, "message" => "No Authorization token detected, please read the documentation - docs-nba-api.sebastian7.cz"]; 
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
    $stmt = $db->prepare("SELECT * FROM nba_data where id = :id");
    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $player = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if ($player) {
        $status = ["code" => 200, "message" => "Successful request."];
        echo json_encode(["content" => $player, "status" => $status]);
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
