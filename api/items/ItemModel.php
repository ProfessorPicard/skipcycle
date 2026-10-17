<?php

class ItemModel {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
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
            SELECT id, user_id, title, description, latitude, longitude, is_collected, is_skip
                ROUND(
                    ST_Distance_Sphere(
                        POINT(longitude, latitude), 
                        POINT(:lng, :lat)
                    )
                ) AS distance_meters
            FROM items
            WHERE is_collected = 0
            AND ST_Distance_Sphere(
                    POINT(longitude, latitude), 
                    POINT(:lng, :lat)
                ) <= :radius
            ORDER BY distance_meters ASC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':lat'    => $searchLat,
            ':lng'    => $searchLng,
            ':radius' => $radiusMeters
        ]);

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $formatted = array_map(function($item) {
            return [
                'id'              => (int)$item['id'],
                'user_id'         => (int)$item['user_id'],
                'title'           => $item['title'],
                'description'     => $item['description'],
                'latitude'        => (float)$item['latitude'],
                'longitude'       => (float)$item['longitude'],
                'is_collected'   => (bool)$item['is_collected'],
                'is_skip'        => (bool)$item['is_skip'],
                'distance_meters' => (int)$item['distance_meters']
            ];
        }, $result);

        return $formatted ?: null;
    }

}