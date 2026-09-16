<?php require_once __DIR__ . '/includes/auth.php'; requireLogin(); if (hasRole('judge')) { header('Location: events.php'); exit; } $user = currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="assets/img/logo.png">
<title>Dashboard — Event Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>window.tailwind = window.tailwind || {}; window.tailwind.config = {corePlugins: {preflight: false}};</script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">

    <!-- ============ SIDEBAR ============ -->
    <aside class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-mark"><img src="assets/img/logo.png" alt="Event Manager logo"></div>
            <div class="logo-text">Event Manager</div>
        </div>

        <nav class="sidebar-nav">
            <a class="nav-item active" href="index.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Home
            </a>
            <a class="nav-item" href="events.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <?php if (!hasRole('staff', 'student')): ?>
            <a class="nav-item" href="staff.php">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
    Staff
</a>
            <?php endif; ?>
            <?php if (!hasRole('staff', 'student')): ?>
            <a class="nav-item" href="reports.php">
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

    <!-- ============ MAIN ============ -->
    <main class="main-content">

        <!-- Top bar -->
        <div class="top-bar">
            <div class="breadcrumb">
                Home &nbsp;/&nbsp; <strong>Dashboard</strong>
            </div>
            <div class="top-actions">
                <div class="avatar-sm"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                <a href="logout.php" class="icon-btn" title="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>

        <!-- Page header -->
        <div class="page-header">
            <div class="page-title">
                <h2>Welcome back, <?= htmlspecialchars(explode(' ', $user['full_name'])[0]) ?></h2>
                <p>Here's what's happening across your events</p>
            </div>
            <?php if (canCreate()): ?>
            <a href="events.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Manage Events
            </a>
            <?php endif; ?>
        </div>

        <!-- Stat cards -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
                <div class="stat-info">
                    <div class="stat-value" id="statTotal">0</div>
                    <div class="stat-label">Total Events</div>
                </div>
            </div>
            <?php if (!hasRole('student')): ?>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="m8 12 2.5 2.5L16 9"/></svg></div>
                <div class="stat-info">
                    <div class="stat-value" id="statApproved">0</div>
                    <div class="stat-label">Approved</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M6 3h12M6 21h12M7 3c0 4 5 4 5 9s-5 5-5 9M17 3c0 4-5 4-5 9s5 5 5 9"/></svg></div>
                <div class="stat-info">
                    <div class="stat-value" id="statPending">0</div>
                    <div class="stat-label">Pending</div>
                </div>
            </div>
            <?php endif; ?>
            <div class="stat-card">
                <div class="stat-icon" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg></div>
                <div class="stat-info">
                    <div class="stat-value" id="statToday">0</div>
                    <div class="stat-label">Today</div>
                </div>
            </div>
        </div>

        <?php if (hasRole('student')): ?>
        <div class="card attendance-qr-card">
            <div class="card-header">
                <div class="card-title"><span class="dot"></span> My Attendance QR</div>
            </div>
            <div class="attendance-qr-box">
                <canvas id="studentQrCanvas" width="180" height="180" style="display:none"></canvas>
                <img id="studentQrImage" alt="Student attendance QR code" style="display:none; width:180px; height:180px; background:#fff; border-radius:12px; border:1px solid #dfe7e3; padding:10px;" />
                <div class="attendance-qr-code" id="studentQrCodeValue">EVENTMNGMT-STUDENT-0</div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Calendar + day panel -->
        <div class="calendar-layout">

            <!-- CALENDAR -->
            <div class="card calendar-card">
                <div class="calendar-header">
                    <button class="icon-btn" id="prevMonth" title="Previous month">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="15 18 9 12 15 6"/></svg>
                    </button>

                    <div class="calendar-title">
                        <h3 id="monthLabel">Month Year</h3>
                        <button class="btn btn-secondary btn-sm" id="todayBtn">Today</button>
                    </div>

                    <button class="icon-btn" id="nextMonth" title="Next month">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                    </button>
                </div>

                <div class="calendar-weekdays">
                    <div>Sun</div><div>Mon</div><div>Tue</div><div>Wed</div>
                    <div>Thu</div><div>Fri</div><div>Sat</div>
                </div>

                <div class="calendar-grid" id="calendarGrid">
                    <!-- days rendered by JS -->
                </div>
            </div>

            <!-- DAY PANEL -->
            <div class="card day-panel">
                <div class="card-header">
                    <div class="card-title"><span class="dot"></span> <span id="dayPanelTitle">Select a day</span></div>
                </div>
                <div id="dayEventsList" class="day-events">
                    <div class="empty-state">Click a day on the calendar to see its events.</div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>window.CURRENT_USER = <?= json_encode($user) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="assets/js/attendance.js?v=20260916-2"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>