<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

requireLogin();

$uploadDir = __DIR__ . '/../assets/uploads/events';
$publicPrefix = 'assets/uploads/events/';
$maxBytes = 20 * 1024 * 1024;
$allowedMimes = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'application/pdf', 'text/plain',
    'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/zip'
];

try {
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('Upload directory could not be created');
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list') {
        listFiles($pdo, (int)($_GET['event_id'] ?? 0));
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        uploadFiles($pdo, $uploadDir, $publicPrefix, $maxBytes, $allowedMimes);
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        deleteFile($pdo, $uploadDir);
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'File operation failed']);
}

function eventAccess(PDO $pdo, int $eventId, bool $write = false): bool {
    if (!$eventId) return false;
    if (hasRole('superadmin', 'admin')) return true;
    if (!$write && hasRole('student')) return true;
    if (hasRole('staff')) {
        $stmt = $pdo->prepare('SELECT 1 FROM event_staff WHERE event_id = ? AND staff_id = ?');
        $stmt->execute([$eventId, $_SESSION['user']['id']]);
        return (bool)$stmt->fetchColumn();
    }
    return false;
}

function listFiles(PDO $pdo, int $eventId): void {
    if (!eventAccess($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this event']);
        return;
    }
    $stmt = $pdo->prepare('SELECT id, original_name, mime_type, file_size, storage_name, is_cover, created_at FROM event_files WHERE event_id = ? ORDER BY is_cover DESC, created_at DESC');
    $stmt->execute([$eventId]);
    $files = [];
    foreach ($stmt->fetchAll() as $file) {
        $file['size_human'] = formatSize((int)$file['file_size']);
        $file['url'] = 'assets/uploads/events/' . rawurlencode($file['storage_name']);
        unset($file['storage_name']);
        $files[] = $file;
    }
    echo json_encode($files);
}

function uploadFiles(PDO $pdo, string $uploadDir, string $publicPrefix, int $maxBytes, array $allowedMimes): void {
    $eventId = (int)($_POST['event_id'] ?? 0);
    if (!eventAccess($pdo, $eventId, true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot upload files for this event']);
        return;
    }
    if (empty($_FILES['files']['name']) || !is_array($_FILES['files']['name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'No files supplied']);
        return;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $uploaded = [];
    $errors = [];
    $hasCover = hasCover($pdo, $eventId);

    foreach ($_FILES['files']['name'] as $index => $originalName) {
        $tmp = $_FILES['files']['tmp_name'][$index] ?? '';
        $size = (int)($_FILES['files']['size'][$index] ?? 0);
        $error = (int)($_FILES['files']['error'][$index] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($tmp)) {
            $errors[] = ['name' => $originalName, 'error' => 'Upload failed'];
            continue;
        }
        if ($size <= 0 || $size > $maxBytes) {
            $errors[] = ['name' => $originalName, 'error' => 'File must be smaller than 20 MB'];
            continue;
        }
        $mime = $finfo->file($tmp);
        if (!in_array($mime, $allowedMimes, true)) {
            $errors[] = ['name' => $originalName, 'error' => 'File type is not allowed'];
            continue;
        }

        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $storageName = bin2hex(random_bytes(16)) . ($extension ? '.' . preg_replace('/[^a-z0-9]/i', '', $extension) : '');
        if (!move_uploaded_file($tmp, $uploadDir . DIRECTORY_SEPARATOR . $storageName)) {
            $errors[] = ['name' => $originalName, 'error' => 'File could not be stored'];
            continue;
        }

        $isImage = str_starts_with($mime, 'image/');
        $isCover = $isImage && !$hasCover ? 1 : 0;
        $stmt = $pdo->prepare('INSERT INTO event_files (event_id, original_name, storage_name, mime_type, file_size, is_cover, uploaded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$eventId, basename($originalName), $storageName, $mime, $size, $isCover, $_SESSION['user']['id']]);
        $hasCover = $hasCover || $isCover;
        $uploaded[] = ['id' => $pdo->lastInsertId(), 'original_name' => basename($originalName), 'mime_type' => $mime, 'size_human' => formatSize($size), 'is_cover' => $isCover];
    }

    echo json_encode(['uploaded' => $uploaded, 'errors' => $errors]);
}

function deleteFile(PDO $pdo, string $uploadDir): void {
    $data = json_decode(file_get_contents('php://input'), true) ?: [];
    $fileId = (int)($data['id'] ?? 0);
    $stmt = $pdo->prepare('SELECT * FROM event_files WHERE id = ?');
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if (!$file || !eventAccess($pdo, (int)$file['event_id'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot delete this file']);
        return;
    }

    $pdo->prepare('DELETE FROM event_files WHERE id = ?')->execute([$fileId]);
    $path = $uploadDir . DIRECTORY_SEPARATOR . basename($file['storage_name']);
    if (is_file($path)) unlink($path);
    if ((int)$file['is_cover'] === 1) promoteCover($pdo, (int)$file['event_id']);
    echo json_encode(['success' => true]);
}

function hasCover(PDO $pdo, int $eventId): bool {
    $stmt = $pdo->prepare('SELECT 1 FROM event_files WHERE event_id = ? AND is_cover = 1 AND mime_type LIKE \'image/%\' LIMIT 1');
    $stmt->execute([$eventId]);
    return (bool)$stmt->fetchColumn();
}

function promoteCover(PDO $pdo, int $eventId): void {
    $stmt = $pdo->prepare('SELECT id FROM event_files WHERE event_id = ? AND mime_type LIKE \'image/%\' ORDER BY created_at ASC LIMIT 1');
    $stmt->execute([$eventId]);
    if ($id = $stmt->fetchColumn()) $pdo->prepare('UPDATE event_files SET is_cover = 1 WHERE id = ?')->execute([$id]);
}

function formatSize(int $bytes): string {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}
