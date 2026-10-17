<?php

require_once realpath(__DIR__ . '/ItemModel.php');

class ItemController {
    private ItemModel $model;

    public function __construct() {
        $this->model = new ItemModel();
    }

    /**
     * Main request handler for all user actions.
     */
    public function handleRequest(string $method, ?string $action, ?string $id, ?array $currentUser): void {
        switch ($action) {
            
            case 'findInRadius':
                if ($method === 'POST') {
                    $this->findInRadius();
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'findById':
                if ($method === 'GET') {
                    if ($id !== null) {
                        $this->findById((int)$id);
                    } else {
                        http_response_code(400);
                        echo json_encode(["status" => "error", "message" => "Item ID is required for this action"]);
                    }
                } else {
                    $this->methodNotAllowed();
                }
                break;

            case 'create':
                if ($method === 'POST') {
                    $this->create();
                    // Implement item creation logic here
                } else {
                    $this->methodNotAllowed();
                }
                break;

            default:
                http_response_code(404);
                echo json_encode(["status" => "error", "message" => "Item action not found"]);
                break;
        }
    }

    /**
     * Handles item creation (POST /api/items/create)
     */
    private function create(): void {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        if (empty($data['user_id']) || 
            empty($data['title']) || 
            empty($data['description']) || 
            empty($data['image']) || 
            empty($data['latitude']) || 
            empty($data['longitude'])) {
                http_response_code(400);
                echo json_encode(["status" => "fail", "message" => "All fields are required"]);
                return;
        }


        $created = $this->model->create(
            trim($data['user_id']),
            trim($data['title']),
            trim($data['description']),
            trim($data['image']),
            (int)$data['is_skip'],
            (float)$data['latitude'],
            (float)$data['longitude']
        );

        if ($created) {
            http_response_code(201);
            echo json_encode(["status" => "success", "message" => "Item created successfully"]);
        } else {
            http_response_code(500);
            echo json_encode(["status" => "error", "message" => "Failed to create item"]);
        }
    }

    /**
     * Handles item search by radius (POST /api/items/findInRadius)
     */
    private function findInRadius(): void {
        $data = json_decode(file_get_contents("php://input"), true) ?? $_POST;

        if (empty($data['latitude']) || empty($data['longitude']) || empty($data['radius_meters'])) {
            http_response_code(400);
            echo json_encode(["status" => "fail", "message" => "Latitude, longitude, and radius are required"]);
            return;
        }

        $items = $this->model->findInRadius(
            (float)$data['longitude'],
            (float)$data['latitude'],
            (int)$data['radius_meters']
        );

        http_response_code(200);
        echo json_encode(["status" => "success", "data" => $items]);
        
    }

    /**
     * Fetches a single item by its ID (GET /api/items/{id})
     */
    private function findById(int $itemId): void {
        $item = $this->model->findById($itemId);

        if ($item) {
            http_response_code(200);
            echo json_encode(["status" => "success", "data" => $item]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Item not found"]);
        }
    }

    private function methodNotAllowed(): void {
        http_response_code(405);
        echo json_encode(["status" => "error", "message" => "Method not allowed"]);
    }
}