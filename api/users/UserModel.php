<?php

class UserModel {
    private PDO $conn;

    public function __construct() {
        $this->conn = Database::getInstance()->getConnection();
    }

    /**
     * Find a single user by email address.
     */
    public function findByEmail(string $email): ?array {
        $stmt = $this->conn->prepare("SELECT * FROM users WHERE email = :email");
        $stmt->execute(['email' => $email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    /**
     * Find a user by primary ID (excluding sensitive credentials).
     */
    public function findById(int $id): ?array {
        $stmt = $this->conn->prepare("
            SELECT id, email, first_name, last_name, phone_number, profile_image, created_at, updated_at 
            FROM users 
            WHERE id = :id
        ");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();

        return $result ?: null;
    }

    public function findBasicPublicProfile(int $id): ?array {
        $stmt = $this->conn->prepare("
            SELECT first_name, profile_image 
            FROM users 
            WHERE id = :id
        ");
        $stmt->execute([':id' => $id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        return $user ?: null;
    }

    /**
     * Create a new user record with hashed password and optional personal details.
     */
    public function create(
        string $email, 
        string $password, 
        ?string $firstName = null, 
        ?string $lastName = null, 
        ?string $phone = null
    ): bool {
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $this->conn->prepare("
            INSERT INTO users (email, password_hash, first_name, last_name, phone_number) 
            VALUES (:email, :hash, :first_name, :last_name, :phone_number)
        ");

        return $stmt->execute([
            'email'        => $email,
            'hash'         => $hash,
            'first_name'   => $firstName,
            'last_name'    => $lastName,
            'phone_number' => $phone
        ]);
    }

    /**
     * Dynamically update profile fields for a user.
     */
    public function updateProfile(int $userId, array $data): bool {
        $fields = [];
        $params = ['id' => $userId];

        $allowedFields = ['first_name', 'last_name', 'phone_number', 'profile_image'];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $data)) {
                $fields[] = "{$field} = :{$field}";
                $params[$field] = $data[$field];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute($params);
    }
}