const API = 'api/events.php';

const uiIcon = (name, size = 15) => {
    const paths = {
        users: '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        staff: '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM23 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>'
    };
    return `<svg width="${size}" height="${size}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">${paths[name] || ''}</svg>`;
};

let allEvents = [];
let viewYear, viewMonth; // viewMonth: 0-11
let selectedDate = null; // 'YYYY-MM-DD'

// ============ INIT ============
document.addEventListener('DOMContentLoaded', async () => {
    const now = new Date();
    viewYear  = now.getFullYear();
    viewMonth = now.getMonth();

    await loadEvents();
    bindNav();
    render();
});

// ============ DATA ============
async function loadEvents() {
    try {
        const res = await fetch(API);
        const data = await res.json();
        allEvents = res.ok && Array.isArray(data) ? data : [];
    } catch {
        allEvents = [];
    }
    updateStats();
}

function updateStats() {
    const total    = allEvents.length;
    const approved = allEvents.filter(e => e.status === 'approved').length;
    const pending  = allEvents.filter(e => e.status === 'pending').length;

    const todayStr = toDateStr(new Date());
    const today = allEvents.filter(e => {
        if (!e.start_datetime) return false;
        return toDateStr(new Date(e.start_datetime.replace(' ', 'T'))) === todayStr;
    }).length;

    document.getElementById('statTotal').textContent    = total;
    const approvedStat = document.getElementById('statApproved');
    const pendingStat = document.getElementById('statPending');
    if (approvedStat) approvedStat.textContent = approved;
    if (pendingStat) pendingStat.textContent = pending;
    document.getElementById('statToday').textContent    = today;
}

// ============ NAV ============
function bindNav() {
    document.getElementById('prevMonth').onclick = () => {
        viewMonth--;
        if (viewMonth < 0) { viewMonth = 11; viewYear--; }
        render();
    };
    document.getElementById('nextMonth').onclick = () => {
        viewMonth++;
        if (viewMonth > 11) { viewMonth = 0; viewYear++; }
        render();
    };
    document.getElementById('todayBtn').onclick = () => {
        const now = new Date();
        viewYear  = now.getFullYear();
        viewMonth = now.getMonth();
        selectedDate = toDateStr(now);
        render();
    };
}

// ============ RENDER ============
function render() {
    renderCalendar();
    renderDayPanel();
}

function renderCalendar() {
    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    const monthNames = ['January','February','March','April','May','June',
                        'July','August','September','October','November','December'];
    document.getElementById('monthLabel').textContent = `${monthNames[viewMonth]} ${viewYear}`;

    const firstOfMonth = new Date(viewYear, viewMonth, 1);
    const startWeekday = firstOfMonth.getDay();        // 0 = Sun
    const daysInMonth  = new Date(viewYear, viewMonth + 1, 0).getDate();
    const daysInPrev   = new Date(viewYear, viewMonth, 0).getDate();

    // Group events by date string
    const eventsByDate = {};
    allEvents.forEach(ev => {
        if (!ev.start_datetime) return;
        const d = new Date(ev.start_datetime.replace(' ', 'T'));
        const key = toDateStr(d);
        (eventsByDate[key] = eventsByDate[key] || []).push(ev);
    });

    const todayStr = toDateStr(new Date());

    // Leading days from previous month
    for (let i = startWeekday - 1; i >= 0; i--) {
        const day = daysInPrev - i;
        grid.appendChild(makeDayCell(day, true, null, eventsByDate));
    }

    // Days of current month
    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = `${viewYear}-${pad(viewMonth + 1)}-${pad(d)}`;
        const isToday = dateStr === todayStr;
        const cell = makeDayCell(d, false, dateStr, eventsByDate, isToday);
        if (selectedDate === dateStr) cell.classList.add('selected');
        grid.appendChild(cell);
    }

    // Trailing days to fill 6 rows (42 cells total)
    const totalCells = startWeekday + daysInMonth;
    const trailing   = (7 - (totalCells % 7)) % 7;
    for (let d = 1; d <= trailing; d++) {
        grid.appendChild(makeDayCell(d, true, null, eventsByDate));
    }
}

function makeDayCell(day, isOutside, dateStr, eventsByDate, isToday = false) {
    const cell = document.createElement('div');
    cell.className = 'calendar-day';
    if (isOutside) cell.classList.add('outside');
    if (isToday)   cell.classList.add('today');

    const num = document.createElement('div');
    num.className = 'day-number';
    num.textContent = day;
    cell.appendChild(num);

    // Event chips
    if (dateStr && eventsByDate[dateStr]) {
        const evs = eventsByDate[dateStr];
        const list = document.createElement('div');
        list.className = 'day-events-mini';

        const max = 2;
        evs.slice(0, max).forEach(ev => {
            const chip = document.createElement('div');
            chip.className = 'mini-event';
            chip.title = `${ev.name} (${ev.category})`;
            chip.textContent = ev.name.length > 18
                ? ev.name.slice(0, 18) + '…'
                : ev.name;
            list.appendChild(chip);
        });

        if (evs.length > max) {
            const more = document.createElement('div');
            more.className = 'mini-more';
            more.textContent = `+${evs.length - max} more`;
            list.appendChild(more);
        }
        cell.appendChild(list);
    }

    // Click handler
    if (dateStr) {
        cell.onclick = () => {
            selectedDate = dateStr;
            render();
        };
    }
    return cell;
}

function renderDayPanel() {
    const title = document.getElementById('dayPanelTitle');
    const list  = document.getElementById('dayEventsList');

    if (!selectedDate) {
        title.textContent = 'Select a day';
        list.innerHTML = `<div class="empty-state">Click a day on the calendar to see its events.</div>`;
        return;
    }

    const d = new Date(selectedDate + 'T00:00:00');
    title.textContent = d.toLocaleDateString(undefined, {
        weekday: 'long', month: 'long', day: 'numeric', year: 'numeric'
    });

    const dayEvents = allEvents.filter(ev => {
        if (!ev.start_datetime) return false;
        return toDateStr(new Date(ev.start_datetime.replace(' ', 'T'))) === selectedDate;
    }).sort((a, b) => new Date(a.start_datetime) - new Date(b.start_datetime));

    if (!dayEvents.length) {
        list.innerHTML = `<div class="empty-state">No events scheduled for this day.</div>`;
        return;
    }

    list.innerHTML = '';
    dayEvents.forEach(ev => list.appendChild(buildDayEventCard(ev)));
}

function buildDayEventCard(ev) {
    const el = document.createElement('div');
    el.className = 'day-event-item';

    const timeRange = formatTimeRange(ev.start_datetime, ev.end_datetime);
    const duration  = formatDuration(ev.start_datetime, ev.end_datetime);
    const staffTags = (ev.staff || []).map(s =>
        `<span class="staff-tag">${escapeHtml(s.full_name)}</span>`
    ).join('');
    const participantText = (ev.participants || []).map(participant => {
        const name = participant.team_name || participant.name;
        const members = (participant.members || []).join(', ');
        return members ? `${name} (${members})` : name;
    }).filter(Boolean).join(' · ') || 'No participants listed';

    const statusClass = `status-${ev.status}`;
    const statusBadge = window.CURRENT_USER?.role === 'student'
        ? ''
        : `<span class="${statusClass}">${ev.status}</span>`;

    el.innerHTML = `
        <div class="day-event-time">
            <div class="time-range">${timeRange}</div>
            <div class="duration">${duration}</div>
        </div>
        <div class="day-event-info">
            <div class="day-event-title">${escapeHtml(ev.name)}</div>
            <div class="day-event-sub">
                <span class="event-category-badge">${escapeHtml(ev.category)}</span>
                ${statusBadge}
            </div>
            <div class="day-event-meta">
                <span>${uiIcon('users')} ${escapeHtml(participantText)}</span>
                ${staffTags ? `<span>${uiIcon('staff')} ${staffTags}</span>` : ''}
            </div>
        </div>
    `;
    return el;
}

// ============ HELPERS ============
function pad(n) { return String(n).padStart(2, '0'); }

function toDateStr(d) {
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function parseDT(s) {
    if (!s) return null;
    return new Date(String(s).replace(' ', 'T'));
}

function formatTimeRange(startStr, endStr) {
    const s = parseDT(startStr);
    const e = parseDT(endStr);
    if (!s) return '—';
    const fmt = d => d.toLocaleTimeString(undefined, {
        hour: 'numeric', minute: '2-digit'
    });
    return e ? `${fmt(s)} – ${fmt(e)}` : fmt(s);
}

function formatDuration(startStr, endStr) {
    const s = parseDT(startStr);
    const e = parseDT(endStr);
    if (!s || !e) return '';
    const mins = Math.max(0, Math.round((e - s) / 60000));
    const h = Math.floor(mins / 60);
    const m = mins % 60;
    if (h && m) return `${h}h ${m}m`;
    if (h)      return `${h}h`;
    return `${m}m`;
}

function escapeHtml(str) {
    if (str == null) return '';
    return String(str).replace(/[&<>"']/g, m => ({
        '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
    }[m]));
}