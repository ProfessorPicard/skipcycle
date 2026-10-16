<?php

date_default_timezone_set('UTC');

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once realpath(__DIR__ . '/Database.php');
require_once realpath(__DIR__ . '/Auth.php');
require_once realpath(__DIR__ . '/users/UsersController.php');
require_once realpath(__DIR__ . '/tokens/TokenController.php');
// require_once realpath(__DIR__ . '/src/Items/ItemController.php');

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$uriSegments = explode('/', trim($uri, '/'));


$apiIndex = array_search('api', $uriSegments);

if ($apiIndex !== false && isset($uriSegments[$apiIndex + 1])) {
    $domain = $uriSegments[$apiIndex + 1];           // e.g., "users"
    $action = $uriSegments[$apiIndex + 2] ?? null;    // e.g., "login"
    $id     = $uriSegments[$apiIndex + 3] ?? null;
} else {
    $domain = null;
    $action = null;
    $id     = null;
}

if (empty($uriSegments[$apiIndex]) || $uriSegments[$apiIndex] !== 'api') {
    http_response_code(404);
    echo json_encode(["status" => "error", "message" => "Invalid API base endpoint"]);
    exit();
}

$method = $_SERVER['REQUEST_METHOD'];

// Public Endpoints (No Token Required)
$publicRoutes = [
    'users/login',
    'users/register',
    'tokens/refresh'
];

$currentRoute = "{$domain}/{$action}";
$currentUser = null;

// Authentication
if (!in_array($currentRoute, $publicRoutes)) {
    // Validates the Bearer Access Token against the access_tokens table
    $currentUser = Auth::validateToken();

    // If the token is invalid or missing, return a 401 Unauthorized response
    if (!$currentUser) {
        http_response_code(401);
        echo json_encode([
            "status" => "error", 
            "message" => "Unauthorized. Access token is missing, invalid, or expired."
        ]);
        exit(); // Terminates execution before reaching controllers
    }
}

// Pass values into Controller Methods
switch ($domain) {
    case 'users':
        (new UserController())->handleRequest($method, $action, $id, $currentUser);
        break;

    case 'tokens':
        (new TokenController())->handleRequest($method, $action, $id, $currentUser);
        break;

    // case 'items':
    //     (new ItemController())->handleRequest($method, $action, $id, $currentUser);
    //     break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
        break;
}