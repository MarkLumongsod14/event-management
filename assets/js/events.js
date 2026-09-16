const API = 'api/events.php';
const FILES_API = 'api/files.php';
const user = window.CURRENT_USER;

const icon = (name, size = 15) => {
    const paths = {
        calendar: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
        scale: '<path d="M12 3v18M5 6h14M7 6l-3 6a3 3 0 0 0 6 0L7 6ZM17 6l-3 6a3 3 0 0 0 6 0l-3-6ZM8 21h8"/>',
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        staff: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
        edit: '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z"/>',
        check: '<path d="m5 12 4 4L19 6"/>',
        clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        eye: '<path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/>',
        pencil: '<path d="m4 16-1 5 5-1L20 8a2.12 2.12 0 0 0-3-3L4 16Z"/>',
        paperclip: '<path d="m21.4 11.6-8.9 8.9a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7L9.7 17.7a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
        close: '<path d="m6 6 12 12M18 6 6 18"/>',
        warning: '<path d="m12 3 10 18H2L12 3Z"/><path d="M12 9v4M12 17h.01"/>'
        ,award: '<path d="M8 21h8M12 17v4M7 4h10v4a5 5 0 0 1-10 0V4Z"/><path d="M7 6H4v1a4 4 0 0 0 4 4M17 6h3v1a4 4 0 0 1-4 4"/>'
    };
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || ''}</svg>`;
};

let categoriesData = {};
let eventsData = [];
let categoryStaffMap = {};   // { categoryId: [{id, full_name}, ...] }
let judgeList = [];
let editingId = null;
let pendingFiles = [];       // files queued before save
let currentView = 'grid';
let resultsEventId = null;

const can = {
    create:  ['superadmin','admin'].includes(user.role),
    approve: ['superadmin','admin'].includes(user.role),
    edit:    ['superadmin','admin','staff'].includes(user.role),
    delete:  ['superadmin','admin'].includes(user.role),
};

// ============ INIT ============
document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([
        loadCategories(),
        loadCategoryStaffMap(),
        loadJudges(),
    ]);
    await loadEvents();
    bindFilters();
    bindForm();
    bindFileUpload();
});

// ============ DATA LOADERS ============
async function loadCategories() {
    const res = await fetch(API + '?action=categories');
    categoriesData = await res.json();
    const sel = document.getElementById('categorySelect');
    sel.innerHTML = '<option value="">Select a category</option>';
    for (const [group, items] of Object.entries(categoriesData)) {
        const og = document.createElement('optgroup');
        og.label = group;
        items.forEach(c => {
            const o = document.createElement('option');
            o.value = c.id;
            o.textContent = c.name;
            o.dataset.hasCriteria = String(c.has_criteria) === '1' ? '1' : '0';
            o.dataset.group = group;
            og.appendChild(o);
        });
        sel.appendChild(og);
    }
    sel.addEventListener('change', onCategoryChange);
}

async function loadCategoryStaffMap() {
    try {
        const res = await fetch(API + '?action=category_staff_map');
        categoryStaffMap = await res.json();
    } catch {
        categoryStaffMap = {};
    }
}

async function loadEvents() {
    const res = await fetch(API);
    const data = await res.json();
    if (!res.ok || !Array.isArray(data)) {
        eventsData = [];
        window.EVENTS_DATA = [];
        renderEventsGrid([]);
        showToast(data.error || 'Events could not be loaded', true);
        return;
    }
    eventsData = data;
    window.EVENTS_DATA = eventsData;
    applyFilters();
    if (typeof populateAttendanceEvents === 'function') populateAttendanceEvents();
}

// ============ FILTERS ============
function bindFilters() {
    document.getElementById('searchInput').addEventListener('input', applyFilters);
    document.getElementById('groupFilter').addEventListener('change', applyFilters);
    document.getElementById('statusFilter').addEventListener('change', applyFilters);

    document.querySelectorAll('.view-btn').forEach(b => {
        b.onclick = () => {
            document.querySelectorAll('.view-btn').forEach(x => x.classList.remove('active'));
            b.classList.add('active');
            currentView = b.dataset.view;
            applyFilters();
        };
    });
}

function applyFilters() {
    const q      = document.getElementById('searchInput').value.toLowerCase().trim();
    const group  = document.getElementById('groupFilter').value;
    const status = document.getElementById('statusFilter').value;

    const filtered = eventsData.filter(ev => {
        if (group && ev.parent_group !== group) return false;
        if (status && ev.status !== status) return false;
        if (q) {
            const haystack = [
                ev.name, ev.category,
                (ev.participants || []).map(p => [p.team_name, p.name, ...(p.members || [])].filter(Boolean).join(' ')).join(' '),
                (ev.staff || []).map(s => s.full_name).join(' ')
            ].join(' ').toLowerCase();
            if (!haystack.includes(q)) return false;
        }
        return true;
    });

    renderEventsGrid(filtered);
}

// ============ RENDER ============
function renderEventsGrid(list) {
    const container = document.getElementById('eventsGrid');
    container.className = currentView === 'grid' ? 'events-grid' : 'events-grid list-view';

    if (!list.length) {
        container.innerHTML = '<div class="empty-state">No events match your filters.</div>';
        return;
    }

    container.innerHTML = '';
    list.forEach(ev => container.appendChild(buildEventCard(ev)));
}

function buildEventCard(ev) {
    const card = document.createElement('div');
    card.className = 'event-card';

    const staffTags = (ev.staff || []).map(s =>
        `<span class="staff-tag">${escapeHtml(s.full_name)}</span>`
    ).join('');

    // Schedule badge
    let scheduleHtml = '';
    if (ev.start_datetime && ev.end_datetime) {
        const fmt = s => new Date(s.replace(' ', 'T')).toLocaleString(undefined, {
            month: 'short', day: 'numeric',
            hour: 'numeric', minute: '2-digit'
        });
        scheduleHtml = `
            <div class="event-schedule">
                ${icon('calendar')} ${fmt(ev.start_datetime)} - ${fmt(ev.end_datetime)}
            </div>
        `;
    }

    // Criteria summary
    let criteriaHtml = '';
    if (ev.criteria?.length) {
        criteriaHtml = `<div class="event-criteria-summary">${icon('scale')} ${
            ev.criteria.map(c => `${escapeHtml(c.name)} ${c.percentage}%`).join(' · ')
        }</div>`;
    }

    const participantNames = (ev.participants || []).map(p => p.team_name || p.name).filter(Boolean).join(', ');
    const statusClass = `status-${ev.status}`;
    const statusText  = ev.status === 'approved' ? 'Approved'
                      : ev.status === 'pending'  ? 'Pending'
                      : 'Rejected';
    const statusBadge = user.role === 'student'
        ? ''
        : `<span class="${statusClass}">${statusText}</span>`;

    // Actions
    let actions = '';
    if (can.delete)  actions += `<button class="btn btn-danger btn-sm" onclick="deleteEvent(${ev.id})" title="Delete event" aria-label="Delete event">${icon('trash')}</button>`;
    if (can.edit)    actions += `<button class="btn btn-secondary btn-sm" onclick="editEvent(${ev.id})" title="Edit event" aria-label="Edit event">${icon('edit')}</button>`;
    if (can.approve) {
        const isApproved = ev.status === 'approved';
        const approvalLabel = isApproved ? 'Set pending' : 'Approve';
        const approvalHint = isApproved ? 'Move this event back to pending' : 'Approve this event';
        actions += `<button class="btn btn-approval btn-sm" onclick="toggleApprove(${ev.id})" title="${approvalHint}" aria-label="${approvalHint}">${isApproved ? icon('clock') : icon('check')} <span>${approvalLabel}</span></button>`;
    }
    const canScoreDirectly = user.role === 'staff' && !ev.has_criteria;
    const isScoring = user.role === 'judge' || canScoreDirectly;
    const resultActionLabel = isScoring ? 'Score event' : 'View results';
    const resultActionHint = isScoring ? 'Enter scores for this event' : 'View event results';
    actions += `<button class="btn btn-secondary btn-sm" onclick="viewEvent(${ev.id})" title="${resultActionHint}" aria-label="${resultActionHint}">${isScoring ? icon('pencil') : icon('eye')} <span>${resultActionLabel}</span></button>`;

    card.innerHTML = `
        ${ev.cover_image_url ? `<div class="event-card-media"><img src="${escapeHtml(ev.cover_image_url)}" alt="${escapeHtml(ev.name)} event picture" loading="lazy"></div>` : ''}
        <div class="event-card-head">
            <span class="event-category-badge">${escapeHtml(ev.parent_group)}</span>
            ${statusBadge}
        </div>
        <div class="event-card-body">
            <h3 class="event-card-title">${escapeHtml(ev.name)}</h3>
            <div class="event-card-sub">${escapeHtml(ev.category)}</div>
            ${scheduleHtml}
            <div class="event-detail-grid">
                <div class="event-detail">
                    <span class="event-detail-label">Participants <strong>${(ev.participants || []).length}</strong></span>
                    <span class="event-detail-value">${icon('users')} ${escapeHtml(participantNames || 'No participants listed')}</span>
                </div>
                <div class="event-detail">
                    <span class="event-detail-label">Assigned staff</span>
                    <span class="event-detail-value">${icon('staff')} ${staffTags || 'No staff assigned'}</span>
                </div>
            </div>
            ${criteriaHtml}
        </div>
        <div class="event-card-actions">${actions}</div>
    `;
    return card;
}

// ============ CATEGORY / CRITERIA / AUTO-STAFF ============
function onCategoryChange() {
    const sel = document.getElementById('categorySelect');
    const opt = sel.options[sel.selectedIndex];
    const block = document.getElementById('criteriaBlock');
    const hasCriteria = opt?.dataset.hasCriteria === '1';
    const judgeGroup = document.getElementById('judgeAssignmentGroup');
    if (judgeGroup) judgeGroup.classList.toggle('hidden', !hasCriteria || !can.approve);
    if (!hasCriteria) {
        const judgeSelect = document.getElementById('judgeSelect');
        if (judgeSelect) Array.from(judgeSelect.options).forEach(option => { option.selected = false; });
    }

    if (hasCriteria) {
        block.classList.remove('hidden');
        if (!document.querySelector('#criteriaContainer .criteria-row')) {
            renderCriteriaRows([
                { name: 'Music',           percentage: 20 },
                { name: 'Execution',       percentage: 30 },
                { name: 'Costume',         percentage: 30 },
                { name: 'Audience Impact', percentage: 20 },
            ]);
        }
    } else {
        block.classList.add('hidden');
        document.getElementById('criteriaContainer').innerHTML = '';
    }

    renderAutoStaffPreview();   // 👈 auto-staff refresh
}

function renderAutoStaffPreview() {
    const box = document.getElementById('autoStaffPreview');
    if (!box) return;  // safety

    const catSel = document.getElementById('categorySelect');
    const catId = parseInt(catSel.value);

    if (!catId) {
        box.innerHTML = '<div class="small-note">Pick a category to see assigned staff.</div>';
        return;
    }

    const staff = categoryStaffMap[catId] || [];
    if (!staff.length) {
        box.innerHTML = `<div class="small-note">${icon('warning')} No staff assigned to this game yet. Assign staff in the <a href="staff.php" style="color:var(--primary-dark);font-weight:600;">Staff page</a>.</div>`;
        return;
    }

    box.innerHTML = staff.map(s =>
        `<span class="staff-tag">${escapeHtml(s.full_name)}</span>`
    ).join('');
}

function renderCriteriaRows(rows = []) {
    const c = document.getElementById('criteriaContainer');
    c.innerHTML = '';
    rows.forEach(r => addCriteriaRow(r.name, r.percentage));
}

function addCriteriaRow(name = '', pct = '') {
    const c = document.getElementById('criteriaContainer');
    const row = document.createElement('div');
    row.className = 'criteria-row';
    row.innerHTML = `
        <input type="text" class="criterion-name" placeholder="Criterion name" value="${name}" required>
        <input type="number" class="criterion-percent" placeholder="%" min="0" max="100" value="${pct}" required>
        <button type="button" class="btn btn-danger btn-sm remove-criteria" title="Remove criterion" aria-label="Remove criterion">${icon('close')}</button>
    `;
    row.querySelector('.remove-criteria').onclick = () => row.remove();
    c.appendChild(row);
}

// ============ FILE UPLOAD ============
function bindFileUpload() {
    const drop  = document.getElementById('fileDrop');
    const input = document.getElementById('fileInput');
    if (!drop || !input) return;

    input.addEventListener('change', e => queueFiles(e.target.files));

    ['dragenter','dragover'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.add('dragover');
    }));
    ['dragleave','drop'].forEach(ev => drop.addEventListener(ev, e => {
        e.preventDefault(); drop.classList.remove('dragover');
    }));
    drop.addEventListener('drop', e => queueFiles(e.dataTransfer.files));
}

function queueFiles(fileList) {
    Array.from(fileList).forEach(f => pendingFiles.push(f));
    renderFilePreview();
}

function renderFilePreview() {
    const p = document.getElementById('filePreview');
    if (!p) return;
    p.innerHTML = '';
    pendingFiles.forEach((f, i) => {
        const chip = document.createElement('div');
        chip.className = 'file-chip';
        chip.innerHTML = `
            ${icon('paperclip')}
            <span class="name">${escapeHtml(f.name)}</span>
            <span class="size">${(f.size/1024).toFixed(0)} KB</span>
            <button type="button" class="remove" data-i="${i}" aria-label="Remove file">${icon('close')}</button>
        `;
        chip.querySelector('.remove').onclick = () => {
            pendingFiles.splice(i, 1);
            renderFilePreview();
        };
        p.appendChild(chip);
    });
}

// ============ FORM ============
function bindForm() {
    const addBtn = document.getElementById('addCriteriaBtn');
    if (addBtn) addBtn.onclick = () => addCriteriaRow('', '');

    const form = document.getElementById('eventForm');
    if (form) form.addEventListener('submit', submitEvent);
    const participantBtn = document.getElementById('addParticipantBtn');
    if (participantBtn) participantBtn.onclick = () => addParticipantRow();
    const saveResultsBtn = document.getElementById('saveResultsBtn');
    if (saveResultsBtn) saveResultsBtn.onclick = saveResults;
    const judgeGroup = document.getElementById('judgeAssignmentGroup');
    if (judgeGroup) judgeGroup.classList.toggle('hidden', !can.approve);
}

function resetForm() {
    const form = document.getElementById('eventForm');
    if (!form) return;

    form.reset();
    document.getElementById('eventId').value = '';
    const sd = document.getElementById('startDatetime');
    const ed = document.getElementById('endDatetime');
    if (sd) sd.value = '';
    if (ed) ed.value = '';

    const cc = document.getElementById('criteriaContainer');
    if (cc) cc.innerHTML = '';
    const cb = document.getElementById('criteriaBlock');
    if (cb) cb.classList.add('hidden');
    const pc = document.getElementById('participantsContainer');
    if (pc) pc.innerHTML = '';

    const sb = document.getElementById('submitBtn');
    if (sb) sb.textContent = 'Create Event';
    const mt = document.getElementById('modalTitle');
    if (mt) mt.textContent = 'Create New Event';

    pendingFiles = [];
    renderFilePreview();
    renderAutoStaffPreview();
    editingId = null;
}

function addParticipantRow(participant = {}) {
    const container = document.getElementById('participantsContainer');
    const row = document.createElement('div');
    row.className = 'participant-row';
    const isIndividual = participant.participant_type === 'individual';
    const teamName = participant.team_name || (!isIndividual ? participant.name || '' : '');
    const individualName = isIndividual ? participant.name || '' : '';
    const members = Array.isArray(participant.members) ? participant.members.join(', ') : '';
    row.innerHTML = `
        <input type="text" class="participant-team-name" placeholder="Team name" value="${escapeHtml(teamName)}" ${isIndividual ? 'hidden' : ''}>
        <input type="text" class="participant-name" placeholder="Participant name" value="${escapeHtml(individualName)}" ${isIndividual ? '' : 'hidden'}>
        <select class="participant-type">
            <option value="team" ${!isIndividual ? 'selected' : ''}>Team</option>
            <option value="individual" ${isIndividual ? 'selected' : ''}>Individual</option>
        </select>
        <input type="text" class="participant-members" placeholder="Member names, separated by commas" value="${escapeHtml(members)}" ${isIndividual ? 'hidden' : ''}>
        <button type="button" class="btn btn-danger btn-sm remove-participant" title="Remove participant" aria-label="Remove participant">${icon('close')}</button>
    `;
    row.querySelector('.participant-type').onchange = event => {
        const team = event.target.value === 'team';
        row.querySelector('.participant-team-name').hidden = !team;
        row.querySelector('.participant-name').hidden = team;
        row.querySelector('.participant-members').hidden = !team;
    };
    row.querySelector('.remove-participant').onclick = () => row.remove();
    container.appendChild(row);
}

function collectParticipants() {
    return Array.from(document.querySelectorAll('#participantsContainer .participant-row')).map(row => ({
        name: row.querySelector('.participant-name').value.trim(),
        team_name: row.querySelector('.participant-team-name').value.trim(),
        participant_type: row.querySelector('.participant-type').value,
        members: row.querySelector('.participant-members').value.split(',').map(name => name.trim()).filter(Boolean),
    })).filter(participant => participant.participant_type === 'team' ? participant.team_name : participant.name);
}

async function saveParticipants(eventId) {
    const res = await fetch(`${API}?action=participants`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ event_id: eventId, participants: collectParticipants() })
    });
    const data = await res.json();
    if (!data.success) throw new Error(data.error || 'Participants could not be saved');
}

async function submitEvent(e) {
    e.preventDefault();

    const catSel = document.getElementById('categorySelect');
    const catOpt = catSel.options[catSel.selectedIndex];
    const categoryId = parseInt(catSel.value);
    if (!categoryId) return alert('Please select a category.');

    // Schedule validation
    const startDt = document.getElementById('startDatetime').value;
    const endDt   = document.getElementById('endDatetime').value;
    if (!startDt || !endDt) {
        return alert('Please set start and end date/time.');
    }
    if (new Date(endDt) < new Date(startDt)) {
        return alert('End time cannot be before start time.');
    }

    // Criteria
    const hasCriteria = catOpt.dataset.hasCriteria === '1';
    let criteria = [];
    if (hasCriteria) {
        document.querySelectorAll('#criteriaContainer .criteria-row').forEach(row => {
            const name = row.querySelector('.criterion-name').value.trim();
            const pct = parseFloat(row.querySelector('.criterion-percent').value);
            if (name && !isNaN(pct)) criteria.push({ name, percentage: pct });
        });
        const total = criteria.reduce((s,c) => s + c.percentage, 0);
        if (Math.abs(total - 100) > 0.5) {
            return alert(`Criteria must total 100% (currently ${total}%)`);
        }
    }

    const payload = {
        id: editingId,
        name: document.getElementById('eventName').value.trim(),
        category_id: categoryId,
        start_datetime: startDt.replace('T', ' ') + ':00',
        end_datetime:   endDt.replace('T', ' ')   + ':00',
        criteria: criteria,
        has_criteria: hasCriteria,
        judge_ids: Array.from(document.getElementById('judgeSelect').selectedOptions)
            .map(option => Number(option.value)),
    };

    const action = editingId ? 'update' : 'create';
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!data.success) {
        showToast(data.error || 'Failed', true);
        return;
    }

    const eventId = editingId || data.id;

    try {
        await saveParticipants(eventId);
    } catch (error) {
        showToast(error.message, true);
    }

    // Upload queued files
    if (pendingFiles.length) {
        const fd = new FormData();
        fd.append('event_id', eventId);
        pendingFiles.forEach(f => fd.append('files[]', f));

        const upRes = await fetch(FILES_API, { method: 'POST', body: fd });
        const upData = await upRes.json();
        if (upData.errors?.length) {
            showToast(`Event saved. ${upData.errors.length} file(s) failed.`, true);
        } else if (upData.uploaded?.length) {
            showToast(`Event saved with ${upData.uploaded.length} file(s).`);
        }
    } else {
        showToast(editingId ? 'Event updated!' : 'Event created!');
    }

    closeModal();
    resetForm();
    loadEvents();
}

// ============ MODAL ============
function openCreateModal() {
    resetForm();
    document.getElementById('eventModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('eventModal').classList.add('hidden');
}

// ============ ACTIONS ============
window.deleteEvent = async (id) => {
    if (!confirm('Delete this event and all its files?')) return;
    const res = await fetch(`${API}?action=delete`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id})
    });
    const d = await res.json();
    if (d.success) { showToast('Event deleted'); loadEvents(); }
    else showToast(d.error || 'Failed', true);
};

window.toggleApprove = async (id) => {
    const ev = eventsData.find(e => e.id == id);
    const newStatus = ev.status === 'approved' ? 'pending' : 'approved';
    const res = await fetch(`${API}?action=approve`, {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({id, status:newStatus})
    });
    const d = await res.json();
    if (d.success) { showToast('Status updated'); loadEvents(); }
    else showToast(d.error || 'Failed', true);
};

window.editEvent = async (id) => {
    const ev = eventsData.find(e => e.id == id);
    if (!ev) return;

    editingId = id;
    document.getElementById('eventId').value = id;
    document.getElementById('eventName').value = ev.name;
    document.getElementById('modalTitle').textContent = 'Edit Event';
    document.getElementById('submitBtn').textContent = 'Update Event';

    // Populate schedule
    if (ev.start_datetime) {
        document.getElementById('startDatetime').value =
            ev.start_datetime.replace(' ', 'T').slice(0, 16);
    }
    if (ev.end_datetime) {
        document.getElementById('endDatetime').value =
            ev.end_datetime.replace(' ', 'T').slice(0, 16);
    }

    // Category
    const sel = document.getElementById('categorySelect');
    Array.from(sel.options).forEach(o => {
        if (o.textContent === ev.category) sel.value = o.value;
    });
    onCategoryChange();          // also refreshes auto-staff preview

    (ev.participants || []).forEach(participant => addParticipantRow(participant));
    renderJudgeOptions((ev.judges || []).map(j => Number(j.id)));

    // Criteria
    if (ev.has_criteria && ev.criteria?.length) {
        renderCriteriaRows(ev.criteria.map(c => ({name:c.name, percentage:c.percentage})));
    }

    // Existing files
    pendingFiles = [];
    renderFilePreview();
    try {
        const res = await fetch(`${FILES_API}?action=list&event_id=${id}`);
        const files = await res.json();
        if (files.length) {
            const p = document.getElementById('filePreview');
            files.forEach(f => {
                const chip = document.createElement('div');
                chip.className = 'file-chip existing';
                chip.innerHTML = `
                    ${icon('paperclip')}
                    <a href="${f.url}" target="_blank" class="name">${escapeHtml(f.original_name)}</a>
                    <span class="size">${f.size_human}</span>
                    <button type="button" class="remove" data-id="${f.id}" aria-label="Remove file">${icon('close')}</button>
                `;
                chip.querySelector('.remove').onclick = async () => {
                    if (!confirm(`Delete "${f.original_name}"?`)) return;
                    await fetch(FILES_API, {
                        method: 'DELETE',
                        headers: {'Content-Type':'application/json'},
                        body: JSON.stringify({ id: f.id })
                    });
                    chip.remove();
                };
                p.appendChild(chip);
            });
        }
    } catch {}

    document.getElementById('eventModal').classList.remove('hidden');
};

window.viewEvent = (id) => {
    const ev = eventsData.find(e => e.id == id);
    if (!ev) return;
    resultsEventId = id;
    document.getElementById('resultsModalTitle').textContent = ev.name;
    const saveResultsButton = document.getElementById('saveResultsBtn');
    const canEditScores = ev.can_score && ((ev.has_criteria && ['judge'].includes(user.role)) || (!ev.has_criteria && user.role === 'staff'));

    if (saveResultsButton) {
        const shouldShowSaveButton = user.role !== 'student';
        saveResultsButton.style.display = shouldShowSaveButton ? 'inline-flex' : 'none';
        saveResultsButton.disabled = !canEditScores;
        saveResultsButton.title = !canEditScores
            ? 'Scores are read-only here'
            : (ev.can_score ? 'Save scores and rankings' : 'You are not assigned to score this event');
    }

    document.getElementById('resultsEventSummary').innerHTML = `
        <strong>${escapeHtml(ev.category)}</strong> · ${escapeHtml(ev.status)}
        ${ev.start_datetime ? `<br>${escapeHtml(ev.start_datetime)}${ev.end_datetime ? ` → ${escapeHtml(ev.end_datetime)}` : ''}` : ''}
    `;
    renderResults(ev.participants || [], ev.criteria || [], ev.has_criteria, canEditScores);
    document.getElementById('resultsMessage').textContent = !ev.has_criteria && canEditScores
        ? 'Enter a score for each participant. Rankings are calculated automatically.'
        : user.role !== 'judge'
        ? 'Scores are shown read-only. Rankings are calculated automatically.'
        : !ev.can_score
        ? 'You can view this event, but only an assigned judge or event manager can save results.'
        : (ev.participants?.length ? '' : 'Add participants by editing this event first.');
    document.getElementById('resultsModal').classList.remove('hidden');
};

window.closeResultsModal = () => document.getElementById('resultsModal').classList.add('hidden');

function renderResults(participants, criteria = [], hasCriteria = true, canEditScores = false) {
    const container = document.getElementById('resultsContainer');
    if (!participants.length) {
        container.innerHTML = '<div class="empty-state">No participants have been added.</div>';
        return;
    }
    container.innerHTML = participants.map(p => {
        const displayName = p.team_name || p.name;
        const members = (p.members || []).join(', ');
        const savedScores = Object.fromEntries((p.criterion_scores || []).map(score => [score.criterion_id, score.score]));
        const rank = Number(p.rank_position);
        const rankBadge = rank > 0 ? `<span class="rank-badge rank-${rank <= 3 ? rank : 'other'}">${ordinalRank(rank)}</span>` : '<span class="rank-badge rank-unset">—</span>';
        const directScore = !hasCriteria && canEditScores
            ? `<label class="direct-score-control"><span>Score</span><input type="number" class="direct-score" min="0" step="0.01" placeholder="Enter score" value="${p.score ?? ''}"></label>`
            : '';
        const totalScore = (!canEditScores || !hasCriteria) && p.score != null && !directScore
            ? `<div class="result-total-score"><small>Total score</small><strong>${Number(p.score).toFixed(2)}</strong></div>`
            : '';
        const criterionInputs = criteria.length ? `
            <div class="criterion-score-grid">
                ${criteria.map(c => `
                    <label>
                        <span>${escapeHtml(c.name)} <small>(${c.percentage}%)</small></span>
                        <input type="number" class="criterion-score" data-criterion-id="${c.id}" min="0" max="100" step="0.01" placeholder="0-100" value="${savedScores[c.id] ?? ''}" ${canEditScores ? '' : 'readonly'}>
                    </label>
                `).join('')}
            </div>
        ` : '';
        return `
            <div class="result-row" data-participant-id="${p.id}">
                <div class="result-participant-name"><div class="participant-title">${rankBadge}<strong>${escapeHtml(displayName)}</strong></div>${members ? `<small>Members: ${escapeHtml(members)}</small>` : ''}</div>
                ${criterionInputs}
                ${directScore}
                ${totalScore}
            </div>
        `;
    }).join('');
}

async function saveResults() {
    if (!resultsEventId) return;
    if (document.getElementById('saveResultsBtn').disabled) return;
    const event = eventsData.find(item => item.id == resultsEventId);
    const criterionScores = Array.from(document.querySelectorAll('#resultsContainer .result-row')).flatMap(row =>
        Array.from(row.querySelectorAll('.criterion-score')).map(input => ({
            participant_id: Number(row.dataset.participantId),
            criterion_id: Number(input.dataset.criterionId),
            score: input.value,
        }))
    );
    const results = Array.from(document.querySelectorAll('#resultsContainer .result-row')).map(row => ({
        participant_id: Number(row.dataset.participantId),
        score: row.querySelector('.direct-score')?.value ?? ''
    }));
    const res = await fetch(`${API}?action=results`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(event?.has_criteria
            ? {event_id: resultsEventId, criterion_scores: criterionScores}
            : {event_id: resultsEventId, results})
    });
    const data = await res.json();
    if (!data.success) {
        showToast(data.error || 'Results could not be saved', true);
        return;
    }
    showToast('Results saved');
    closeResultsModal();
    loadEvents();
}

// ============ UTILITIES ============
function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
}

function showToast(msg, isError = false) {
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.textContent = msg;
    document.body.appendChild(t);
    requestAnimationFrame(() => t.classList.add('show'));
    setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 300);
    }, 2200);
}

async function loadJudges() {
    try {
        const res = await fetch(API + '?action=judge_list');
        judgeList = await res.json();
    } catch {
        judgeList = [];
    }
    renderJudgeOptions([]);
}

function renderJudgeOptions(selectedIds = []) {
    const select = document.getElementById('judgeSelect');
    if (!select) return;
    select.innerHTML = judgeList.length
        ? judgeList.map(j => `<option value="${j.id}" ${selectedIds.includes(Number(j.id)) ? 'selected' : ''}>${escapeHtml(j.full_name)} (@${escapeHtml(j.username)})</option>`).join('')
        : '<option disabled>No judge accounts available</option>';
}

function ordinalRank(rank) {
    if (rank % 100 >= 11 && rank % 100 <= 13) return `${rank}th`;
    return `${rank}${({1:'st', 2:'nd', 3:'rd'})[rank % 10] || 'th'}`;
}