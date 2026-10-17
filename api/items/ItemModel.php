<?php

class ItemModel {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Create a new item. Endpoint: POST /api/items/create
     */
    public function create(
        string $userId, 
        string $title, 
        string $description, 
        string $image,
        int $isSkip, 
        float $latitude, 
        float $longitude): bool {
            $stmt = $this->conn->prepare("
                INSERT INTO items (user_id, title, description, image, is_skip, latitude, longitude) 
                VALUES (:user_id, :title, :description, :image, :is_skip, :latitude, :longitude)");
            
            return $stmt->execute([
                'user_id'      => $userId,
                'title'        => $title,
                'description'  => $description,
                'image'        => $image,
                'is_skip'      => $isSkip,
                'latitude'     => $latitude,
                'longitude'    => $longitude
            ]);
    }

    /**
     * Find a single item by its ID.
     */
    public function findById(int $id): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM items WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find a user by primary ID (excluding sensitive credentials).
     */
    public function findInRadius(float $long, float $lat, int $radius): ?array {

    $sql = "
            SELECT id, user_id, title, description, image, is_collected, is_skip, latitude, longitude, 
                ROUND(
                    ST_Distance_Sphere(
                        POINT(longitude, latitude), 
                        POINT(:lng1, :lat1)
                    )
                ) AS distance_meters
            FROM items
            WHERE is_collected = 0
            AND ST_Distance_Sphere(
                    POINT(longitude, latitude), 
                    POINT(:lng2, :lat2)
                ) <= :radius
            ORDER BY distance_meters ASC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':lng1'   => $long,
            ':lat1'   => $lat,
            ':lng2'   => $long,
            ':lat2'   => $lat,
            ':radius' => $radius
        ]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function($item) {
            return [
                'id'              => (int)$item['id'],
                'user_id'         => (int)$item['user_id'],
                'title'           => $item['title'],
                'description'     => $item['description'],
                'latitude'        => number_format((float)$item['latitude'], 8, '.', ''),
                'longitude'       => number_format((float)$item['longitude'], 8, '.', ''),
                'is_collected'   => (bool)$item['is_collected'],
                'is_skip'        => (bool)$item['is_skip'],
                'distance_meters' => (int)$item['distance_meters']
            ];
        }, $result);

        return $formatted ?: null;
    }

}