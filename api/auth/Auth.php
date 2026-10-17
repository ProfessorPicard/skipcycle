<?php
// src/Auth/Auth.php
require_once realpath(__DIR__ . '/../Database.php');

class Auth {

    /**
     * Generates a new Refresh Token (7 days) and Access Token (15 mins).
     * Stores sha256 hashes in the database while returning raw tokens to the client.
     */
    
    public static function createTokens(int $userId): array {
        $db = Database::getInstance()->getConnection();

        // Revoke previous sessions
        $revokeStmt = $db->prepare("DELETE FROM refresh_tokens WHERE user_id = :user_id");
        $revokeStmt->execute(['user_id' => $userId]);

        $rawRefreshToken = bin2hex(random_bytes(32));
        $rawAccessToken  = bin2hex(random_bytes(32));

        $refreshHash = hash('sha256', $rawRefreshToken);
        $accessHash  = hash('sha256', $rawAccessToken);

        // 1. Store Refresh Token (Let MySQL set +7 days)
        $stmtRefresh = $db->prepare("
            INSERT INTO refresh_tokens (user_id, token_hash, expires_at)
            VALUES (:user_id, :token_hash, DATE_ADD(NOW(), INTERVAL 7 DAY))
        ");
        $stmtRefresh->execute([
            'user_id'    => $userId,
            'token_hash' => $refreshHash
        ]);
        $refreshTokenId = (int)$db->lastInsertId();

        // 2. Store Access Token (Let MySQL set +15 minutes)
        $stmtAccess = $db->prepare("
            INSERT INTO access_tokens (user_id, refresh_token_id, token_hash, expires_at)
            VALUES (:user_id, :refresh_token_id, :token_hash, DATE_ADD(NOW(), INTERVAL 15 MINUTE))
        ");
        $stmtAccess->execute([
            'user_id'          => $userId,
            'refresh_token_id' => $refreshTokenId,
            'token_hash'       => $accessHash
        ]);

        return [
            "access_token"       => $rawAccessToken,
            "refresh_token"      => $rawRefreshToken,
            "access_expires_at"  => $db->query("SELECT UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 15 MINUTE)) AS expires_at")->fetchColumn(),
            "refresh_expires_at" => $db->query("SELECT UNIX_TIMESTAMP(DATE_ADD(NOW(), INTERVAL 7 DAY)) AS expires_at")->fetchColumn()
        ];
    }
    /**
     * Validates the incoming Access Token from the Authorization header.
     * Looks for 'Authorization: Bearer <token>' or fallback HTTP_AUTHORIZATION header.
     */
    public static function validateToken(): ?array {
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] 
            ?? $headers['authorization'] 
            ?? $_SERVER['HTTP_AUTHORIZATION'] 
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] 
            ?? '';

        if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            return null; // Fails here if header is missing/malformed
        }

        $rawToken = $matches[1];
        $tokenHash = hash('sha256', $rawToken);

        $db = Database::getInstance()->getConnection();

        $stmt = $db->prepare("
            SELECT a.user_id, u.email 
            FROM access_tokens a
            JOIN users u ON a.user_id = u.id
            WHERE a.token_hash = :token_hash 
            AND a.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['token_hash' => $tokenHash]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Swaps a valid Refresh Token for a fresh set of Access & Refresh Tokens.
     * Rotates tokens by deleting the old session record.
     */
    public static function refreshToken(string $rawRefreshToken): ?array {
        $db = Database::getInstance()->getConnection();
        $refreshHash = hash('sha256', $rawRefreshToken);

        // 1. Locate valid session record
        $stmt = $db->prepare("
            SELECT id, user_id 
            FROM refresh_tokens 
            WHERE token_hash = :token_hash 
              AND expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute(['token_hash' => $refreshHash]);
        $session = $stmt->fetch();

        if (!$session) {
            return null; // Invalid or expired refresh token
        }

        // 2. Delete old session (ON DELETE CASCADE automatically deletes child access tokens)
        $deleteStmt = $db->prepare("DELETE FROM refresh_tokens WHERE id = :id");
        $deleteStmt->execute(['id' => $session['id']]);

        // 3. Issue fresh set of tokens
        return self::createTokens($session['user_id']);
    }

    /**
     * Revokes a session during logout using the access token from the header.
     */
    public static function revokeSession(string $rawAccessToken): bool {
        $db = Database::getInstance()->getConnection();
        $accessHash = hash('sha256', $rawAccessToken);

        // Delete parent refresh token (deletes access token via foreign key CASCADE)
        $stmt = $db->prepare("
            DELETE r FROM refresh_tokens r
            JOIN access_tokens a ON a.refresh_token_id = r.id
            WHERE a.token_hash = :token_hash
        ");
        return $stmt->execute(['token_hash' => $accessHash]);
    }
}