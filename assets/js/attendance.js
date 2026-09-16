const ATTENDANCE_API = 'api/attendance.php';
let attendanceScanStream = null;
let attendanceScanLoop = null;
let attendanceActionMode = 'check_in';
let lastAttendanceStudentCode = '';
let attendanceVisibleQrCode = '';

function parseStudentCode(value) {
    const raw = String(value || '').trim();
    if (!raw) return null;
    const cleaned = raw.replace(/^EVENTMNGMT-STUDENT-?/i, '');
    const num = Number(cleaned);
    return Number.isFinite(num) && num > 0 ? num : null;
}

function renderStudentQr() {
    const user = window.CURRENT_USER || {};
    const studentId = Number(user.id || 0);
    if (!studentId) return;

    const value = `EVENTMNGMT-STUDENT-${studentId}`;
    const codeEl = document.getElementById('studentQrCodeValue');
    if (codeEl) codeEl.textContent = value;

    const canvas = document.getElementById('studentQrCanvas');
    const img = document.getElementById('studentQrImage');
    if (!canvas || !img) return;

    canvas.width = 180;
    canvas.height = 180;
    canvas.style.display = 'none';
    canvas.style.backgroundColor = '#ffffff';
    canvas.style.borderRadius = '12px';
    canvas.style.padding = '10px';
    canvas.style.border = '1px solid #dfe7e3';

    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(value)}`;

    img.src = qrUrl;
    img.alt = `Student QR code: ${value}`;
    img.style.display = 'block';

    if (window.QRCode) {
        try {
            const qrOptions = {
                width: 180,
                margin: 2,
                scale: 6,
                color: { dark: '#111827', light: '#ffffff' }
            };
            QRCode.toCanvas(canvas, value, qrOptions, function (error) {
                if (!error) {
                    canvas.style.display = 'block';
                    img.style.display = 'none';
                }
            });
        } catch (error) {
            console.error(error);
        }
    }
}

function formatAttendanceTime(value) {
    if (!value) return '—';
    const date = new Date(value.replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return value;
    return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
}

async function loadAttendanceList(eventId) {
    const list = document.getElementById('attendanceList');
    const status = document.getElementById('attendanceStatus');
    const hidden = document.getElementById('attendanceEventId');
    if (!list || !status || !hidden) return;

    hidden.value = eventId;
    list.innerHTML = '<div class="empty-state">Loading attendance…</div>';

    try {
        const res = await fetch(`${ATTENDANCE_API}?action=list&event_id=${eventId}`);
        const data = await res.json();

        if (!res.ok || !Array.isArray(data)) {
            list.innerHTML = `<div class="empty-state">${(data && data.error) || 'Unable to load attendance.'}</div>`;
            return;
        }

        if (!data.length) {
            list.innerHTML = '<div class="empty-state">No students checked in yet.</div>';
            status.textContent = '0 checked in';
            return;
        }

        status.textContent = `${data.length} checked in`;
        list.innerHTML = data.map(item => `
            <div class="attendance-row">
                <div>
                    <strong>${escapeHtml(item.student_name || 'Unknown student')}</strong>
                    <small>${escapeHtml(item.username || '')}</small>
                    <small>In: ${escapeHtml(formatAttendanceTime(item.checked_in_at))} · Out: ${escapeHtml(formatAttendanceTime(item.checked_out_at))}</small>
                </div>
                <span class="status-${item.status || 'present'}">${escapeHtml(item.status || 'present')}</span>
            </div>
        `).join('');
    } catch (error) {
        list.innerHTML = '<div class="empty-state">Unable to load attendance.</div>';
    }
}

async function submitAttendanceAction(studentId, eventId, mode, statusValue) {
    const action = mode === 'check_out' ? 'check_out' : 'check_in';
    const res = await fetch(ATTENDANCE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            action,
            event_id: eventId,
            student_id: studentId,
            status: statusValue || 'present'
        })
    });

    const data = await res.json();
    if (!res.ok || !data.success) {
        throw new Error(data.error || `${mode === 'check_out' ? 'Check-out' : 'Check-in'} failed`);
    }

    return data;
}

async function submitAttendanceCheckIn() {
    const valueInput = document.getElementById('attendanceStudentCode');
    const hidden = document.getElementById('attendanceEventId');
    const statusSelect = document.getElementById('attendanceStatusSelect');
    if (!valueInput || !hidden) return;

    if (!valueInput.value && lastAttendanceStudentCode) {
        valueInput.value = lastAttendanceStudentCode;
    }

    const studentId = parseStudentCode(valueInput.value);
    const eventId = Number(hidden.value || 0);
    const statusValue = statusSelect ? statusSelect.value : 'present';

    if (!eventId) {
        showToast('Select an event first', true);
        return;
    }
    if (!studentId) {
        showToast('Scan or enter a valid student QR code', true);
        return;
    }

    try {
        const data = await submitAttendanceAction(studentId, eventId, attendanceActionMode, statusValue);
        valueInput.value = '';
        lastAttendanceStudentCode = '';
        const resolvedAction = data.action || attendanceActionMode;
        showToast(resolvedAction === 'check_out' ? 'Student checked out successfully' : 'Student checked in successfully');
        loadAttendanceList(eventId);
    } catch (error) {
        showToast(error.message || 'Attendance update failed', true);
    }
}

async function handleScannedCode(rawValue) {
    const studentId = parseStudentCode(rawValue);
    const valueInput = document.getElementById('attendanceStudentCode');
    if (!valueInput) return;
    if (!studentId) {
        showToast('The scanned value is not a valid student QR code', true);
        return;
    }

    const eventId = Number(document.getElementById('attendanceEventId')?.value || 0);
    const statusSelect = document.getElementById('attendanceStatusSelect');
    if (!eventId) {
        valueInput.value = `EVENTMNGMT-STUDENT-${studentId}`;
        showToast('Select an event before submitting the scan', true);
        return;
    }

    try {
        const data = await submitAttendanceAction(studentId, eventId, attendanceActionMode, statusSelect ? statusSelect.value : 'present');
        valueInput.value = '';
        lastAttendanceStudentCode = '';
        const resolvedAction = data.action || attendanceActionMode;
        showToast(resolvedAction === 'check_out' ? 'Student checked out successfully' : 'Student checked in successfully');
        loadAttendanceList(eventId);
    } catch (error) {
        showToast(error.message || 'Attendance update failed', true);
    }
}

async function startAttendanceCamera() {
    const video = document.getElementById('attendanceScannerVideo');
    const scannerWrap = document.getElementById('cameraScannerWrap');
    if (!video || !scannerWrap) return;

    const isLocalHost = ['localhost', '127.0.0.1', '[::1]'].includes(window.location.hostname);
    if (!window.isSecureContext && !isLocalHost) {
        showToast('Camera scanning requires HTTPS on a phone. Open this site using an HTTPS address.', true);
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
        showToast('This browser does not support camera scanning. Use manual QR entry or a modern browser.', true);
        return;
    }

    try {
        attendanceScanStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        video.srcObject = attendanceScanStream;
        scannerWrap.classList.remove('hidden');

        const scanFrame = async () => {
            if (!attendanceScanStream) return;

            let detectedQrCode = '';

            try {
                if (video.readyState >= 2) {
                    if ('BarcodeDetector' in window) {
                        const detector = new window.BarcodeDetector({ formats: ['qr_code'] });
                        const barcodes = await detector.detect(video);
                        if (barcodes && barcodes.length > 0) {
                            const rawValue = barcodes[0].rawValue;
                            if (rawValue) {
                                detectedQrCode = rawValue;
                            }
                        }
                    } else if (window.jsQR) {
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d', { willReadFrequently: true });
                        if (video.videoWidth && video.videoHeight) {
                            canvas.width = video.videoWidth;
                            canvas.height = video.videoHeight;
                            context.drawImage(video, 0, 0, canvas.width, canvas.height);
                            const imageData = context.getImageData(0, 0, canvas.width, canvas.height);
                            const code = window.jsQR(imageData.data, imageData.width, imageData.height, {
                                inversionAttempts: 'attemptBoth'
                            });

                            if (code) {
                                detectedQrCode = code.data;
                            }
                        }
                    }
                }

                if (detectedQrCode) {
                    if (detectedQrCode !== attendanceVisibleQrCode) {
                        attendanceVisibleQrCode = detectedQrCode;
                        await handleScannedCode(detectedQrCode);
                    }
                } else {
                    attendanceVisibleQrCode = '';
                }
            } catch (error) {
                console.error('QR detect error:', error);
            }

            attendanceScanLoop = requestAnimationFrame(scanFrame);
        };

        if (attendanceScanLoop) cancelAnimationFrame(attendanceScanLoop);
        attendanceScanLoop = requestAnimationFrame(scanFrame);
    } catch (error) {
        showToast('Camera access was denied. Please type the student code manually.', true);
    }
}

function stopAttendanceCamera() {
    const video = document.getElementById('attendanceScannerVideo');
    const scannerWrap = document.getElementById('cameraScannerWrap');
    if (attendanceScanLoop) cancelAnimationFrame(attendanceScanLoop);
    if (attendanceScanStream) {
        attendanceScanStream.getTracks().forEach(track => track.stop());
        attendanceScanStream = null;
    }
    attendanceVisibleQrCode = '';
    if (video) video.srcObject = null;
    if (scannerWrap) scannerWrap.classList.add('hidden');
}

async function getAttendanceModeState(eventId) {
    const modeSelect = document.getElementById('attendanceModeSelect');
    if (!modeSelect || !eventId) return 'closed';

    try {
        const res = await fetch(`${ATTENDANCE_API}?action=mode&event_id=${eventId}`);
        const data = await res.json();
        const mode = res.ok && data && data.mode ? data.mode : 'closed';
        modeSelect.value = mode;
        if (mode === 'check_in' || mode === 'check_out') {
            attendanceActionMode = mode;
            updateAttendanceActionButtons();
        }
        return mode;
    } catch {
        modeSelect.value = 'closed';
        return 'closed';
    }
}

async function saveAttendanceMode(eventId, mode) {
    const res = await fetch(ATTENDANCE_API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_mode', event_id: eventId, mode })
    });
    const data = await res.json();
    if (!res.ok || !data.success) {
        throw new Error(data.error || 'Unable to update attendance mode');
    }
    return data;
}

function validateAttendanceModeForAction(actionMode) {
    const modeSelect = document.getElementById('attendanceModeSelect');
    const currentMode = modeSelect ? modeSelect.value : 'closed';
    if (currentMode === 'closed') {
        showToast('Attendance is currently closed', true);
        return false;
    }
    if (actionMode === 'check_in' && currentMode !== 'check_in') {
        showToast('Attendance is currently open for check-out only', true);
        return false;
    }
    if (actionMode === 'check_out' && currentMode !== 'check_out') {
        showToast('Attendance is currently open for check-in only', true);
        return false;
    }
    return true;
}

function updateAttendanceActionButtons() {
    const checkInBtn = document.getElementById('attendanceCheckInBtn');
    const checkOutBtn = document.getElementById('attendanceCheckOutBtn');
    if (!checkInBtn || !checkOutBtn) return;

    checkInBtn.classList.toggle('btn-primary', attendanceActionMode === 'check_in');
    checkInBtn.classList.toggle('btn-secondary', attendanceActionMode !== 'check_in');
    checkInBtn.setAttribute('aria-pressed', attendanceActionMode === 'check_in' ? 'true' : 'false');

    checkOutBtn.classList.toggle('btn-primary', attendanceActionMode === 'check_out');
    checkOutBtn.classList.toggle('btn-secondary', attendanceActionMode !== 'check_out');
    checkOutBtn.setAttribute('aria-pressed', attendanceActionMode === 'check_out' ? 'true' : 'false');
}

function setupAttendanceModal() {
    const checkInBtn = document.getElementById('attendanceCheckInBtn');
    const checkOutBtn = document.getElementById('attendanceCheckOutBtn');
    const cameraBtn = document.getElementById('attendanceScanCameraBtn');
    const eventSelect = document.getElementById('attendanceEventSelect');
    const modeSelect = document.getElementById('attendanceModeSelect');
    const attendeeInput = document.getElementById('attendanceStudentCode');
    const statusSelect = document.getElementById('attendanceStatusSelect');

    if (checkInBtn) {
        checkInBtn.onclick = () => {
            attendanceActionMode = 'check_in';
            updateAttendanceActionButtons();
            if (!validateAttendanceModeForAction(attendanceActionMode)) return;
            submitAttendanceCheckIn();
        };
    }
    if (checkOutBtn) {
        checkOutBtn.onclick = () => {
            attendanceActionMode = 'check_out';
            updateAttendanceActionButtons();
            if (!validateAttendanceModeForAction(attendanceActionMode)) return;
            submitAttendanceCheckIn();
        };
    }
    if (cameraBtn) {
        cameraBtn.onclick = () => {
            if (attendanceScanStream) {
                stopAttendanceCamera();
                return;
            }
            if (modeSelect && modeSelect.value === 'closed') {
                showToast('Attendance is currently closed', true);
                return;
            }
            startAttendanceCamera();
        };
    }

    if (eventSelect) {
        eventSelect.onchange = async () => {
            const eventId = Number(eventSelect.value || 0);
            if (eventId) {
                loadAttendanceList(eventId);
                await getAttendanceModeState(eventId);
            }
        };
    }

    if (modeSelect) {
        modeSelect.onchange = async () => {
            const eventId = Number(eventSelect?.value || 0);
            const mode = modeSelect.value;
            if (!eventId) {
                showToast('Select an event before changing the attendance mode', true);
                modeSelect.value = 'closed';
                return;
            }

            try {
                await saveAttendanceMode(eventId, mode);
                if (mode === 'check_in' || mode === 'check_out') {
                    attendanceActionMode = mode;
                    updateAttendanceActionButtons();
                }
                showToast(mode === 'closed' ? 'Attendance closed' : mode === 'check_in' ? 'Check-in mode opened' : 'Check-out mode opened');
            } catch (error) {
                showToast(error.message || 'Failed to update attendance mode', true);
                modeSelect.value = 'closed';
            }
        };
    }

    if (statusSelect) {
        statusSelect.value = 'present';
    }

    updateAttendanceActionButtons();

    if (attendeeInput) {
        attendeeInput.addEventListener('input', () => {
            const typedValue = attendeeInput.value.trim();
            if (typedValue) {
                lastAttendanceStudentCode = typedValue;
            }
        });

        attendeeInput.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                event.preventDefault();
                if (!validateAttendanceModeForAction(attendanceActionMode)) return;
                submitAttendanceCheckIn();
            }
        });
    }
}

function openAttendanceModal(eventId) {
    const modal = document.getElementById('attendanceModal');
    const eventSelect = document.getElementById('attendanceEventSelect');
    if (!modal || !eventSelect) return;

    if (eventId) {
        eventSelect.value = String(eventId);
        loadAttendanceList(eventId);
    }
    modal.classList.remove('hidden');
}

function closeAttendanceModal() {
    stopAttendanceCamera();
    const modal = document.getElementById('attendanceModal');
    if (modal) modal.classList.add('hidden');
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"']/g, m => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[m]));
}

function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => { t.classList.remove('show'); setTimeout(() => t.remove(), 300); }, 2200);
}

function populateAttendanceEvents() {
    const eventSelect = document.getElementById('attendanceEventSelect');
    if (!eventSelect) return;

    const events = Array.isArray(window.EVENTS_DATA) ? window.EVENTS_DATA : [];
    const approved = events.filter(e => e.status === 'approved');

    if (!approved.length) {
        eventSelect.innerHTML = '<option value="">No approved events</option>';
        return;
    }

    eventSelect.innerHTML = '<option value="">Select an event</option>' + approved.map(e => `<option value="${e.id}">${escapeHtml(e.name)}</option>`).join('');
}

async function loadAttendanceReport(eventId = '') {
    const tableBody = document.getElementById('attendanceReportTableBody');
    const summaryEl = document.getElementById('attendanceReportSummary');
    if (!tableBody) return;

    tableBody.innerHTML = '<tr><td colspan="7" class="empty-state">Loading attendance report…</td></tr>';

    try {
        const url = eventId ? `${ATTENDANCE_API}?action=report&event_id=${encodeURIComponent(eventId)}` : `${ATTENDANCE_API}?action=report`;
        const res = await fetch(url);
        const data = await res.json();

        if (!res.ok || !Array.isArray(data)) {
            throw new Error((data && data.error) || 'Unable to load attendance report');
        }

        if (!data.length) {
            tableBody.innerHTML = '<tr><td colspan="7" class="empty-state">No attendance records found.</td></tr>';
            if (summaryEl) summaryEl.textContent = '0 records';
            return;
        }

        tableBody.innerHTML = data.map((row, index) => `
            <tr>
                <td>${index + 1}</td>
                <td>${escapeHtml(row.event_name || '—')}</td>
                <td>${escapeHtml(row.student_name || '—')}</td>
                <td>${escapeHtml(row.username || '—')}</td>
                <td><span class="status-${row.status || 'present'}">${escapeHtml(row.status || 'present')}</span></td>
                <td>${escapeHtml(formatAttendanceTime(row.checked_in_at))}</td>
                <td>${escapeHtml(formatAttendanceTime(row.checked_out_at))}</td>
            </tr>
        `).join('');

        if (summaryEl) summaryEl.textContent = `${data.length} record${data.length === 1 ? '' : 's'}`;
    } catch (error) {
        tableBody.innerHTML = `<tr><td colspan="7" class="empty-state">${escapeHtml(error.message || 'Unable to load the attendance report')}</td></tr>`;
    }
}

function exportAttendanceReportCsv() {
    const table = document.getElementById('attendanceReportTable');
    if (!table) return;

    const rows = Array.from(table.querySelectorAll('tr'));
    const csvLines = rows.map(row => Array.from(row.querySelectorAll('td, th')).map(cell => {
        const value = cell.textContent.trim().replace(/\r?\n/g, ' ');
        return `"${value.replace(/"/g, '""')}"`;
    }).join(','));

    const blob = new Blob([csvLines.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = 'attendance-report.csv';
    document.body.appendChild(link);
    link.click();
    link.remove();
    URL.revokeObjectURL(url);
}

function setupAttendanceReportPage() {
    const eventSelect = document.getElementById('reportEventSelect');
    const exportBtn = document.getElementById('exportAttendanceCsv');
    if (!eventSelect && !exportBtn) return;

    if (exportBtn) {
        exportBtn.addEventListener('click', exportAttendanceReportCsv);
    }

    if (eventSelect) {
        eventSelect.addEventListener('change', () => {
            loadAttendanceReport(eventSelect.value || '');
        });

        fetch(`${ATTENDANCE_API}?action=report`)
            .then(res => res.ok ? res.json() : Promise.reject(new Error('Unable to load report events')))
            .then(data => {
                if (!Array.isArray(data)) return;
                const uniqueEvents = [];
                const seen = new Set();
                data.forEach(item => {
                    if (!seen.has(item.event_id)) {
                        seen.add(item.event_id);
                        uniqueEvents.push({ id: item.event_id, name: item.event_name });
                    }
                });
                eventSelect.innerHTML = '<option value="">All Events</option>' + uniqueEvents.map(item => `<option value="${item.id}">${escapeHtml(item.name)}</option>`).join('');
            })
            .catch(() => {
                eventSelect.innerHTML = '<option value="">All Events</option>';
            })
            .finally(() => {
                loadAttendanceReport(eventSelect.value || '');
            });
    } else {
        loadAttendanceReport('');
    }
}

window.openAttendanceModal = openAttendanceModal;
window.closeAttendanceModal = closeAttendanceModal;
window.exportAttendanceReportCsv = exportAttendanceReportCsv;

document.addEventListener('DOMContentLoaded', () => {
    renderStudentQr();
    setupAttendanceModal();
    populateAttendanceEvents();
    setupAttendanceReportPage();
});
