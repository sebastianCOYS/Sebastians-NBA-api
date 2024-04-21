<?php
//setup
require __DIR__ . '/vendor/autoload.php';//using composer packages...
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
include "db.php";
header('Content-Type: application/json');
//setup

//POST validation
if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    http_response_code(405);
    $status = ["code" => http_response_code(), "message" => "Invalid request method (https://docs-nba-api.sebastian7.cz/)"];
    echo json_encode(["status" => $status]);
    exit();
}
//no need to check empty() cause if it is then it just won't match up in the DB
if (!isset($_POST["app_password"]) or !isset($_POST["app_id"])) {
    http_response_code(400);
    $status = ["code" => http_response_code(), "message" => "missing parameter"];
    echo json_encode(["status" => $status]);
    exit();
}
if (count($_POST) !== 2) {
    http_response_code(400);
    $status = ["code" => http_response_code(), "message" => "Expected exactly 2 parameters"];
    echo json_encode(["status" => $status]);
    exit();
}
//POST validation

//credential check
try{
    $stmt = $db->prepare("SELECT * FROM nba_api_users WHERE app_id = :app_id AND app_password = :app_password");
    $stmt->bindParam(":app_id", $_POST["app_id"]);
    $stmt->bindParam(":app_password", $_POST["app_password"]);
    $stmt->execute();    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    
    if (!$user) {
        http_response_code(401);
        $status = ["code" => http_response_code(), "message" => "Incorrect credentials"];
        echo json_encode(["status" => $status]);
        exit(); 
    }
    //if there IS such a user, the code just continues on to JWT creation...
    

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    exit();
}
//credential check

//create and send the jwt...
$privateKey = $_ENV['private'];


$iat = time();//current unix timestamp
$expiry = strtotime("+20 minutes", $iat);

//first check if post_app id valid tho or smting
$payload = [
    'sub' => $_POST["app_id"],
    'iss' => 'https://www.nba-api.sebastian7.cz',
    'aud' => 'user',
    'iat' => $iat,
    'exp' => $expiry
];

$jwt = JWT::encode($payload, $privateKey, 'RS256');
$content = ["jwt" => $jwt];
echo json_encode(["content" => $content]);
exit();



