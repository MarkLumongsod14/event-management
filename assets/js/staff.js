const USERS_API  = 'api/users.php';
const EVENTS_API = 'api/events.php';
const user = window.CURRENT_USER;

const uiIcon = (name, size = 15) => {
    const paths = {
        edit: '<path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4 11.5-11.5Z"/>',
        games: '<rect x="3" y="6" width="18" height="12" rx="2"/><path d="M8 10v4M6 12h4M16 11h.01M19 13h.01"/>',
        trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 15H6L5 6M10 11v6M14 11v6"/>',
        mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/> '
    };
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || ''}</svg>`;
};

let staffList    = [];
let categories   = {};   // grouped by parent_group
let editingId    = null;

const canManage = ['superadmin','admin'].includes(user.role);

// ============ INIT ============
document.addEventListener('DOMContentLoaded', async () => {
    await Promise.all([loadStaff(), loadCategories()]);
    bindFilters();
    bindForm();
});

// ============ LOADERS ============
async function loadStaff() {
    const res = await fetch(`${USERS_API}?action=list`);
    staffList = await res.json();
    applyFilters();
}

async function loadCategories() {
    const res = await fetch(`${EVENTS_API}?action=categories`);
    categories = await res.json();
}

// ============ FILTERS ============
function bindFilters() {
    document.getElementById('staffSearch').addEventListener('input', applyFilters);
    document.getElementById('roleFilter').addEventListener('change', applyFilters);
}

function applyFilters() {
    const q    = document.getElementById('staffSearch').value.toLowerCase().trim();
    const role = document.getElementById('roleFilter').value;

    const filtered = staffList.filter(s => {
        if (role && s.role !== role) return false;
        if (q) {
            const hay = `${s.full_name} ${s.username} ${s.email || ''}`.toLowerCase();
            if (!hay.includes(q)) return false;
        }
        return true;
    });

    renderStaffGrid(filtered);
}

// ============ RENDER ============
function renderStaffGrid(list) {
    const grid = document.getElementById('staffGrid');

    if (!list.length) {
        grid.innerHTML = '<div class="empty-state">No staff found.</div>';
        return;
    }

    grid.innerHTML = '';
    list.forEach(s => grid.appendChild(buildStaffCard(s)));
}

function buildStaffCard(s) {
    const card = document.createElement('div');
    card.className = 'staff-card';

    const initials = s.full_name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
    const roleClass = `role-${s.role}`;
    const gameCount = s.game_count ?? 0;

    let actions = '';
    if (canManage) {
        actions = `
            <button class="btn btn-secondary btn-sm" onclick="openStaffModal(${s.id})">${uiIcon('edit')} Edit</button>
            <button class="btn btn-secondary btn-sm" onclick="viewStaffGames(${s.id})">${uiIcon('games')} Games</button>
            <button class="btn btn-danger btn-sm" onclick="deleteStaff(${s.id})" aria-label="Delete account">${uiIcon('trash')}</button>
        `;
    } else {
        actions = `<button class="btn btn-secondary btn-sm" onclick="viewStaffGames(${s.id})">${uiIcon('games')} Games</button>`;
    }

    card.innerHTML = `
        <div class="staff-card-head">
            <div class="staff-avatar">${initials}</div>
            <div class="staff-info">
                <div class="staff-name">${escapeHtml(s.full_name)}</div>
                <div class="staff-username">@${escapeHtml(s.username)}</div>
            </div>
            <span class="role-badge ${roleClass}">${s.role}</span>
        </div>
        <div class="staff-card-body">
            <div class="staff-meta">
                ${s.email ? `<span>${uiIcon('mail')} ${escapeHtml(s.email)}</span>` : ''}
                <span>${uiIcon('games')} ${gameCount} game${gameCount !== 1 ? 's' : ''}</span>
            </div>
        </div>
        <div class="staff-card-actions">${actions}</div>
    `;
    return card;
}

// ============ MODAL ============
function openStaffModal(id = null) {
    if (!canManage) return;
    editingId = id;
    const modal = document.getElementById('staffModal');
    const form  = document.getElementById('staffForm');
    form.reset();

    if (id) {
        const s = staffList.find(x => x.id == id);
        if (!s) return;

        document.getElementById('staffModalTitle').textContent = 'Edit Staff';
        document.getElementById('staffId').value       = s.id;
        document.getElementById('staffFullName').value = s.full_name;
        document.getElementById('staffUsername').value = s.username;
        document.getElementById('staffEmail').value    = s.email || '';
        document.getElementById('staffRole').value     = s.role;
        document.getElementById('staffPassword').value = '';

        document.getElementById('pwdLabel').textContent = 'New Password (optional)';
        document.getElementById('pwdNote').textContent  = 'Leave blank to keep current password.';
        document.getElementById('staffSubmitBtn').textContent = 'Update Staff';

        loadStaffGamesForModal(id);
    } else {
        document.getElementById('staffModalTitle').textContent = 'Add Staff';
        document.getElementById('staffId').value = '';
        document.getElementById('pwdLabel').textContent = 'Password';
        document.getElementById('pwdNote').textContent  = 'Required for new staff (min 6 chars).';
        document.getElementById('staffSubmitBtn').textContent = 'Create Staff';

        renderGameCheckboxes([]);
    }

    modal.classList.remove('hidden');
}

function closeStaffModal() {
    document.getElementById('staffModal').classList.add('hidden');
}

async function loadStaffGamesForModal(staffId) {
    try {
        const res = await fetch(`${USERS_API}?action=single&id=${staffId}`);
        const s = await res.json();
        renderGameCheckboxes(s.game_ids || []);
    } catch {
        renderGameCheckboxes([]);
    }
}

function renderGameCheckboxes(assignedIds) {
    const container = document.getElementById('gameAssignList');

    if (!categories || !Object.keys(categories).length) {
        container.innerHTML = '<div class="small-note">No categories available.</div>';
        return;
    }

    let html = '';
    for (const [group, items] of Object.entries(categories)) {
        html += `<div class="game-group">
            <div class="game-group-label">${escapeHtml(group)}</div>
            <div class="game-group-items">`;

        items.forEach(c => {
            const checked = assignedIds.includes(c.id) ? 'checked' : '';
            html += `
                <label class="game-check">
                    <input type="checkbox" value="${c.id}" ${checked}>
                    <span>${escapeHtml(c.name)}</span>
                </label>
            `;
        });

        html += `</div></div>`;
    }

    container.innerHTML = html;
}

// ============ FORM ============
function bindForm() {
    document.getElementById('staffForm').addEventListener('submit', submitStaff);
}

async function submitStaff(e) {
    e.preventDefault();

    const id       = document.getElementById('staffId').value;
    const fullName = document.getElementById('staffFullName').value.trim();
    const username = document.getElementById('staffUsername').value.trim();
    const email    = document.getElementById('staffEmail').value.trim();
    const role     = document.getElementById('staffRole').value;
    const password = document.getElementById('staffPassword').value;

    const gameIds = Array.from(
        document.querySelectorAll('#gameAssignList input[type="checkbox"]:checked')
    ).map(cb => parseInt(cb.value));

    if (!fullName || !username) {
        showToast('Full name and username are required', true);
        return;
    }
    if (!id && password.length < 6) {
        showToast('Password must be at least 6 characters', true);
        return;
    }
    if (id && password && password.length < 6) {
        showToast('New password must be at least 6 characters', true);
        return;
    }

    const action = id ? 'update' : 'create';
    const payload = {
        id: id ? parseInt(id) : undefined,
        full_name: fullName,
        username, email, role,
        password,
        game_ids: gameIds,
    };

    const res = await fetch(`${USERS_API}?action=${action}`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(payload)
    });
    const data = await res.json();

    if (!data.success) {
        showToast(data.error || 'Failed', true);
        return;
    }

    showToast(id ? 'Staff updated!' : 'Staff created!');
    closeStaffModal();
    loadStaff();
}

// ============ ACTIONS ============
window.deleteStaff = async (id) => {
    const s = staffList.find(x => x.id == id);
    if (!s) return;
    if (!confirm(`Delete staff "${s.full_name}"? This cannot be undone.`)) return;

    const res = await fetch(`${USERS_API}?action=delete`, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id })
    });
    const d = await res.json();
    if (d.success) {
        showToast('Staff deleted');
        loadStaff();
    } else {
        showToast(d.error || 'Failed', true);
    }
};

window.viewStaffGames = async (id) => {
    const s = staffList.find(x => x.id == id);
    if (!s) return;

    const res = await fetch(`${USERS_API}?action=games_of&id=${id}`);
    const games = await res.json();

    if (!games.length) {
        alert(`${s.full_name} is not assigned to any games.`);
        return;
    }

    let msg = `${s.full_name} handles:\n\n`;
    games.forEach(g => {
        msg += `• [${g.parent_group}] ${g.name}\n`;
    });
    alert(msg);
};

// ============ UTILS ============
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