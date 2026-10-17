<?php

date_default_timezone_set('UTC');

$allowedOrigin = 'https://pb685.brighton.domains'; // Your frontend domain

header("Access-Control-Allow-Origin: {$allowedOrigin}");
header("Access-Control-Allow-Credentials: true"); // CRITICAL: Allows cookies to pass
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request immediately
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

require_once realpath(__DIR__ . '/Database.php');
require_once realpath(__DIR__ . '/auth/Auth.php');
require_once realpath(__DIR__ . '/users/UsersController.php');
require_once realpath(__DIR__ . '/auth/AuthController.php');
require_once realpath(__DIR__ . '/items/ItemController.php');

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
    'auth/login',
    'users/register',
    'auth/refresh'
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
            "currrentRoute" => $currentRoute,
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

    case 'auth':
        (new AuthController())->handleRequest($method, $action, $id, $currentUser);
        break;

    case 'items':
        (new ItemController())->handleRequest($method, $action, $id, $currentUser);
        break;

    default:
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
        break;
}