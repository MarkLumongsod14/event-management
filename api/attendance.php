<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

try {
    ensureAttendanceSettingsTable($pdo);

    if ($method === 'GET') {
        $action = $_GET['action'] ?? '';
        if ($action === 'list') {
            requireLogin();
            listAttendance($pdo, (int)($_GET['event_id'] ?? 0));
            return;
        }
        if ($action === 'report') {
            requireLogin();
            reportAttendance($pdo, (int)($_GET['event_id'] ?? 0));
            return;
        }
        if ($action === 'mode') {
            requireLogin();
            getAttendanceMode($pdo, (int)($_GET['event_id'] ?? 0));
            return;
        }
        if ($action === 'student_code') {
            requireLogin();
            echo json_encode([
                'success' => true,
                'code' => 'EVENTMNGMT-STUDENT-' . (int)$_SESSION['user']['id'],
                'student_id' => (int)$_SESSION['user']['id'],
            ]);
            return;
        }
        http_response_code(400);
        echo json_encode(['error' => 'Unknown attendance action']);
        return;
    }

    if ($method === 'POST') {
        requireLogin();
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        if ($action === 'set_mode') {
            setAttendanceMode($pdo, $data);
            return;
        }
        if ($action === 'check_in') {
            checkInStudent($pdo, $data);
            return;
        }
        if ($action === 'check_out') {
            checkOutStudent($pdo, $data);
            return;
        }

        http_response_code(400);
        echo json_encode(['error' => 'Unknown attendance action']);
        return;
    }

    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Attendance operation failed']);
}

function ensureAttendanceSettingsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_attendance_settings (
        event_id INT NOT NULL,
        mode ENUM('closed','check_in','check_out') NOT NULL DEFAULT 'closed',
        updated_by INT NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (event_id),
        KEY idx_updated_at (updated_at),
        CONSTRAINT fk_event_attendance_settings_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
        CONSTRAINT fk_event_attendance_settings_user FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function getAttendanceMode(PDO $pdo, int $eventId): void {
    if (!$eventId) {
        echo json_encode(['success' => true, 'mode' => 'closed']);
        return;
    }
    $stmt = $pdo->prepare('SELECT mode FROM event_attendance_settings WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $mode = $stmt->fetchColumn();
    echo json_encode(['success' => true, 'mode' => $mode ?: 'closed']);
}

function setAttendanceMode(PDO $pdo, array $data): void {
    $eventId = (int)($data['event_id'] ?? 0);
    $mode = $data['mode'] ?? 'closed';
    if (!in_array($mode, ['closed', 'check_in', 'check_out'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid attendance mode']);
        return;
    }
    if (!$eventId || !hasRole('superadmin', 'admin') || !canManageAttendance($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'Only admin accounts can change the attendance mode']);
        return;
    }

    $stmt = $pdo->prepare('INSERT INTO event_attendance_settings (event_id, mode, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE mode = VALUES(mode), updated_by = VALUES(updated_by), updated_at = CURRENT_TIMESTAMP');
    $stmt->execute([$eventId, $mode, $_SESSION['user']['id']]);

    echo json_encode(['success' => true, 'mode' => $mode]);
}

function getEventAttendanceMode(PDO $pdo, int $eventId): string {
    $stmt = $pdo->prepare('SELECT mode FROM event_attendance_settings WHERE event_id = ?');
    $stmt->execute([$eventId]);
    $mode = $stmt->fetchColumn();
    return in_array($mode, ['closed', 'check_in', 'check_out'], true) ? $mode : 'closed';
}

function canManageAttendance(PDO $pdo, int $eventId): bool {
    if (hasRole('superadmin', 'admin')) return true;
    if (hasRole('staff')) {
        $stmt = $pdo->prepare('SELECT 1 FROM event_staff WHERE event_id = ? AND staff_id = ?');
        $stmt->execute([$eventId, $_SESSION['user']['id']]);
        return (bool)$stmt->fetchColumn();
    }
    return false;
}

function listAttendance(PDO $pdo, int $eventId): void {
    if (!$eventId || !canManageAttendance($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot view attendance for this event']);
        return;
    }

    $stmt = $pdo->prepare('SELECT a.id, a.student_id, u.full_name AS student_name, u.username, a.status, a.checked_in_at, a.checked_out_at, a.created_at, a.scanned_by FROM event_attendance a JOIN users u ON u.id = a.student_id WHERE a.event_id = ? ORDER BY a.checked_in_at DESC, u.full_name ASC');
    $stmt->execute([$eventId]);
    echo json_encode($stmt->fetchAll());
}

function reportAttendance(PDO $pdo, int $eventId): void {
    if (!hasRole('superadmin', 'admin', 'staff')) {
        http_response_code(403);
        echo json_encode(['error' => 'You do not have permission to view attendance reports']);
        return;
    }

    $where = '';
    $params = [];
    if ($eventId > 0) {
        if (!canManageAttendance($pdo, $eventId)) {
            http_response_code(403);
            echo json_encode(['error' => 'You cannot view attendance for this event']);
            return;
        }
        $where = 'WHERE a.event_id = ?';
        $params[] = $eventId;
    } elseif (hasRole('staff')) {
        $where = 'JOIN event_staff es ON es.event_id = a.event_id AND es.staff_id = ?';
        $params[] = $_SESSION['user']['id'];
    }

    $sql = "
        SELECT a.id, a.event_id, e.name AS event_name, a.student_id, u.full_name AS student_name, u.username,
               a.status, a.checked_in_at, a.checked_out_at,
               TIMESTAMPDIFF(MINUTE, a.checked_in_at, COALESCE(a.checked_out_at, NOW())) AS minutes_present
        FROM event_attendance a
        JOIN events e ON e.id = a.event_id
        JOIN users u ON u.id = a.student_id
        {$where}
        ORDER BY e.name ASC, a.checked_in_at DESC, u.full_name ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll());
}

function checkInStudent(PDO $pdo, array $data): void {
    $eventId = (int)($data['event_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);
    $status = in_array($data['status'] ?? 'present', ['present', 'late', 'absent', 'excused'], true) ? $data['status'] : 'present';

    if (!$eventId || !$studentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Event ID and student ID are required']);
        return;
    }

    if (!canManageAttendance($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot check in students for this event']);
        return;
    }

    $currentMode = getEventAttendanceMode($pdo, $eventId);
    if ($currentMode !== 'check_in') {
        http_response_code(403);
        echo json_encode(['error' => $currentMode === 'closed' ? 'Attendance is currently closed.' : 'Attendance is currently open for check-out only.']);
        return;
    }

    $eventStmt = $pdo->prepare('SELECT status FROM events WHERE id = ?');
    $eventStmt->execute([$eventId]);
    $event = $eventStmt->fetch();
    if (!$event) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }
    if (($event['status'] ?? '') !== 'approved') {
        http_response_code(400);
        echo json_encode(['error' => 'Only approved events accept attendance']);
        return;
    }

    $studentStmt = $pdo->prepare('SELECT id, role FROM users WHERE id = ? AND role = ?');
    $studentStmt->execute([$studentId, 'student']);
    if (!$studentStmt->fetch()) {
        http_response_code(400);
        echo json_encode(['error' => 'The selected user is not a student']);
        return;
    }

    $existingStmt = $pdo->prepare('SELECT id, checked_in_at, checked_out_at, status FROM event_attendance WHERE event_id = ? AND student_id = ?');
    $existingStmt->execute([$eventId, $studentId]);
    $existing = $existingStmt->fetch();

    if ($existing && empty($existing['checked_out_at'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Student is already checked in']);
        return;
    }

    $upsert = $pdo->prepare('INSERT INTO event_attendance (event_id, student_id, checked_in_at, checked_out_at, status, scanned_by) VALUES (?, ?, NOW(), NULL, ?, ?) ON DUPLICATE KEY UPDATE checked_in_at = NOW(), checked_out_at = NULL, status = VALUES(status), scanned_by = VALUES(scanned_by)');
    $upsert->execute([$eventId, $studentId, $status, $_SESSION['user']['id']]);

    echo json_encode([
        'success' => true,
        'action' => 'check_in',
        'student_id' => $studentId,
        'status' => $status,
        'checked_in_at' => date('Y-m-d H:i:s'),
        'checked_out_at' => null,
    ]);
}

function checkOutStudent(PDO $pdo, array $data): void {
    $eventId = (int)($data['event_id'] ?? 0);
    $studentId = (int)($data['student_id'] ?? 0);
    $status = in_array($data['status'] ?? 'present', ['present', 'late', 'absent', 'excused'], true) ? $data['status'] : 'present';

    if (!$eventId || !$studentId) {
        http_response_code(400);
        echo json_encode(['error' => 'Event ID and student ID are required']);
        return;
    }

    if (!canManageAttendance($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You cannot check out students for this event']);
        return;
    }

    $currentMode = getEventAttendanceMode($pdo, $eventId);
    if ($currentMode !== 'check_out') {
        http_response_code(403);
        echo json_encode(['error' => $currentMode === 'closed' ? 'Attendance is currently closed.' : 'Attendance is currently open for check-in only.']);
        return;
    }

    $attendanceStmt = $pdo->prepare('SELECT id, checked_in_at, checked_out_at FROM event_attendance WHERE event_id = ? AND student_id = ?');
    $attendanceStmt->execute([$eventId, $studentId]);
    $attendance = $attendanceStmt->fetch();
    if (!$attendance) {
        http_response_code(404);
        echo json_encode(['error' => 'Student has not checked in for this event yet']);
        return;
    }
    if (!empty($attendance['checked_out_at'])) {
        http_response_code(409);
        echo json_encode(['error' => 'Student is already checked out']);
        return;
    }

    $update = $pdo->prepare('UPDATE event_attendance SET checked_out_at = NOW(), status = ?, scanned_by = ? WHERE event_id = ? AND student_id = ?');
    $update->execute([$status, $_SESSION['user']['id'], $eventId, $studentId]);

    echo json_encode([
        'success' => true,
        'action' => 'check_out',
        'student_id' => $studentId,
        'status' => $status,
        'checked_out_at' => date('Y-m-d H:i:s'),
    ]);
}
