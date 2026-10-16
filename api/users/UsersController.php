<?php
// src/Users/UserController.php
require_once realpath(__DIR__ . '/UserModel.php');

class UserController {
    private UserModel $model;

    public function __construct() {
        $this->model = new UserModel();
    }

    /**
     * Main request handler for all user actions.
     */
    public function handleRequest(string $method, ?string $action, ?string $id, ?array $currentUser): void {
        switch ($action) {
            case 'register':
                if ($method === 'POST') {
                    $this->register();
                } else {
                    $this->methodNotAllowed();
                }
                break;

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

            case 'profile':
                if ($method === 'GET') {
                    if ($id !== null) {
                        // Authorized view of another user's basic profile: GET /api/users/profile/{id}
                        $this->getPublicBasicProfile((int)$id);
                    } else {
                        // Authenticated user's own full profile: GET /api/users/profile
                        $this->getProfile($currentUser['user_id']);
                    }
                } elseif ($method === 'POST' || $method === 'PUT') {
                    $this->updateProfile($currentUser['user_id']);
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "User action not found"]);
                break;
        }
    }

    /**
     * Handles user registration (POST /api/users/register)
     */
    private function register(): void {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        if (empty($data['email']) || empty($data['password'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Email and password are required"]);
            return;
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Invalid email format"]);
            return;
        }

        if ($this->model->findByEmail(trim($data['email']))) {
            http_response_code(409);
            echo json_encode(["status" => "fail", "message" => "User already exists"]);
            return;
        }

        $created = $this->model->create(
            trim($data['email']),
            $data['password'],
            $data['first_name'] ?? null,
            $data['last_name'] ?? null,
            $data['phone_number'] ?? null
        );

        if ($created) {
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "User registered successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to register user"]);
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

            http_response_code(200);
            echo json_encode([
                "status"  => "success",
                "message" => "Login successful",
                "tokens"  => $tokens
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

        http_response_code(200);
        echo json_encode(["status" => "success", "message" => "Logged out successfully"]);
    }

    /**
     * Fetches authenticated user's profile details (GET /api/users/profile)
     */
    private function getProfile(int $userId): void {
        $profile = $this->model->findById($userId);

        if ($profile) {
            http_response_code(200);
            echo json_encode(["status" => "success", "data" => $profile]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "User profile not found"]);
        }
    }

    /**
     * Fetches basic public profile data for a specific user ID.
     * Returns only first_name and profile_image.
     */
    private function getPublicBasicProfile(int $userId): void {
        if ($userId <= 0) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Invalid User ID"]);
            return;
        }

        $profile = $this->model->findBasicPublicProfile($userId);

        if ($profile) {
            $baseUrl = "http://" . $_SERVER['HTTP_HOST'] . "/";
            $profile['profile_image_url'] = !empty($profile['profile_image']) 
                ? $baseUrl . $profile['profile_image'] 
                : null;

            http_response_code(200);
            echo json_encode([
                "status" => "success",
                "data"   => $profile
            ]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "User not found"]);
        }
    }

    /**
     * Updates profile details and optional profile image (POST or PUT /api/users/profile)
     */
    private function updateProfile(int $userId): void {
        $updateData = $_POST;

        // Process profile image upload if provided in multipart form data
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $imagePath = $this->handleImageUpload($_FILES['profile_image']);
            if ($imagePath) {
                $updateData['profile_image'] = $imagePath;
            } else {
                http_response_code(400);
                echo json_encode(["status" => "fail", "message" => "Invalid image format or size exceeds limit"]);
                return;
            }
        }

        if ($this->model->updateProfile($userId, $updateData)) {
            http_response_code(200);
            echo json_encode(["status" => "success", "message" => "Profile updated successfully"]);
        } else {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "No valid fields provided for update"]);
        }
    }

    /**
     * Validates and moves uploaded profile image files.
     */
    private function handleImageUpload(array $file): ?string {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (!in_array($file['type'], $allowedTypes)) {
            return null;
        }

        // Limit maximum image size to 2MB
        if ($file['size'] > 2 * 1024 * 1024) {
            return null;
        }

        $targetDir = __DIR__ . '/../../uploads/profiles/';
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = uniqid('profile_', true) . '.' . strtolower($extension);
        $targetFile = $targetDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $targetFile)) {
            return 'uploads/profiles/' . $filename;
        }

        return null;
    }

    private function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }
}