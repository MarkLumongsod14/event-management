<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'GET':
            requireLogin();
            if ($action === 'list')          listStaff($pdo);
            elseif ($action === 'single')    getStaff($pdo, (int)($_GET['id'] ?? 0));
            elseif ($action === 'games_of')  getStaffGames($pdo, (int)($_GET['id'] ?? 0));
            else { http_response_code(400); echo json_encode(['error' => 'Unknown action']); }
            break;

        case 'POST':
            requireRole('superadmin', 'admin');
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create')     createStaff($pdo, $data);
            elseif ($action === 'update') updateStaff($pdo, $data);
            elseif ($action === 'delete') deleteStaff($pdo, $data);
            else { http_response_code(400); echo json_encode(['error' => 'Unknown action']); }
            break;

        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

// ============ FUNCTIONS ============

function listStaff($pdo) {
    $stmt = $pdo->query("
        SELECT u.id, u.username, u.full_name, u.email, u.role, u.created_at,
               COUNT(cs.category_id) AS game_count
        FROM users u
        LEFT JOIN category_staff cs ON cs.staff_id = u.id
        WHERE u.role IN ('staff','judge','student','admin','superadmin')
        GROUP BY u.id
        ORDER BY u.full_name
    ");
    echo json_encode($stmt->fetchAll());
}

function getStaff($pdo, $id) {
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID required']); return; }
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, role FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) { http_response_code(404); echo json_encode(['error'=>'Not found']); return; }
    $row['game_ids'] = getStaffGameIds($pdo, $id);
    echo json_encode($row);
}

function getStaffGames($pdo, $id) {
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID required']); return; }
    $stmt = $pdo->prepare("
        SELECT c.id, c.name, c.parent_group
        FROM category_staff cs
        JOIN categories c ON cs.category_id = c.id
        WHERE cs.staff_id = ?
        ORDER BY c.parent_group, c.name
    ");
    $stmt->execute([$id]);
    echo json_encode($stmt->fetchAll());
}

function getStaffGameIds($pdo, $id) {
    $stmt = $pdo->prepare("SELECT category_id FROM category_staff WHERE staff_id = ?");
    $stmt->execute([$id]);
    return array_map('intval', array_column($stmt->fetchAll(), 'category_id'));
}

function createStaff($pdo, $data) {
    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';
    $fullName = trim($data['full_name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $role     = $data['role'] ?? 'staff';

    if (!$username || !$password || !$fullName) {
        http_response_code(400);
        echo json_encode(['error' => 'Username, password, and full name are required']);
        return;
    }
    if (!in_array($role, ['staff','judge','admin','superadmin','student'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        return;
    }
    if (in_array($role, ['admin', 'superadmin'], true) && hasRole('admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Only a superadmin can create elevated accounts']);
        return;
    }
    if (strlen($password) < 6) {
        http_response_code(400);
        echo json_encode(['error' => 'Password must be at least 6 characters']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken']);
        return;
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (username, password, full_name, email, role)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$username, $hash, $fullName, $email, $role]);
        $newId = $pdo->lastInsertId();

        // Assign games
        if ($role === 'staff' && !empty($data['game_ids']) && is_array($data['game_ids'])) {
            $ss = $pdo->prepare("INSERT INTO category_staff (category_id, staff_id) VALUES (?, ?)");
            foreach ($data['game_ids'] as $cid) $ss->execute([(int)$cid, $newId]);
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $newId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateStaff($pdo, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID required']); return; }

    $username = trim($data['username'] ?? '');
    $fullName = trim($data['full_name'] ?? '');
    $email    = trim($data['email'] ?? '');
    $role     = $data['role'] ?? 'staff';
    $password = $data['password'] ?? '';

    if (!$username || !$fullName) {
        http_response_code(400);
        echo json_encode(['error' => 'Username and full name are required']);
        return;
    }
    if (!in_array($role, ['staff','judge','admin','superadmin','student'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid role']);
        return;
    }
    $target = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetRole = $target->fetchColumn();
    if ($targetRole === false) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    if (hasRole('admin') && !in_array($targetRole, ['staff', 'judge', 'student'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Only a superadmin can modify elevated accounts']);
        return;
    }
    if (in_array($role, ['admin', 'superadmin'], true) && hasRole('admin')) {
        http_response_code(403);
        echo json_encode(['error' => 'Only a superadmin can assign elevated roles']);
        return;
    }

    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $stmt->execute([$username, $id]);
    if ($stmt->fetch()) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken']);
        return;
    }

    $pdo->beginTransaction();
    try {
        if ($password !== '') {
            if (strlen($password) < 6) {
                throw new Exception('Password must be at least 6 characters');
            }
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users SET username=?, full_name=?, email=?, role=?, password=? WHERE id=?
            ");
            $stmt->execute([$username, $fullName, $email, $role, $hash, $id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE users SET username=?, full_name=?, email=?, role=? WHERE id=?
            ");
            $stmt->execute([$username, $fullName, $email, $role, $id]);
        }

        // Replace game assignments
        $pdo->prepare("DELETE FROM category_staff WHERE staff_id = ?")->execute([$id]);
        if ($role === 'staff' && !empty($data['game_ids']) && is_array($data['game_ids'])) {
            $ss = $pdo->prepare("INSERT INTO category_staff (category_id, staff_id) VALUES (?, ?)");
            foreach ($data['game_ids'] as $cid) $ss->execute([(int)$cid, $id]);
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteStaff($pdo, $data) {
    $id = (int)($data['id'] ?? 0);
    if (!$id) { http_response_code(400); echo json_encode(['error'=>'ID required']); return; }

    if ($id === (int)$_SESSION['user']['id']) {
        http_response_code(400);
        echo json_encode(['error' => 'You cannot delete your own account']);
        return;
    }

    $target = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $target->execute([$id]);
    $targetRole = $target->fetchColumn();
    if ($targetRole === false) {
        http_response_code(404);
        echo json_encode(['error' => 'User not found']);
        return;
    }
    if (hasRole('admin') && !in_array($targetRole, ['staff', 'judge', 'student'], true)) {
        http_response_code(403);
        echo json_encode(['error' => 'Only a superadmin can delete elevated accounts']);
        return;
    }

    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
    echo json_encode(['success' => true]);
}