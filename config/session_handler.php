<?php
class PdoSessionHandler implements SessionHandlerInterface {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function open(string $path, string $name): bool {
        return true;
    }

    public function close(): bool {
        return true;
    }

    public function read(string $id): string|false {
        $stmt = $this->db->prepare("SELECT data FROM sessions WHERE id = :id AND expires_at > CURRENT_TIMESTAMP");
        $stmt->execute(['id' => $id]);
        
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            return $row['data'];
        }
        
        return '';
    }

    public function write(string $id, string $data): bool {
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hour session lifetime
        
        $stmt = $this->db->prepare("
            INSERT INTO sessions (id, data, expires_at) 
            VALUES (:id, :data, :expires)
            ON CONFLICT (id) DO UPDATE 
            SET data = EXCLUDED.data, expires_at = EXCLUDED.expires_at
        ");
        
        return $stmt->execute([
            'id' => $id,
            'data' => $data,
            'expires' => $expires
        ]);
    }

    public function destroy(string $id): bool {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function gc(int $max_lifetime): int|false {
        $stmt = $this->db->prepare("DELETE FROM sessions WHERE expires_at < CURRENT_TIMESTAMP");
        if ($stmt->execute()) {
            return $stmt->rowCount();
        }
        return false;
    }
}
