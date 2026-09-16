<?php require_once __DIR__ . '/includes/auth.php'; requireLogin(); $user = currentUser(); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="assets/img/logo.png">
<title>Events — Event Management</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script>window.tailwind = window.tailwind || {}; window.tailwind.config = {corePlugins: {preflight: false}};</script>
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.min.js"></script>
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
            <?php if (!hasRole('judge')): ?>
            <a class="nav-item" href="index.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Home
            </a>
            <?php endif; ?>
            <a class="nav-item active" href="events.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Events
            </a>
            <?php if (!hasRole('judge', 'staff', 'student')): ?>
            <a class="nav-item" href="staff.php">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                Staff
            </a>
            <?php endif; ?>
            <?php if (!hasRole('judge', 'staff', 'student')): ?>
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
                Home &nbsp;/&nbsp; <strong>Events</strong>
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
                <h2>All Events</h2>
                <p>Browse, filter, and manage every event in the system</p>
            </div>
            <div class="inline-actions">
            <?php if (canCreate()): ?>
            <button class="btn btn-primary" onclick="openCreateModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                New Event
            </button>
            <?php endif; ?>
            <?php if (!hasRole('student')): ?>
                <button class="btn btn-secondary" onclick="openAttendanceModal()">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16v10H4z"/><path d="M8 12h8M8 9h8M8 15h5"/></svg>
                    Attendance
                </button>
            <?php endif; ?>
            </div>
        </div>

        <!-- Filter bar -->
        <div class="filter-bar">
            <div class="search-wrap">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchInput" placeholder="Search events, players, staff…">
            </div>

            <select id="groupFilter" class="filter-select">
                <option value="">All Groups</option>
                <option value="IT GAMES">IT Games</option>
                <option value="Ball Games">Ball Games</option>
                <option value="Esports">Esports</option>
                <option value="Board Game">Board Game</option>
                <option value="Other Games">Other Games</option>
                <option value="Games with Criteria">Games with Criteria</option>
            </select>

            <select id="statusFilter" class="filter-select">
                <option value="">All Status</option>
                <option value="approved">Approved</option>
                <option value="pending">Pending</option>
                <option value="rejected">Rejected</option>
            </select>

            <div class="view-toggle">
                <button class="view-btn active" data-view="grid" title="Grid view">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/></svg>
                </button>
                <button class="view-btn" data-view="list" title="List view">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                </button>
            </div>
        </div>

        <!-- Event grid -->
        <div id="eventsGrid" class="events-grid">
            <div class="empty-state">Loading events…</div>
        </div>
    </main>
</div>

<!-- ============ CREATE / EDIT MODAL ============ -->
<div class="modal-overlay hidden" id="eventModal">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Create New Event</h3>
            <button class="icon-btn" onclick="closeModal()">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <form id="eventForm" class="modal-body">
            <input type="hidden" id="eventId">

            <div class="form-group">
                <label class="required">Event Name</label>
                <input type="text" id="eventName" required placeholder="e.g., Programming Contest 2025">
            </div>

            <div class="form-group">
                <label class="required">Category</label>
                <select id="categorySelect" required>
                    <option value="">Loading…</option>
                </select>
            </div>

            <div class="form-group">
    <label>Staff Assigned <span class="small-note" style="font-weight:400">(auto)</span></label>
    <div id="autoStaffPreview" class="auto-staff-preview">
        <div class="small-note">Pick a category to see assigned staff.</div>
    </div>
</div>

            <div class="form-group" id="judgeAssignmentGroup">
                <label>Judges Assigned</label>
                <select id="judgeSelect" class="judge-select" multiple size="4">
                    <option disabled>Loading judges…</option>
                </select>
                <div class="small-note">Judges can score only the events assigned to them.</div>
            </div>

            <div class="form-group">
                <div class="criteria-header">
                    <label>Participants / Teams</label>
                    <button type="button" class="btn btn-secondary btn-sm" id="addParticipantBtn">+ Add</button>
                </div>
                <div id="participantsContainer" class="participants-container"></div>
                <div class="small-note">Add each team or individual who will receive a ranking.</div>
            </div>
            <div class="form-row">
    <div class="form-group">
        <label class="required">Start Date &amp; Time</label>
        <input type="datetime-local" id="startDatetime" required>
    </div>
    <div class="form-group">
        <label class="required">End Date &amp; Time</label>
        <input type="datetime-local" id="endDatetime" required>
    </div>
</div>
            <div id="criteriaBlock" class="criteria-section hidden">
                <div class="criteria-header">
                    <span class="inline-flex items-center gap-2"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M12 3v18M5 6h14M7 6l-3 6a3 3 0 0 0 6 0L7 6ZM17 6l-3 6a3 3 0 0 0 6 0l-3-6ZM8 21h8"/></svg> Judging Criteria</span>
                    <button type="button" class="btn btn-secondary btn-sm" id="addCriteriaBtn">+ Add</button>
                </div>
                <div id="criteriaContainer"></div>
                <div class="small-note">Total must equal 100%.</div>
            </div>

            <!-- FILES -->
            <div class="form-group" id="filesSection">
                <label>Attachments</label>
                <div class="file-drop" id="fileDrop">
                    <input type="file" id="fileInput" multiple
                           accept="image/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx,.zip,.txt"
                           style="display:none">
                    <div class="file-drop-inner" onclick="document.getElementById('fileInput').click()">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="17 8 12 3 7 8"/>
                            <line x1="12" y1="3" x2="12" y2="15"/>
                        </svg>
                        <div class="file-drop-text">
                            <strong>Click to upload</strong> or drag &amp; drop
                            <div class="small-note">Images, PDFs, videos, docs · Max 20 MB each</div>
                        </div>
                    </div>
                    <div id="filePreview" class="file-preview"></div>
                </div>
            </div>
        </form>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
            <button type="submit" form="eventForm" class="btn btn-primary" id="submitBtn">Create Event</button>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="resultsModal">
    <div class="modal modal-wide">
        <div class="modal-header">
            <h3 id="resultsModalTitle">Event Results</h3>
            <button class="icon-btn" onclick="closeResultsModal()" aria-label="Close results"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="modal-body">
            <div id="resultsEventSummary" class="event-summary"></div>
            <div class="results-section">
                <div class="criteria-header">
                    <span>Criterion scoring</span>
                    <span class="small-note">Scores are weighted automatically. The highest total becomes 1st place.</span>
                </div>
                <div id="resultsContainer"></div>
            </div>
            <div id="resultsMessage" class="small-note"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeResultsModal()">Close</button>
            <button type="button" class="btn btn-primary" id="saveResultsBtn">Save Results</button>
        </div>
    </div>
</div>

<div class="modal-overlay hidden" id="attendanceModal">
    <div class="modal attendance-modal">
        <div class="modal-header">
            <h3>Attendance Check-In</h3>
            <button class="icon-btn" onclick="closeAttendanceModal()" aria-label="Close attendance"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
        </div>
        <div class="modal-body attendance-body">
            <div class="attendance-controls">
                <label>Approved Event</label>
                <select id="attendanceEventSelect">
                    <option value="">Loading events…</option>
                </select>
            </div>
            <div class="attendance-controls">
                <label>Student QR / Code</label>
                <input type="text" id="attendanceStudentCode" placeholder="Scan or type EVENTMNGMT-STUDENT-#">
            </div>
            <div class="attendance-controls">
                <label>Attendance Mode</label>
                <select id="attendanceModeSelect" <?= hasRole('superadmin', 'admin') ? '' : 'disabled' ?>>
                    <option value="closed">Closed</option>
                    <option value="check_in">Open Check In</option>
                    <option value="check_out">Open Check Out</option>
                </select>
                <?php if (!hasRole('superadmin', 'admin')): ?>
                    <small class="small-note">Only admin accounts can change the attendance mode.</small>
                <?php endif; ?>
            </div>
            <div class="attendance-controls">
                <label>Attendance Status</label>
                <select id="attendanceStatusSelect">
                    <option value="present">Present</option>
                    <option value="late">Late</option>
                    <option value="excused">Excused</option>
                    <option value="absent">Absent</option>
                </select>
            </div>
            <div class="attendance-action-group">
                <button type="button" class="btn btn-primary" id="attendanceCheckInBtn">Check In</button>
                <button type="button" class="btn btn-secondary" id="attendanceCheckOutBtn">Check Out</button>
                <button type="button" class="btn btn-secondary" id="attendanceScanCameraBtn">Use Camera</button>
            </div>
            <div id="cameraScannerWrap" class="camera-scanner hidden">
                <video id="attendanceScannerVideo" autoplay playsinline muted></video>
            </div>
            <input type="hidden" id="attendanceEventId">
            <div class="attendance-panel">
                <div class="attendance-head">
                    <h4>Attendance List</h4>
                    <span id="attendanceStatus">0 checked in</span>
                </div>
                <div id="attendanceList" class="attendance-list">
                    <div class="empty-state">Select an approved event to view attendance.</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeAttendanceModal()">Close</button>
        </div>
    </div>
</div>

<script>window.CURRENT_USER = <?= json_encode($user) ?>;</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
<script src="assets/js/events.js"></script>
<script src="assets/js/attendance.js?v=20260916-2"></script>
</body>
</html>