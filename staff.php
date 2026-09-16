<?php require_once __DIR__ . '/includes/auth.php'; requireLogin(); if (hasRole('judge', 'staff', 'student')) { header('Location: events.php'); exit; } $user = currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="assets/img/logo.png">
<title>Staff — Event Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>window.tailwind = window.tailwind || {}; window.tailwind.config = {corePlugins: {preflight: false}};</script>
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">

    <!-- SIDEBAR -->
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
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <a class="nav-item active" href="staff.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Staff
            </a>
            <?php if (!hasRole('staff')): ?>
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

    <!-- MAIN -->
    <main class="main-content">

        <div class="top-bar">
            <div class="breadcrumb">Home &nbsp;/&nbsp; <strong>Staff</strong></div>
            <div class="top-actions">
                <div class="avatar-sm"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
                <a href="logout.php" class="icon-btn" title="Logout">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                </a>
            </div>
        </div>

        <div class="page-header">
            <div class="page-title">
                <h2>Staff Management</h2>
                <p>Add staff members and assign them to events</p>
            </div>
            <?php if (hasRole('superadmin','admin')): ?>
            <button class="btn btn-primary" onclick="openStaffModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Staff
            </button>
            <?php endif; ?>
        </div>

        <!-- FILTER BAR -->
        <div class="filter-bar">
            <div class="search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="staffSearch" placeholder="Search by name, username, or email…">
            </div>
            <select id="roleFilter" class="filter-select">
                <option value="">All Roles</option>
                <option value="staff">Staff</option>
                <option value="judge">Judge</option>
                <option value="student">Audience</option>
                <option value="admin">Admin</option>
                <option value="superadmin">Superadmin</option>
            </select>
        </div>

        <!-- STAFF GRID -->
        <div id="staffGrid" class="staff-grid">
            <div class="empty-state">Loading staff…</div>
        </div>
    </main>
</div>

<!-- ============ STAFF MODAL ============ -->
<div class="modal-overlay hidden" id="staffModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="staffModalTitle">Add Staff</h3>
            <button class="icon-btn" onclick="closeStaffModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="staffForm" class="modal-body">
            <input type="hidden" id="staffId">

            <div class="form-row">
                <div class="form-group">
                    <label class="required">Full Name</label>
                    <input type="text" id="staffFullName" required placeholder="e.g., Prof. Reyes">
                </div>
                <div class="form-group">
                    <label class="required">Username</label>
                    <input type="text" id="staffUsername" required placeholder="e.g., reyes">
                </div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="staffEmail" placeholder="optional">
                </div>
                <div class="form-group">
                    <label class="required">Role</label>
                    <select id="staffRole" required>
                        <option value="staff">Staff</option>
                        <option value="judge">Judge</option>
                        <option value="student">Audience</option>
                        <option value="admin">Admin</option>
                        <option value="superadmin">Superadmin</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label id="pwdLabel">Password</label>
                <input type="password" id="staffPassword" placeholder="Min 6 characters">
                <div class="small-note" id="pwdNote">Required for new staff.</div>
            </div>

            <!-- EVENT ASSIGNMENT -->
            <!-- GAME ASSIGNMENT -->
<div class="form-group">
    <label>Assigned Games</label>
    <div class="game-assign-list" id="gameAssignList">
        <div class="small-note">Loading games…</div>
    </div>
    <div class="small-note">Staff will be auto-assigned to any event under these games. Judges are assigned per event.</div>
</div>
        </form>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
            <button type="submit" form="staffForm" class="btn btn-primary" id="staffSubmitBtn">Save</button>
        </div>
    </div>
</div>

<script>window.CURRENT_USER = <?= json_encode($user) ?>;</script>
<script src="assets/js/staff.js"></script>
</body>
</html>