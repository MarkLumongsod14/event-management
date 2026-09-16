const API = 'api/events.php';
const user = window.CURRENT_USER;
let categoriesData = {};
let staffData = [];
let eventsData = [];
let editingId = null;

// ---------- PERMISSIONS ----------
const can = {
    create: ['superadmin','admin'].includes(user.role),
    approve: ['superadmin','admin'].includes(user.role),
    edit:    ['superadmin','admin','staff'].includes(user.role),
    delete:  ['superadmin','admin'].includes(user.role),
};

// ---------- INIT ----------
document.addEventListener('DOMContentLoaded', async () => {
    await loadCategories();
    await loadStaff();
    await loadEvents();
    bindForm();
});

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

            // 👇 CRITICAL: convert to string "0" or "1" explicitly
            o.dataset.hasCriteria = String(c.has_criteria) === '1' ? '1' : '0';

            og.appendChild(o);
        });

        sel.appendChild(og);
    }

    // Attach the listener
    sel.addEventListener('change', onCategoryChange);

    // Debug: log the flag on every option
    console.log('Options loaded. Flags:',
        Array.from(sel.querySelectorAll('option')).map(o => ({
            name: o.textContent,
            flag: o.dataset.hasCriteria
        }))
    );
}

async function loadStaff() {
    const res = await fetch(API + '?action=staff_list');
    staffData = await res.json();
    const sel = document.getElementById('staffAssigned');
    if (!sel) return;
    sel.innerHTML = '';
    staffData.forEach(s => {
        const o = document.createElement('option');
        o.value = s.id;
        o.textContent = s.full_name;
        sel.appendChild(o);
    });
}

async function loadEvents() {
    const res = await fetch(API);
    eventsData = await res.json();
    renderEvents();
}

// ---------- CATEGORY CHANGE ----------
function onCategoryChange() {
    const sel = document.getElementById('categorySelect');
    const opt = sel.options[sel.selectedIndex];
    const block = document.getElementById('criteriaBlock');

    // 👇 Safe extraction of the flag
    const rawFlag = opt ? opt.dataset.hasCriteria : '0';
    const hasCriteria = rawFlag === '1';

    console.log('Category changed:', {
        name: opt?.textContent,
        rawFlag: rawFlag,
        hasCriteria: hasCriteria
    });

    if (hasCriteria) {
        block.classList.remove('hidden');
        // Only add default rows if empty
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
}

function renderCriteriaRows(rows = []) {
    const c = document.getElementById('criteriaContainer');
    c.innerHTML = '';
    rows.forEach(r => addCriteriaRow(r.name, r.percentage));
}

function addCriteriaRow(name='', pct='') {
    const c = document.getElementById('criteriaContainer');
    const row = document.createElement('div');
    row.className = 'criteria-row';
    row.innerHTML = `
        <input type="text" class="criterion-name" placeholder="Criterion name" value="${name}" required>
        <input type="number" class="criterion-percent" placeholder="%" min="0" max="100" value="${pct}" required>
        <button type="button" class="btn btn-danger btn-sm remove-criteria">✕</button>
    `;
    row.querySelector('.remove-criteria').onclick = () => row.remove();
    c.appendChild(row);
}

// ---------- FORM ----------
function bindForm() {
    const form = document.getElementById('eventForm');
    if (!form) return;
    document.getElementById('addCriteriaBtn').onclick = () => addCriteriaRow('', '');
    document.getElementById('resetFormBtn').onclick = resetForm;
    form.addEventListener('submit', submitEvent);
}

function resetForm() {
    document.getElementById('eventForm').reset();
    document.getElementById('eventId').value = '';
    document.getElementById('criteriaContainer').innerHTML = '';
    document.getElementById('criteriaBlock').classList.add('hidden');
    document.getElementById('submitBtn').textContent = 'Create Event';
    editingId = null;
    Array.from(document.getElementById('staffAssigned').options).forEach(o => o.selected = false);
}

async function submitEvent(e) {
    e.preventDefault();
    const catSel = document.getElementById('categorySelect');
    const catOpt = catSel.options[catSel.selectedIndex];
    const categoryId = parseInt(catSel.value);
    if (!categoryId) return alert('Please select a category.');

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

    const staffIds = Array.from(document.getElementById('staffAssigned').selectedOptions)
                        .map(o => parseInt(o.value));

    const payload = {
        id: editingId,
        name: document.getElementById('eventName').value.trim(),
        category_id: categoryId,
        players_representative: document.getElementById('playersRepresentative').value.trim(),
        staff_ids: staffIds,
        criteria: criteria,
        has_criteria: hasCriteria,
    };

    const action = editingId ? 'update' : 'create';
    const res = await fetch(`${API}?action=${action}`, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
        showToast(editingId ? 'Event updated!' : 'Event created!');
        resetForm();
        loadEvents();
    } else {
        showToast(data.error || 'Failed', true);
    }
}

// ---------- RENDER EVENT LIST ----------
// ---------- RENDER EVENT LIST ----------
function renderEvents() {
    const container = document.getElementById('eventListContainer');
    const countEl   = document.getElementById('eventCount');
    const totalEl   = document.getElementById('totalCount');

    // Update counters
    if (countEl) countEl.textContent = eventsData.length;
    if (totalEl) totalEl.textContent = eventsData.length;

    // Empty state
    if (!eventsData.length) {
        container.innerHTML = '<div class="empty-state">No events created yet.</div>';
        return;
    }

    container.innerHTML = '';

    eventsData.forEach(ev => {
        // ---- Staff tags ----
        const staffTags = (ev.staff || []).map(s =>
            `<span class="staff-tag">${escapeHtml(s.full_name)}</span>`
        ).join('');

        // ---- Criteria summary ----
        let criteriaHtml = '';
        if (ev.criteria && ev.criteria.length) {
            criteriaHtml = `<div class="event-criteria-summary">⚖️ ${
                ev.criteria.map(c => `${escapeHtml(c.name)} ${c.percentage}%`).join(' · ')
            }</div>`;
        }

        // ---- Status ----
        const statusClass = `status-${ev.status}`;
        const statusText  = ev.status === 'approved' ? '✓ approved'
                          : ev.status === 'pending'  ? '⏳ pending'
                          : '✕ rejected';

        // ---- Action buttons (role-aware) ----
        let actions = '';
        if (can.delete)  actions += `<button class="btn btn-danger btn-sm" onclick="deleteEvent(${ev.id})">🗑️ Delete</button>`;
        if (can.edit)    actions += `<button class="btn btn-secondary btn-sm" onclick="editEvent(${ev.id})">✏️ Edit</button>`;
        if (can.approve) actions += `<button class="btn btn-secondary btn-sm" onclick="toggleApprove(${ev.id})">${ev.status === 'approved' ? '⏳ Unapprove' : '✅ Approve'}</button>`;
        actions += `<button class="btn btn-secondary btn-sm" onclick="viewEvent(${ev.id})">👁️ View</button>`;

        // ---- Build card ----
        const div = document.createElement('div');
        div.className = 'event-item';
        div.innerHTML = `
            <div class="event-title">
                ${escapeHtml(ev.name)}
                <span class="event-category-badge">${escapeHtml(ev.category)}</span>
                <span class="${statusClass}">${statusText}</span>
            </div>
            <div class="event-meta">
                <span>👥 ${escapeHtml(ev.players_representative || 'No players listed')}</span>
                <span>🧑‍🏫 ${staffTags || 'No staff assigned'}</span>
            </div>
            ${criteriaHtml}
            <div class="event-actions">
                ${actions}
            </div>
        `;
        container.appendChild(div);
    });
}
// Add this once, anywhere in app.js
function filterEvents(query) {
    const q = (query || '').toLowerCase().trim();
    if (!q) { renderEvents(); return; }

    const filtered = eventsData.filter(ev =>
        ev.name.toLowerCase().includes(q) ||
        ev.category.toLowerCase().includes(q) ||
        (ev.players_representative || '').toLowerCase().includes(q)
    );

    // Temporarily render filtered
    const original = eventsData;
    eventsData = filtered;
    renderEvents();
    eventsData = original;
}

// ---------- ACTIONS ----------
window.deleteEvent = async (id) => {
    if (!confirm('Delete this event?')) return;
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

window.editEvent = (id) => {
    const ev = eventsData.find(e => e.id == id);
    if (!ev) return;
    editingId = id;
    document.getElementById('eventId').value = id;
    document.getElementById('eventName').value = ev.name;
    document.getElementById('playersRepresentative').value = ev.players_representative || '';

    // category
    const sel = document.getElementById('categorySelect');
    Array.from(sel.options).forEach(o => {
        if (o.textContent === ev.category) { sel.value = o.value; }
    });
    onCategoryChange();

    // staff
    const staffSel = document.getElementById('staffAssigned');
    Array.from(staffSel.options).forEach(o => {
        o.selected = (ev.staff || []).some(s => s.id == o.value);
    });

    // criteria
    if (ev.has_criteria && ev.criteria?.length) {
        renderCriteriaRows(ev.criteria.map(c => ({name:c.name, percentage:c.percentage})));
    }

    document.getElementById('submitBtn').textContent = 'Update Event';
    document.getElementById('createEventCard').scrollIntoView({behavior:'smooth'});
};

window.viewEvent = (id) => {
    const ev = eventsData.find(e => e.id == id);
    if (!ev) return;
    const staff = (ev.staff||[]).map(s => s.full_name).join(', ') || '—';
    let msg = `Event: ${ev.name}\nCategory: ${ev.category}\nStatus: ${ev.status}\n` +
              `Players: ${ev.players_representative || '—'}\nStaff: ${staff}`;
    if (ev.criteria?.length) {
        msg += '\n\nCriteria:\n' + ev.criteria.map(c => `• ${c.name}: ${c.percentage}%`).join('\n');
    }
    alert(msg);
};

// ---------- UTILITIES ----------
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