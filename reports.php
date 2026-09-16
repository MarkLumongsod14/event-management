<?php
require_once __DIR__ . '/includes/auth.php';
requireLogin();
if (hasRole('student')) { header('Location: index.php'); exit; }
$user = currentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
    <title>Reports — Event Management</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script>window.tailwind = window.tailwind || {}; window.tailwind.config = {corePlugins: {preflight: false}};</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-mark"><img src="assets/img/logo.png" alt="Event Manager logo"></div>
            <div class="logo-text">Event Manager</div>
        </div>

        <nav class="sidebar-nav">
            <a class="nav-item" href="index.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Home
            </a>
            <a class="nav-item" href="events.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <?php if (!hasRole('staff', 'student')): ?>
            <a class="nav-item" href="staff.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Staff
            </a>
            <?php endif; ?>
            <?php if (!hasRole('staff', 'student')): ?>
            <a class="nav-item active" href="reports.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                Reports
            </a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-user">
            <div class="avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
            <div class="info">
                <div class="name"><?= htmlspecialchars($user['full_name']) ?></div>
                <div class="role"><?= htmlspecialchars($user['role']) ?></div>
            </div>
        </div>
    </aside>

    <main class="main-content">
        <div class="top-bar">
            <div class="breadcrumb">Home &nbsp;/&nbsp; <strong>Reports</strong></div>
            <div class="top-actions">
                <div class="avatar-sm"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                <a href="logout.php" class="icon-btn" title="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>

        <div class="page-header">
            <div class="page-title">
                <h2>Reports</h2>
                <p>Review attendance activity and export event records.</p>
            </div>
            <button id="exportAttendanceCsv" class="btn btn-primary" type="button">Export CSV</button>
        </div>

        <div class="card report-card">
            <div class="card-header report-header">
                <div class="card-title"><span class="dot"></span> Attendance report</div>
                <div class="inline-actions report-filter">
                    <label class="report-filter-label" for="reportEventSelect">Event</label>
                    <select id="reportEventSelect" class="filter-select">
                        <option value="">Loading events…</option>
                    </select>
                </div>
            </div>

            <div class="table-wrap">
                <div class="report-summary"><strong id="attendanceReportSummary">0 records</strong></div>
                <table id="attendanceReportTable" class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Event</th>
                            <th>Student</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Checked In</th>
                            <th>Checked Out</th>
                        </tr>
                    </thead>
                    <tbody id="attendanceReportTableBody">
                        <tr><td colspan="7" class="empty-state">Loading attendance report…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<script>window.CURRENT_USER = <?= json_encode($user) ?>;</script>
<script src="assets/js/attendance.js?v=20260916-2"></script>
</body>
</html>
