<?php

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
    $dbConfig = require __DIR__ . '/db-config.php';
    $pdo = new PDO(
        'mysql:host=' . $dbConfig['host'] . ';dbname=' . $dbConfig['database'] . ';charset=utf8mb4',
        $dbConfig['username'],
        $dbConfig['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Throwable $error) {
    respond(['error' => 'Không thể kết nối cơ sở dữ liệu. Kiểm tra thông tin trong db-config.php và database đã được tạo trên hosting.'], 500);
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
