<?php
// ============================================================
// RÉUSSITE+ | Connexion Base de Données (PDO)
// ============================================================

require_once __DIR__ . '/config.php';

class Database {
    private static ?PDO $instance = null;

    public static function get(): PDO {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
                ]);
            } catch (PDOException $e) {
                if (APP_ENV === 'development') {
                    die('<pre style="color:red;padding:20px">Erreur DB: ' . htmlspecialchars($e->getMessage()) . '</pre>');
                }
                die('Service temporairement indisponible.');
            }
        }
        return self::$instance;
    }
}

// Alias pratique
function db(): PDO {
    return Database::get();
}

// Requête préparée simplifiée
function dbQuery(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

// Récupérer une seule ligne
function dbRow(string $sql, array $params = []): ?array {
    $row = dbQuery($sql, $params)->fetch();
    return $row ?: null;
}

// Récupérer toutes les lignes
function dbAll(string $sql, array $params = []): array {
    return dbQuery($sql, $params)->fetchAll();
}

// Insérer et retourner l'ID
function dbInsert(string $table, array $data): string {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
    $data['id'] = $uuid;
    $cols   = implode(', ', array_map(fn($k) => "`$k`", array_keys($data)));
    $places = implode(', ', array_fill(0, count($data), '?'));
    dbQuery("INSERT INTO `$table` ($cols) VALUES ($places)", array_values($data));
    return $uuid;
}

// Mettre à jour
function dbUpdate(string $table, array $data, string $whereCol, mixed $whereVal): int {
    $sets = implode(', ', array_map(fn($k) => "`$k` = ?", array_keys($data)));
    $stmt = dbQuery(
        "UPDATE `$table` SET $sets WHERE `$whereCol` = ?",
        [...array_values($data), $whereVal]
    );
    return $stmt->rowCount();
}
