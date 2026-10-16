<?php
// src/Tokens/TokenController.php
require_once realpath(__DIR__ . '/../Auth.php');

class TokenController {

    /**
     * Main request handler for token actions.
     */
    public function handleRequest(string $method, ?string $action, ?string $id, ?array $currentUser): void {
        switch ($action) {
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
     * Swaps a valid Refresh Token for a fresh token pair (POST /api/tokens/refresh)
     */
    private function refreshTokens(): void {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        if (empty($data['refresh_token'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Refresh token is required"]);
            return;
        }

        $newTokens = Auth::refreshToken($data['refresh_token']);

        if ($newTokens) {
            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "tokens" => $newTokens
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