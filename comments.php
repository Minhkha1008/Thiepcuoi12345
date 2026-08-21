<?php
// Laragon defaults: MySQL on localhost, user root, empty password.
const DB_HOST = '127.0.0.1';
const DB_NAME = 'wedding_invitation';
const DB_USER = 'root';
const DB_PASSWORD = '';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = null;

try {
    $serverPdo = new PDO(
        'mysql:host=' . DB_HOST . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $serverPdo->exec(
        'CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'
    );

    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASSWORD,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS guest_comments (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            message VARCHAR(1000) NOT NULL,
            side VARCHAR(10) NOT NULL DEFAULT 'groom',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB"
    );
    $sideColumn = $pdo->query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = " . $pdo->quote(DB_NAME) . "
         AND TABLE_NAME = 'guest_comments' AND COLUMN_NAME = 'side'"
    )->fetchColumn();
    if (!$sideColumn) {
        $pdo->exec("ALTER TABLE guest_comments ADD COLUMN side VARCHAR(10) NOT NULL DEFAULT 'groom'");
    }
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS rsvps (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(100) NOT NULL,
            relationship VARCHAR(40) NOT NULL,
            attendance VARCHAR(20) NOT NULL,
            guest_count TINYINT UNSIGNED NOT NULL DEFAULT 0,
            wishes VARCHAR(1000) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB'
    );
} catch (Throwable $error) {
    respond(['error' => 'Không thể kết nối cơ sở dữ liệu. Kiểm tra MySQL trong Laragon.'], 500);
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $comments = $pdo->query(
        'SELECT id, name, message, side, created_at FROM guest_comments ORDER BY created_at ASC, id ASC LIMIT 100'
    )->fetchAll();
    respond(['comments' => $comments]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Phương thức không được hỗ trợ.'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    respond(['error' => 'Dữ liệu gửi lên không hợp lệ.'], 400);
}

$action = $input['action'] ?? '';
$name = trim((string) ($input['name'] ?? ''));
if ($name === '' || mb_strlen($name) > 100) {
    respond(['error' => 'Tên không hợp lệ.'], 422);
}

if ($action === 'comment') {
    $message = trim((string) ($input['message'] ?? ''));
    $side = trim((string) ($input['side'] ?? ''));
    if ($message === '' || mb_strlen($message) > 1000 || !in_array($side, ['bride', 'groom'], true)) {
        respond(['error' => 'Lời chúc không hợp lệ.'], 422);
    }
    $statement = $pdo->prepare(
        'INSERT INTO guest_comments (name, message, side) VALUES (:name, :message, :side)'
    );
    $statement->execute(['name' => $name, 'message' => $message, 'side' => $side]);
    respond(['success' => true]);
}

if ($action === 'rsvp') {
    $relationship = trim((string) ($input['relationship'] ?? ''));
    $attendance = trim((string) ($input['attendance'] ?? ''));
    $guestCount = filter_var($input['guest_count'] ?? 0, FILTER_VALIDATE_INT);
    $wishes = trim((string) ($input['wishes'] ?? ''));

    if ($relationship === '' || $attendance === '' || $guestCount === false || $guestCount < 0 || $guestCount > 20) {
        respond(['error' => 'Thông tin xác nhận tham dự không hợp lệ.'], 422);
    }
    if (mb_strlen($wishes) > 1000) {
        respond(['error' => 'Lời chúc quá dài.'], 422);
    }

    $statement = $pdo->prepare(
        'INSERT INTO rsvps (name, relationship, attendance, guest_count, wishes)
         VALUES (:name, :relationship, :attendance, :guest_count, :wishes)'
    );
    $statement->execute([
        'name' => $name,
        'relationship' => $relationship,
        'attendance' => $attendance,
        'guest_count' => $guestCount,
        'wishes' => $wishes !== '' ? $wishes : null
    ]);
    respond(['success' => true]);
}

respond(['error' => 'Loại dữ liệu không được hỗ trợ.'], 400);
