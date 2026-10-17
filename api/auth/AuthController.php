<?php

require_once realpath(__DIR__ . '/Auth.php');
require_once realpath(__DIR__ . '/../users/UserModel.php');

class AuthController {

    private UserModel $model;

    public function __construct() {
        $this->model = new UserModel();
    }

    /**
     * Main request handler for token actions.
     */
    public function handleRequest(string $method, ?string $action, ?string $id, ?array $currentUser): void {
        switch ($action) {

            case 'login':
                if ($method === 'POST') {
                    $this->login();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'logout':
                if ($method === 'POST') {
                    $this->logout();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'refresh':
                if ($method === 'POST') {
                    $this->refreshTokens();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Token action not found"]);
                break;
        }
    }

    /**
     * Handles user login (POST /api/users/login)
     */
    private function login(): void {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Email and password required"]);
            return;
        }

        $user = $this->model->findByEmail(trim($data['email']));

        if ($user && password_verify($data['password'], $user['password_hash'])) {
            $tokens = Auth::createTokens((int)$user['id']);
            
            setcookie(
                'refresh_token',
                $tokens["refresh_token"],
                [
                    'expires'  => $tokens["refresh_expires_at"],
                    'path'     => '/ci609/api/',
                    'domain'   => 'pb685.brighton.domains',
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict'
                ]
            );

            http_response_code(200);
            echo json_encode([
                'status'       => 'success',
                'access_token' => $tokens["access_token"],
                'access_expires_at' => $tokens["access_expires_at"],
                'user_id'      => $user['id']
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["status" => "fail", "message" => "Invalid email or password"]);
        }
    }

    /**
     * Revokes the active session during logout (POST /api/users/logout)
     */
    private function logout(): void {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] 
            ?? $headers['authorization'] 
            ?? $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? '';

        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            Auth::revokeSession($matches[1]);
        }

        setcookie('refresh_token', '', [
            'expires'  => time() - 3600,
            'path'     => '/ci609/api/auth/',
            'domain'   => 'pb685.brighton.domains',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ]);

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Logged out successfully"]);
    }
    
    /**
     * Swaps a valid Refresh Token for a fresh token pair (POST /api/tokens/refresh)
     */
    private function refreshTokens(): void {
        $refreshToken = $_COOKIE['refresh_token'] ?? null;

        if (empty($refreshToken)) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Refresh token is required"]);
            return;
        }

        $newTokens = Auth::refreshToken($refreshToken);

        if ($newTokens) {
            
            setcookie('refresh_token', $newTokens["refresh_token"], [
                'expires'  => $newTokens["refresh_expires_at"],
                'path'     => '/ci609/api/auth/',
                'domain'   => 'pb685.brighton.domains',
                'secure'   => true,
                'httponly' => true,
                'samesite' => 'Strict'
            ]);

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "access_token" => $newTokens["access_token"],
                "access_expires_at" => $newTokens["access_expires_at"],
                "user_id" => $newTokens['user_id']
            ]);


            
        
        } else {
            http_response_code(401);
            echo json_encode(["status" => "error", "message" => "Invalid or expired refresh token"]);
        }
    }

    private function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }
}