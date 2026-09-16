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
            if ($action === 'single' && isset($_GET['id'])) {
                getEvent($pdo, (int)$_GET['id']);
            } elseif ($action === 'staff_list') {
                getStaffList($pdo);
            } elseif ($action === 'judge_list') {
                getJudgeList($pdo);
            } elseif ($action === 'categories') {
                getCategories($pdo);
            } elseif ($action === 'category_staff_map') {
                categoryStaffMap($pdo);
            } elseif ($action === 'participants' && isset($_GET['event_id'])) {
                getEventParticipants($pdo, (int)$_GET['event_id']);
            } else {
                listEvents($pdo);
            }
            break;

        case 'POST':
            $data = json_decode(file_get_contents('php://input'), true);
            if ($action === 'create') {
                requireRole('superadmin','admin');
                createEvent($pdo, $data);
            } elseif ($action === 'update') {
                requireRole('superadmin','admin','staff');
                updateEvent($pdo, $data);
            } elseif ($action === 'delete') {
                requireRole('superadmin','admin');
                deleteEvent($pdo, $data);
            } elseif ($action === 'approve') {
                requireRole('superadmin','admin');
                approveEvent($pdo, $data);
            } elseif ($action === 'participants') {
                saveParticipants($pdo, $data);
            } elseif ($action === 'results') {
                saveResults($pdo, $data);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Unknown action']);
            }
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

function listEvents($pdo) {
    $assignmentJoin = '';
    $params = [];
    $where = '';

    if (hasRole('judge')) {
        $assignmentJoin = 'JOIN event_judges ej ON ej.event_id = e.id AND ej.judge_id = ?';
        $params[] = $_SESSION['user']['id'];
    } elseif (hasRole('staff')) {
        $assignmentJoin = 'JOIN event_staff es_filter ON es_filter.event_id = e.id AND es_filter.staff_id = ?';
        $params[] = $_SESSION['user']['id'];
    } elseif (hasRole('student')) {
        $where = 'WHERE e.status = ?';
        $params[] = 'approved';
    }

    $stmt = $pdo->prepare("
        SELECT e.id, e.name, e.players_representative, e.status, e.created_at,
               e.start_datetime, e.end_datetime,
               c.name AS category, c.parent_group, c.has_criteria
        FROM events e
        {$assignmentJoin}
        JOIN categories c ON e.category_id = c.id
        {$where}
        ORDER BY 
            CASE WHEN e.start_datetime IS NULL THEN 1 ELSE 0 END,
            e.start_datetime ASC,
            e.created_at DESC
    ");
    $stmt->execute($params);
    $events = $stmt->fetchAll();

    foreach ($events as &$ev) {
        $ev['staff']        = getEventStaff($pdo, $ev['id']);
        $ev['criteria']     = getEventCriteria($pdo, $ev['id']);
        $ev['participants'] = getEventParticipantsRows($pdo, $ev['id']);
        $ev['judges']      = getEventJudges($pdo, $ev['id']);
        $ev['can_score']   = canScoreEvent($pdo, $ev['id']);
        $ev['cover_image_url'] = getEventCoverImage($pdo, $ev['id']);
        $ev['has_criteria'] = (bool)$ev['has_criteria'];
    }
    echo json_encode($events);
}

function getEvent($pdo, $id) {
    $stmt = $pdo->prepare("
        SELECT e.*, c.name AS category, c.parent_group, c.has_criteria
        FROM events e 
        JOIN categories c ON e.category_id = c.id
        WHERE e.id = ?
    ");
    $stmt->execute([$id]);
    $ev = $stmt->fetch();
    if (!$ev) {
        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
        return;
    }
    if (!canViewEvent($pdo, $id)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this event']);
        return;
    }
    if (hasRole('student') && ($ev['status'] ?? '') !== 'approved') {
        http_response_code(403);
        echo json_encode(['error' => 'This event is not approved for public viewing']);
        return;
    }
    $ev['staff']    = getEventStaff($pdo, $id);
    $ev['criteria'] = getEventCriteria($pdo, $id);
    $ev['participants'] = getEventParticipantsRows($pdo, $id);
    $ev['judges'] = getEventJudges($pdo, $id);
    $ev['can_score'] = canScoreEvent($pdo, $id);
    $ev['cover_image_url'] = getEventCoverImage($pdo, $id);
    echo json_encode($ev);
}

function getEventCoverImage($pdo, $eventId) {
    $stmt = $pdo->prepare("SELECT storage_name FROM event_files WHERE event_id = ? AND is_cover = 1 AND mime_type LIKE 'image/%' LIMIT 1");
    $stmt->execute([$eventId]);
    $storageName = $stmt->fetchColumn();
    return $storageName ? 'assets/uploads/events/' . rawurlencode($storageName) : null;
}

function getEventStaff($pdo, $eventId) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name, u.username
        FROM event_staff es 
        JOIN users u ON es.staff_id = u.id
        WHERE es.event_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function getEventCriteria($pdo, $eventId) {
    $stmt = $pdo->prepare("SELECT id, name, percentage FROM criteria WHERE event_id = ? ORDER BY id");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function getStaffList($pdo) {
    $stmt = $pdo->query("
        SELECT id, full_name, username 
        FROM users 
        WHERE role = 'staff' 
        ORDER BY full_name
    ");
    echo json_encode($stmt->fetchAll());
}

function getJudgeList($pdo) {
    $stmt = $pdo->query("SELECT id, full_name, username FROM users WHERE role = 'judge' ORDER BY full_name");
    echo json_encode($stmt->fetchAll());
}

function getEventJudges($pdo, $eventId) {
    $stmt = $pdo->prepare("\n        SELECT u.id, u.full_name, u.username\n        FROM event_judges ej\n        JOIN users u ON u.id = ej.judge_id\n        WHERE ej.event_id = ?\n        ORDER BY u.full_name\n    ");
    $stmt->execute([$eventId]);
    return $stmt->fetchAll();
}

function saveEventJudges($pdo, $eventId, $judgeIds) {
    $criteriaCheck = $pdo->prepare('SELECT c.has_criteria FROM events e JOIN categories c ON c.id = e.category_id WHERE e.id = ?');
    $criteriaCheck->execute([$eventId]);
    if (!(bool)$criteriaCheck->fetchColumn()) {
        $pdo->prepare('DELETE FROM event_judges WHERE event_id = ?')->execute([$eventId]);
        return;
    }
    $pdo->prepare('DELETE FROM event_judges WHERE event_id = ?')->execute([$eventId]);
    if (!is_array($judgeIds) || !$judgeIds) return;

    $valid = $pdo->prepare("SELECT id FROM users WHERE role = 'judge' AND id = ?");
    $insert = $pdo->prepare('INSERT INTO event_judges (event_id, judge_id, assigned_by) VALUES (?, ?, ?)');
    $seen = [];
    foreach ($judgeIds as $judgeId) {
        $judgeId = (int)$judgeId;
        if (!$judgeId || isset($seen[$judgeId])) continue;
        $valid->execute([$judgeId]);
        if ($valid->fetchColumn()) {
            $insert->execute([$eventId, $judgeId, $_SESSION['user']['id']]);
            $seen[$judgeId] = true;
        }
    }
}

function isAssignedJudge($pdo, $eventId) {
    if (!hasRole('judge')) return false;
    $stmt = $pdo->prepare('SELECT 1 FROM event_judges WHERE event_id = ? AND judge_id = ?');
    $stmt->execute([$eventId, $_SESSION['user']['id']]);
    return (bool)$stmt->fetchColumn();
}

function canScoreEvent($pdo, $eventId) {
    if (hasRole('superadmin', 'admin')) return true;
    $criteriaStmt = $pdo->prepare('SELECT c.has_criteria FROM events e JOIN categories c ON c.id = e.category_id WHERE e.id = ?');
    $criteriaStmt->execute([$eventId]);
    $hasCriteria = (bool)$criteriaStmt->fetchColumn();
    if ($hasCriteria) return isAssignedJudge($pdo, $eventId);
    if (hasRole('staff')) return canManageEvent($pdo, $eventId);
    return false;
}

function canViewEvent($pdo, $eventId) {
    if (hasRole('superadmin', 'admin')) return true;
    if (hasRole('student')) return true;
    if (hasRole('judge')) return isAssignedJudge($pdo, $eventId);
    if (hasRole('staff')) return canManageEvent($pdo, $eventId);
    return false;
}

function getCategories($pdo) {
    $stmt = $pdo->query("
        SELECT id, name, parent_group, has_criteria 
        FROM categories 
        ORDER BY parent_group, name
    ");
    $rows = $stmt->fetchAll();
    $grouped = [];
    foreach ($rows as $r) {
        $grouped[$r['parent_group']][] = $r;
    }
    echo json_encode($grouped);
}

function categoryStaffMap($pdo) {
    $stmt = $pdo->query("
        SELECT cs.category_id, u.id, u.full_name
        FROM category_staff cs
        JOIN users u ON cs.staff_id = u.id
        ORDER BY u.full_name
    ");
    $rows = $stmt->fetchAll();

    $map = [];
    foreach ($rows as $r) {
        $cid = (int)$r['category_id'];
        $map[$cid] = $map[$cid] ?? [];
        $map[$cid][] = ['id' => (int)$r['id'], 'full_name' => $r['full_name']];
    }
    echo json_encode($map);
}

function createEvent($pdo, $data) {
    // Validate required fields
    if (empty($data['name']) || empty($data['category_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name and category required']);
        return;
    }

    // Schedule
    $start = !empty($data['start_datetime']) ? $data['start_datetime'] : null;
    $end   = !empty($data['end_datetime'])   ? $data['end_datetime']   : null;

    if ($start && $end && strtotime($end) < strtotime($start)) {
        http_response_code(400);
        echo json_encode(['error' => 'End time cannot be before start time']);
        return;
    }

    // Criteria validation
    if (!empty($data['has_criteria']) && !empty($data['criteria'])) {
        $total = array_sum(array_column($data['criteria'], 'percentage'));
        if (abs($total - 100) > 0.5) {
            http_response_code(400);
            echo json_encode(['error' => "Criteria must total 100% (got {$total}%)"]);
            return;
        }
    }

    $pdo->beginTransaction();
    try {
        // Insert event
        $stmt = $pdo->prepare("
            INSERT INTO events
                (name, category_id, players_representative, start_datetime, end_datetime, status, created_by)
            VALUES (?, ?, ?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([
            $data['name'],
            $data['category_id'],
            $data['players_representative'] ?? '',
            $start,
            $end,
            $_SESSION['user']['id'],
        ]);
        $eventId = $pdo->lastInsertId();

        // AUTO-ASSIGN STAFF from category_staff based on the chosen game category
        $staffStmt = $pdo->prepare("SELECT staff_id FROM category_staff WHERE category_id = ?");
        $staffStmt->execute([$data['category_id']]);
        $autoStaff = array_column($staffStmt->fetchAll(), 'staff_id');

        if (!empty($autoStaff)) {
            $ss = $pdo->prepare("INSERT INTO event_staff (event_id, staff_id) VALUES (?, ?)");
            foreach ($autoStaff as $sid) {
                $ss->execute([$eventId, $sid]);
            }
        }

        if (hasRole('superadmin', 'admin')) {
            saveEventJudges($pdo, $eventId, $data['judge_ids'] ?? []);
        }

        // Insert criteria
        if (!empty($data['criteria'])) {
            $cs = $pdo->prepare("INSERT INTO criteria (event_id, name, percentage) VALUES (?, ?, ?)");
            foreach ($data['criteria'] as $c) {
                if (!empty($c['name']) && is_numeric($c['percentage'])) {
                    $cs->execute([$eventId, $c['name'], $c['percentage']]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true, 'id' => $eventId]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function updateEvent($pdo, $data) {
    if (empty($data['id']) || empty($data['name']) || empty($data['category_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID, name, and category are required']);
        return;
    }

    $eventId = (int)$data['id'];
    if (hasRole('staff')) {
        $access = $pdo->prepare('SELECT 1 FROM event_staff WHERE event_id = ? AND staff_id = ?');
        $access->execute([$eventId, $_SESSION['user']['id']]);
        if (!$access->fetchColumn()) {
            http_response_code(403);
            echo json_encode(['error' => 'You are not assigned to this event']);
            return;
        }
    }

    $exists = $pdo->prepare('SELECT 1 FROM events WHERE id = ?');
    $exists->execute([$eventId]);
    if (!$exists->fetchColumn()) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }

    // Schedule
    $start = !empty($data['start_datetime']) ? $data['start_datetime'] : null;
    $end   = !empty($data['end_datetime'])   ? $data['end_datetime']   : null;

    if ($start && $end && strtotime($end) < strtotime($start)) {
        http_response_code(400);
        echo json_encode(['error' => 'End time cannot be before start time']);
        return;
    }

    // Criteria validation
    if (!empty($data['has_criteria']) && !empty($data['criteria'])) {
        $total = array_sum(array_column($data['criteria'], 'percentage'));
        if (abs($total - 100) > 0.5) {
            http_response_code(400);
            echo json_encode(['error' => "Criteria must total 100% (got {$total}%)"]);
            return;
        }
    }

    $pdo->beginTransaction();
    try {
        // Update event
        $stmt = $pdo->prepare("
            UPDATE events 
            SET name = ?, category_id = ?, players_representative = ?,
                start_datetime = ?, end_datetime = ?
            WHERE id = ?
        ");
        $stmt->execute([
            $data['name'],
            $data['category_id'],
            $data['players_representative'] ?? '',
            $start,
            $end,
            $eventId,
        ]);

        // Replace staff — auto-populate from the event's category
        $pdo->prepare("DELETE FROM event_staff WHERE event_id = ?")->execute([$eventId]);

        $staffStmt = $pdo->prepare("SELECT staff_id FROM category_staff WHERE category_id = ?");
        $staffStmt->execute([$data['category_id']]);
        $autoStaff = array_column($staffStmt->fetchAll(), 'staff_id');

        if (!empty($autoStaff)) {
            $ss = $pdo->prepare("INSERT INTO event_staff (event_id, staff_id) VALUES (?, ?)");
            foreach ($autoStaff as $sid) {
                $ss->execute([$eventId, $sid]);
            }
        }

        if (hasRole('superadmin', 'admin')) {
            saveEventJudges($pdo, $eventId, $data['judge_ids'] ?? []);
        }

        // Replace criteria
        $pdo->prepare("DELETE FROM criteria WHERE event_id = ?")->execute([$eventId]);

        if (!empty($data['criteria'])) {
            $cs = $pdo->prepare("INSERT INTO criteria (event_id, name, percentage) VALUES (?, ?, ?)");
            foreach ($data['criteria'] as $c) {
                if (!empty($c['name']) && is_numeric($c['percentage'])) {
                    $cs->execute([$eventId, $c['name'], $c['percentage']]);
                }
            }
        }

        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function deleteEvent($pdo, $data) {
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    $fileStmt = $pdo->prepare('SELECT storage_name FROM event_files WHERE event_id = ?');
    $fileStmt->execute([(int)$data['id']]);
    foreach ($fileStmt->fetchAll() as $file) {
        $path = __DIR__ . '/../assets/uploads/events/' . basename($file['storage_name']);
        if (is_file($path)) unlink($path);
    }
    $stmt = $pdo->prepare("DELETE FROM events WHERE id = ?");
    $stmt->execute([$data['id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }
    echo json_encode(['success' => true]);
}

function approveEvent($pdo, $data) {
    if (empty($data['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID required']);
        return;
    }
    $status = $data['status'] ?? 'approved';
    if (!in_array($status, ['pending','approved','rejected'], true)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid status']);
        return;
    }
    $stmt = $pdo->prepare("UPDATE events SET status = ? WHERE id = ?");
    $stmt->execute([$status, $data['id']]);
    if ($stmt->rowCount() === 0) {
        http_response_code(404);
        echo json_encode(['error' => 'Event not found']);
        return;
    }
    echo json_encode(['success' => true]);
}

function getEventParticipantsRows($pdo, $eventId) {
    $stmt = $pdo->prepare("\n        SELECT p.id, p.name, p.team_name, p.participant_type,\n               r.rank_position, r.score, r.is_winner, r.notes\n        FROM event_participants p\n        LEFT JOIN event_results r ON r.participant_id = p.id AND r.event_id = p.event_id\n        WHERE p.event_id = ?\n        ORDER BY COALESCE(r.rank_position, 2147483647), COALESCE(p.team_name, p.name)\n    ");
    $stmt->execute([$eventId]);
    $participants = $stmt->fetchAll();
    $members = $pdo->prepare('SELECT participant_id, member_name FROM event_participant_members WHERE participant_id = ? ORDER BY id');
    $scoreStmt = hasRole('judge')
        ? $pdo->prepare('SELECT criterion_id, score, notes, 1 AS judge_count FROM event_criterion_scores WHERE event_id = ? AND participant_id = ? AND judge_id = ?')
        : $pdo->prepare('SELECT criterion_id, AVG(score) AS score, NULL AS notes, COUNT(DISTINCT judge_id) AS judge_count FROM event_criterion_scores WHERE event_id = ? AND participant_id = ? GROUP BY criterion_id');
    foreach ($participants as &$participant) {
        $members->execute([$participant['id']]);
        $participant['members'] = array_column($members->fetchAll(), 'member_name');
        $params = hasRole('judge') ? [$eventId, $participant['id'], $_SESSION['user']['id']] : [$eventId, $participant['id']];
        $scoreStmt->execute($params);
        $participant['criterion_scores'] = $scoreStmt->fetchAll();
    }
    return $participants;
}

function getEventParticipants($pdo, $eventId) {
    if (!canViewEvent($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this event']);
        return;
    }
    echo json_encode(getEventParticipantsRows($pdo, $eventId));
}

function canManageEvent($pdo, $eventId) {
    if (hasRole('superadmin', 'admin')) return true;
    $stmt = $pdo->prepare('SELECT 1 FROM event_staff WHERE event_id = ? AND staff_id = ?');
    $stmt->execute([$eventId, $_SESSION['user']['id']]);
    return (bool)$stmt->fetchColumn();
}

function saveParticipants($pdo, $data) {
    $eventId = (int)($data['event_id'] ?? 0);
    if (!$eventId || !canManageEvent($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned to this event']);
        return;
    }
    if (!isset($data['participants']) || !is_array($data['participants'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Participants must be an array']);
        return;
    }

    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare('SELECT id, name, team_name, participant_type FROM event_participants WHERE event_id = ?');
        $existingStmt->execute([$eventId]);
        $existing = [];
        foreach ($existingStmt->fetchAll() as $participant) {
            $identity = $participant['participant_type'] === 'team'
                ? ($participant['team_name'] ?: $participant['name'])
                : $participant['name'];
            $existing[strtolower($identity)] = (int)$participant['id'];
        }
        $submitted = [];
        $update = $pdo->prepare('UPDATE event_participants SET name = ?, team_name = ?, participant_type = ? WHERE id = ? AND event_id = ?');
        $insert = $pdo->prepare('INSERT INTO event_participants (event_id, name, team_name, participant_type) VALUES (?, ?, ?, ?)');
        $deleteMembers = $pdo->prepare('DELETE FROM event_participant_members WHERE participant_id = ?');
        $insertMember = $pdo->prepare('INSERT INTO event_participant_members (participant_id, member_name) VALUES (?, ?)');
        foreach ($data['participants'] as $participant) {
            $type = ($participant['participant_type'] ?? 'team') === 'individual' ? 'individual' : 'team';
            $teamName = trim($participant['team_name'] ?? '');
            $name = $type === 'team' ? ($teamName ?: trim($participant['name'] ?? '')) : trim($participant['name'] ?? '');
            $key = strtolower($type === 'team' ? ($teamName ?: $name) : $name);
            if (!$name || isset($submitted[$key])) continue;
            $submitted[$key] = true;
            if (isset($existing[$key])) {
                $participantId = $existing[$key];
                $update->execute([$name, $type === 'team' ? $teamName : null, $type, $participantId, $eventId]);
            } else {
                $insert->execute([$eventId, $name, $type === 'team' ? $teamName : null, $type]);
                $participantId = (int)$pdo->lastInsertId();
            }
            $deleteMembers->execute([$participantId]);
            if ($type === 'team' && is_array($participant['members'] ?? null)) {
                foreach ($participant['members'] as $memberName) {
                    $memberName = trim($memberName);
                    if ($memberName) $insertMember->execute([$participantId, $memberName]);
                }
            }
        }
        $remove = $pdo->prepare('DELETE FROM event_participants WHERE event_id = ? AND id = ?');
        foreach ($existing as $key => $participantId) {
            if (!isset($submitted[$key])) $remove->execute([$eventId, $participantId]);
        }
        $pdo->commit();
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function saveResults($pdo, $data) {
    $eventId = (int)($data['event_id'] ?? 0);
    if (!$eventId || !canScoreEvent($pdo, $eventId)) {
        http_response_code(403);
        echo json_encode(['error' => 'You are not assigned as a judge or event manager']);
        return;
    }
    $criteriaStmt = $pdo->prepare('SELECT c.has_criteria FROM events e JOIN categories c ON c.id = e.category_id WHERE e.id = ?');
    $criteriaStmt->execute([$eventId]);
    if ((bool)$criteriaStmt->fetchColumn()) {
        if (!is_array($data['criterion_scores'] ?? null)) {
            http_response_code(400);
            echo json_encode(['error' => 'Criterion scores must be an array']);
            return;
        }
        saveCriterionScores($pdo, $eventId, $data['criterion_scores']);
    } else {
        if (!is_array($data['results'] ?? null)) {
            http_response_code(400);
            echo json_encode(['error' => 'Scores must be an array']);
            return;
        }
        saveDirectResults($pdo, $eventId, $data['results']);
    }
    echo json_encode(['success' => true]);
}

function saveDirectResults($pdo, $eventId, $results) {
    $valid = $pdo->prepare('SELECT id FROM event_participants WHERE event_id = ?');
    $valid->execute([$eventId]);
    $participantIds = array_flip(array_map('intval', array_column($valid->fetchAll(), 'id')));
    $scores = [];
    foreach ($results as $result) {
        $participantId = (int)($result['participant_id'] ?? 0);
        $score = $result['score'] ?? '';
        if (isset($participantIds[$participantId]) && is_numeric($score) && $score >= 0) {
            $scores[$participantId] = (float)$score;
        }
    }
    arsort($scores, SORT_NUMERIC);
    $stmt = $pdo->prepare("\n        INSERT INTO event_results (event_id, participant_id, rank_position, score, is_winner, updated_by)\n        VALUES (?, ?, ?, ?, ?, ?)\n        ON DUPLICATE KEY UPDATE rank_position = VALUES(rank_position), score = VALUES(score), is_winner = VALUES(is_winner), updated_by = VALUES(updated_by)\n    ");
    $rank = 1;
    foreach ($scores as $participantId => $score) {
        $stmt->execute([$eventId, $participantId, $rank, round($score, 2), $rank === 1 ? 1 : 0, $_SESSION['user']['id']]);
        $rank++;
    }
}

function saveCriterionScores($pdo, $eventId, $criterionScores) {
    $pdo->prepare('DELETE FROM event_results WHERE event_id = ?')->execute([$eventId]);
    if (!is_array($criterionScores)) {
        http_response_code(400);
        echo json_encode(['error' => 'Criterion scores must be an array']);
        return;
    }

    $criteriaStmt = $pdo->prepare('SELECT id, percentage FROM criteria WHERE event_id = ?');
    $criteriaStmt->execute([$eventId]);
    $criteria = [];
    foreach ($criteriaStmt->fetchAll() as $criterion) {
        $criteria[(int)$criterion['id']] = (float)$criterion['percentage'];
    }

    $participantStmt = $pdo->prepare('SELECT id FROM event_participants WHERE event_id = ?');
    $participantStmt->execute([$eventId]);
    $participants = array_map('intval', array_column($participantStmt->fetchAll(), 'id'));

    $stmt = $pdo->prepare("\n        INSERT INTO event_criterion_scores\n            (event_id, criterion_id, participant_id, judge_id, score, notes)\n        VALUES (?, ?, ?, ?, ?, ?)\n        ON DUPLICATE KEY UPDATE score = VALUES(score), notes = VALUES(notes)\n    ");
    foreach ($criterionScores as $score) {
        $criterionId = (int)($score['criterion_id'] ?? 0);
        $participantId = (int)($score['participant_id'] ?? 0);
        $value = $score['score'] ?? '';
        if (!isset($criteria[$criterionId]) || !in_array($participantId, $participants, true) || !is_numeric($value)) continue;
        $value = (float)$value;
        if ($value < 0 || $value > 100) continue;
        $stmt->execute([$eventId, $criterionId, $participantId, $_SESSION['user']['id'], $value, trim($score['notes'] ?? '') ?: null]);
    }

    $averages = $pdo->prepare("\n        SELECT participant_id, criterion_id, AVG(score) AS average_score\n        FROM event_criterion_scores\n        WHERE event_id = ?\n        GROUP BY participant_id, criterion_id\n    ");
    $averages->execute([$eventId]);
    $totals = array_fill_keys($participants, 0.0);
    foreach ($averages->fetchAll() as $row) {
        $criterionId = (int)$row['criterion_id'];
        if (isset($criteria[$criterionId])) {
            $participantId = (int)$row['participant_id'];
            $totals[$participantId] = ($totals[$participantId] ?? 0) + ((float)$row['average_score'] * $criteria[$criterionId] / 100);
        }
    }
    arsort($totals, SORT_NUMERIC);
    $update = $pdo->prepare("\n        INSERT INTO event_results (event_id, participant_id, rank_position, score, is_winner, updated_by)\n        VALUES (?, ?, ?, ?, ?, ?)\n        ON DUPLICATE KEY UPDATE rank_position = VALUES(rank_position), score = VALUES(score), is_winner = VALUES(is_winner), updated_by = VALUES(updated_by)\n    ");
    $rank = 1;
    foreach ($totals as $participantId => $total) {
        $update->execute([$eventId, $participantId, $rank, round($total, 2), $rank === 1 ? 1 : 0, $_SESSION['user']['id']]);
        $rank++;
    }
}