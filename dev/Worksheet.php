<?php
require_once __DIR__ . '/../session_init.php';

$name = isset($_SESSION['name']) ? (string)$_SESSION['name'] : 'Employee';
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Weekly Worksheet</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet" />
    <style>
      *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

      :root {
        --bg: #f5f4f0;
        --surface: #ffffff;
        --surface-2: #f0efe9;
        --border: #d8d6cf;
        --border-strong: #bab8b0;
        --text: #1a1918;
        --text-2: #5a5855;
        --text-3: #8a8885;
        --accent: #1a56db;
        --accent-hover: #1447c0;
        --accent-surface: #eff4ff;
        --danger: #c0392b;
        --success: #166534;
        --success-bg: #dcfce7;
        --font-sans: 'DM Sans', system-ui, sans-serif;
        --font-mono: 'DM Mono', 'Courier New', monospace;
        --radius: 6px;
        --radius-lg: 10px;
      }

      body {
        font-family: var(--font-sans);
        font-size: 14px;
        background: var(--bg);
        color: var(--text);
        min-height: 100vh;
        padding: 0;
        line-height: 1.5;
      }

      /* ── Top bar ── */
      .topbar {
        background: var(--surface);
        border-bottom: 1px solid var(--border);
        padding: 0 24px;
        height: 56px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        position: sticky;
        top: 0;
        z-index: 10;
      }
      .topbar-left {
        display: flex;
        align-items: center;
        gap: 20px;
      }
      .btn-back {
        padding: 6px 10px;
        border-radius: 8px;
        background: var(--surface-2);
        border: 1px solid var(--border-strong);
        color: var(--text);
        font-weight: 600;
        cursor: pointer;
      }
      .btn-back:hover { background: var(--surface); }
      .wordmark {
        font-size: 13px;
        font-weight: 600;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: var(--text);
      }
      .sep {
        width: 1px;
        height: 18px;
        background: var(--border-strong);
      }
      .topbar-meta {
        display: flex;
        align-items: center;
        gap: 12px;
      }
      .employee-badge {
        display: flex;
        align-items: center;
        gap: 8px;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 20px;
        padding: 4px 12px 4px 6px;
      }
      .avatar {
        width: 26px;
        height: 26px;
        border-radius: 50%;
        background: var(--accent);
        color: #fff;
        font-size: 11px;
        font-weight: 600;
        display: flex;
        align-items: center;
        justify-content: center;
        letter-spacing: 0.02em;
        flex-shrink: 0;
      }
      .employee-name {
        font-size: 13px;
        font-weight: 500;
        color: var(--text);
      }
      .topbar-right {
        display: flex;
        align-items: center;
        gap: 8px;
      }
      #today-string {
        font-size: 12px;
        color: var(--text-3);
        font-family: var(--font-mono);
        margin-right: 4px;
      }

      /* ── Buttons ── */
      .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 7px 14px;
        border-radius: var(--radius);
        font-family: var(--font-sans);
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: background 0.12s, border-color 0.12s, color 0.12s;
        white-space: nowrap;
        line-height: 1;
        border: 1px solid transparent;
      }
      .btn-ghost {
        background: transparent;
        border-color: var(--border);
        color: var(--text-2);
      }
      .btn-ghost:hover { background: var(--surface-2); border-color: var(--border-strong); color: var(--text); }
      .btn-primary {
        background: var(--accent);
        border-color: var(--accent);
        color: #fff;
      }
      .btn-primary:hover { background: var(--accent-hover); border-color: var(--accent-hover); }
      .btn svg { width: 14px; height: 14px; flex-shrink: 0; }

      /* ── Page layout ── */
      .page {
        max-width: 1100px;
        margin: 0 auto;
        padding: 28px 24px 48px;
      }

      /* ── Summary strip ── */
      .summary-strip {
        display: flex;
        gap: 12px;
        margin-bottom: 20px;
      }
      .metric-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px 20px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 140px;
      }
      .metric-label {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-3);
      }
      .metric-value {
        font-size: 26px;
        font-weight: 600;
        font-family: var(--font-mono);
        color: var(--text);
        line-height: 1.1;
      }
      .metric-value.accent { color: var(--accent); }
      .metric-sub {
        font-size: 11px;
        color: var(--text-3);
        margin-top: 2px;
      }
      .week-range-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px 20px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        gap: 2px;
        flex: 1;
      }
      .week-label {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-3);
      }
      .week-dates {
        font-size: 15px;
        font-weight: 500;
        color: var(--text);
      }
      .week-num {
        font-size: 11px;
        color: var(--text-3);
        font-family: var(--font-mono);
      }
      /* Current week vs other week styles */
      .week-dates.current { color: var(--text); font-weight: 400; }
      .week-dates.other { color: var(--text-3); font-weight: 500; }
      .week-num.current { color: var(--text); font-weight: 400; }
      .week-num.other { color: var(--text-3); font-weight: 400; }
      .status-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 14px 20px;
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 140px;
      }
      .progress-wrap {
        background: var(--surface-2);
        border-radius: 3px;
        height: 5px;
        margin-top: 8px;
        overflow: hidden;
      }
      .progress-bar {
        background: var(--accent);
        height: 100%;
        border-radius: 3px;
        width: 0%;
        transition: width 0.3s ease;
      }

      /* ── Table card ── */
      .table-card {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
      }
      .table-wrap { overflow-x: auto; }

      table {
        border-collapse: collapse;
        width: 100%;
        min-width: 760px;
      }
      thead th {
        background: var(--surface-2);
        border-bottom: 1px solid var(--border);
        padding: 10px 14px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-3);
        text-align: left;
        white-space: nowrap;
      }
      tbody tr {
        border-bottom: 1px solid var(--border);
        transition: background 0.08s;
      }
      tbody tr:last-child { border-bottom: none; }
      tbody tr:hover { background: #fafaf8; }
      tbody td {
        padding: 10px 14px;
        vertical-align: middle;
      }

      /* ── Day chip ── */
      .day-chip {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--surface-2);
        border: 1px solid var(--border);
        border-radius: 4px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.04em;
        color: var(--text-2);
        font-family: var(--font-mono);
        width: 40px;
        height: 26px;
      }
      .day-chip.weekend { background: var(--accent-surface); border-color: #bfdbfe; color: var(--accent); }

      .date-display {
        font-size: 12px;
        font-family: var(--font-mono);
        color: var(--text-2);
        white-space: nowrap;
      }
      .date-display-day {
        font-size: 10px;
        color: var(--text-3);
        margin-top: 1px;
      }

      /* ── Form inputs ── */
      textarea {
        width: 100%;
        height: 64px;
        resize: vertical;
        font-family: var(--font-sans);
        font-size: 13px;
        color: var(--text);
        background: transparent;
        border: 1px solid transparent;
        border-radius: var(--radius);
        padding: 6px 8px;
        transition: border-color 0.12s, background 0.12s;
        line-height: 1.5;
        outline: none;
      }
      textarea:hover { border-color: var(--border); background: var(--bg); }
      textarea:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(26,86,219,0.08); }
      textarea::placeholder { color: var(--text-3); }

      .hours-input-wrap {
        display: flex;
        align-items: center;
        gap: 6px;
      }
      input[type="number"] {
        width: 80px;
        font-family: var(--font-mono);
        font-size: 15px;
        font-weight: 500;
        color: var(--text);
        background: transparent;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 6px 10px;
        outline: none;
        transition: border-color 0.12s, box-shadow 0.12s;
        text-align: right;
        -moz-appearance: textfield;
      }
      input[type="number"]::-webkit-inner-spin-button,
      input[type="number"]::-webkit-outer-spin-button { -webkit-appearance: none; }
      input[type="number"]:focus { border-color: var(--accent); box-shadow: 0 0 0 3px rgba(26,86,219,0.08); }
      input[type="number"]:hover:not(:focus) { border-color: var(--border-strong); }
      .hrs-unit { font-size: 11px; color: var(--text-3); font-family: var(--font-mono); }

      /* ── Row number ── */
      .row-num {
        font-size: 11px;
        color: var(--text-3);
        font-family: var(--font-mono);
        text-align: center;
        padding: 0 8px;
      }

      /* ── Footer row ── */
      tfoot tr { background: var(--surface-2) !important; border-top: 2px solid var(--border-strong); }
      tfoot td {
        padding: 12px 14px;
        font-weight: 600;
        font-size: 13px;
        color: var(--text-2);
        letter-spacing: 0.02em;
      }
      #total-hours {
        font-family: var(--font-mono);
        font-size: 18px;
        font-weight: 600;
        color: var(--text);
      }
      .total-label {
        text-align: right;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: var(--text-3);
      }

      /* ── Save feedback ── */
      .toast {
        position: fixed;
        bottom: 24px;
        right: 24px;
        background: var(--text);
        color: #fff;
        font-size: 13px;
        font-weight: 500;
        padding: 10px 18px;
        border-radius: var(--radius);
        transform: translateY(8px);
        opacity: 0;
        transition: opacity 0.2s, transform 0.2s;
        pointer-events: none;
        z-index: 100;
      }
      .toast.show { opacity: 1; transform: translateY(0); }
      .toast.success { background: var(--success); }
      .toast.error { background: var(--danger); }
    </style>
  </head>
  <body>

    <!-- Top bar -->
    <nav class="topbar">
      <div class="topbar-left">
        <button class="btn-back" onclick="location.href='index.php'" title="Back to developer dashboard">← Back</button>
        <span class="wordmark">TimeSheet</span>
        <div class="sep"></div>
        <div class="employee-badge">
          <div class="avatar" id="avatar-initials">??</div>
          <span class="employee-name"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></span>
        </div>
      </div>
      <div class="topbar-right">
        <span id="today-string"></span>
        
        <button class="btn btn-ghost" onclick="clearTable()">
          <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M2 4h12M5 4V2.5a.5.5 0 01.5-.5h5a.5.5 0 01.5.5V4M6.5 7v5M9.5 7v5M3 4l.8 9.1a.5.5 0 00.5.4h7.4a.5.5 0 00.5-.4L13 4" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          Clear
        </button>
          <button class="btn btn-primary" onclick="saveSheet()">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M2 2h9l3 3v9H2V2z" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 2v4h6V2M5 9h6" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Save Worksheet
          </button>
          <button id="printBtn" class="btn btn-primary" onclick="printWeek()">
            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5">
              <path d="M3 7h10v6a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V7z" stroke-linecap="round" stroke-linejoin="round"/>
              <path d="M5 3h6v4H5z" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            Print
          </button>
      </div>
    </nav>

    <div class="page">

      <!-- Summary strip -->
      <div class="summary-strip">
        <div class="metric-card">
          <span class="metric-label">Total Hours</span>
          <span class="metric-value accent" id="metric-total">0.00</span>
          <span class="metric-sub">this week</span>
        </div>
        <div class="week-range-card">
          <div style="display:flex; align-items:center; gap:10px;">
            <button class="btn btn-ghost" onclick="prevWeek()" aria-label="Previous week">◀</button>
            <div style="display:flex; flex-direction:column">
              <span class="week-label">Week</span>
              <span class="week-dates" id="week-dates">—</span>
              <span class="week-num" id="week-num"></span>
            </div>
            <button class="btn btn-ghost" onclick="nextWeek()" aria-label="Next week">▶</button>
          </div>
        </div>
        <div class="status-card">
          <span class="metric-label">Completion</span>
          <span class="metric-value" id="metric-pct">0%</span>
          <div class="progress-wrap"><div class="progress-bar" id="progress-bar"></div></div>
          <span class="metric-sub">of 40h standard week</span>
        </div>
      </div>

      <!-- Table -->
      <div class="table-card">
        <div class="table-wrap">
          <table id="worksheet-table">
            <thead>
              <tr>
                <th style="width:52px;">Day</th>
                <th style="width:110px;">Date</th>
                <th>Description of work</th>
                <th style="width:130px; text-align:right;">Hours</th>
              </tr>
            </thead>
            <tbody>
            <?php for ($i=0; $i<7; $i++): ?>
              <tr data-row="<?php echo $i; ?>">
                <td>
                  <span class="day-chip" data-day-chip></span>
                  <select name="day[]" style="display:none;" aria-hidden="true">
                    <option value=""></option>
                    <option value="SUN">SUN</option>
                    <option value="MON">MON</option>
                    <option value="TUE">TUE</option>
                    <option value="WED">WED</option>
                    <option value="THU">THU</option>
                    <option value="FRI">FRI</option>
                    <option value="SAT">SAT</option>
                  </select>
                </td>
                <td>
                  <div class="date-display" data-date-display></div>
                  <input type="date" name="date[]" style="display:none;" aria-hidden="true" />
                </td>
                <td>
                  <textarea name="description[]" placeholder="Describe tasks worked on…" aria-label="Description for row <?php echo $i+1; ?>"></textarea>
                </td>
                <td>
                  <div class="hours-input-wrap" style="justify-content:flex-end;">
                    <input type="number" name="hours[]" step="0.25" min="0" max="24" value="" aria-label="Hours for row <?php echo $i+1; ?>" oninput="recalcTotals()"/>
                    <span class="hrs-unit">hrs</span>
                  </div>
                </td>
              </tr>
            <?php endfor; ?>
            </tbody>
            <tfoot>
              <tr>
                <td colspan="3" class="total-label">Weekly Total</td>
                <td style="text-align:right; padding-right:20px;">
                  <span id="total-hours">0.00</span>
                  <span class="hrs-unit" style="font-size:12px; margin-left:4px;">hrs</span>
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
      const STD_WEEK = 40;

      function getInitials(name) {
        return (name || '?').split(' ').filter(Boolean).map(w => w[0].toUpperCase()).slice(0,2).join('');
      }
      document.getElementById('avatar-initials').textContent = getInitials(<?php echo json_encode($name); ?>);

      function recalcTotals() {
        const hours = Array.from(document.querySelectorAll('input[name="hours[]"]')).map(i => parseFloat(i.value) || 0);
        const total = hours.reduce((a,b) => a+b, 0);
        document.getElementById('total-hours').textContent = total.toFixed(2);
        document.getElementById('metric-total').textContent = total.toFixed(2);
        const pct = Math.min(100, Math.round((total / STD_WEEK) * 100));
        document.getElementById('metric-pct').textContent = pct + '%';
        document.getElementById('progress-bar').style.width = pct + '%';
      }

      function formatDateShort(d) {
        return d.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
      }
      function formatDateFull(d) {
        return d.toLocaleDateString(undefined, { month: 'long', day: 'numeric', year: 'numeric' });
      }
      function pad(n) { return String(n).padStart(2,'0'); }

      function getWeekNum(d) {
        const tmp = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
        tmp.setUTCDate(tmp.getUTCDate() + 4 - (tmp.getUTCDay() || 7));
        const y = tmp.getUTCFullYear();
        const w = Math.ceil((((tmp - new Date(Date.UTC(y,0,1))) / 86400000) + 1) / 7);
        return `Week ${w}, ${y}`;
      }

      // currentSunday holds the start (Sunday) of the currently displayed week
      let currentSunday = null;

      function renderWeek(sunday){
        currentSunday = new Date(sunday);
        const rows = document.querySelectorAll('#worksheet-table tbody tr');
        const dayNames = ['SUN','MON','TUE','WED','THU','FRI','SAT'];

        for (let i=0;i<rows.length;i++){
          const d = new Date(currentSunday);
          d.setDate(currentSunday.getDate() + i);
          const dateInput = rows[i].querySelector('input[name="date[]"]');
          const daySelect = rows[i].querySelector('select[name="day[]"]');
          const dayChip = rows[i].querySelector('[data-day-chip]');
          const dateDisplay = rows[i].querySelector('[data-date-display]');

          if (dateInput) dateInput.value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
          if (daySelect) daySelect.value = dayNames[i];
          if (dayChip){ dayChip.textContent = dayNames[i]; dayChip.classList.toggle('weekend', dayNames[i]==='SAT' || dayNames[i]==='SUN'); }
          if (dateDisplay) dateDisplay.textContent = formatDateShort(d);
        }

        const saturday = new Date(currentSunday); saturday.setDate(currentSunday.getDate()+6);
        const weekDatesEl = document.getElementById('week-dates');
        const weekNumEl = document.getElementById('week-num');
        weekDatesEl.textContent = `${formatDateShort(currentSunday)} – ${formatDateShort(saturday)}`;
        weekNumEl.textContent = getWeekNum(currentSunday);
        document.getElementById('today-string').textContent = formatDateFull(new Date());

        // highlight current week vs other weeks
        try{
          const todayDate = new Date();
          const thisSunday = new Date(todayDate);
          thisSunday.setDate(todayDate.getDate() - todayDate.getDay());
          const isThisWeek = currentSunday.toISOString().slice(0,10) === thisSunday.toISOString().slice(0,10);
          if (isThisWeek){ weekDatesEl.classList.add('current'); weekDatesEl.classList.remove('other'); weekNumEl.classList.add('current'); weekNumEl.classList.remove('other'); }
          else { weekDatesEl.classList.add('other'); weekDatesEl.classList.remove('current'); weekNumEl.classList.add('other'); weekNumEl.classList.remove('current'); }
        }catch(e){}

        // try loading per-week draft from localStorage
        const weekKey = 'worksheet_week_' + currentSunday.toISOString().slice(0,10);
        try{
          const d = JSON.parse(localStorage.getItem(weekKey) || 'null');
          const rowsEls = document.querySelectorAll('#worksheet-table tbody tr');
          if (d && d.rows){
            d.rows.forEach((r,i)=>{ if (!rowsEls[i]) return; rowsEls[i].querySelector('textarea[name="description[]"]').value = r.description || ''; rowsEls[i].querySelector('input[name="hours[]"]').value = r.hours || ''; });
            recalcTotals();
            return;
          }
        }catch(e){}

        // no per-week draft: clear descriptions and hours
        document.querySelectorAll('#worksheet-table tbody textarea, #worksheet-table tbody input[name="hours[]"]').forEach(el => el.value = '');
        recalcTotals();
      }

      function prevWeek(){ if (!currentSunday) return; const d = new Date(currentSunday); d.setDate(d.getDate()-7); renderWeek(d); }
      function nextWeek(){ if (!currentSunday) return; const d = new Date(currentSunday); d.setDate(d.getDate()+7); renderWeek(d); }

      function collectData() {
        const rows = document.querySelectorAll('#worksheet-table tbody tr');
        return Array.from(rows).map(r => ({
          day: r.querySelector('select[name="day[]"]').value,
          date: r.querySelector('input[name="date[]"]').value,
          description: r.querySelector('textarea[name="description[]"]').value,
          hours: r.querySelector('input[name="hours[]"]').value,
        }));
      }

      function showToast(msg, type) {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast show ' + (type || '');
        clearTimeout(t._timer);
        t._timer = setTimeout(() => { t.className = 'toast'; }, 2800);
      }

      async function saveSheet() {
        const data = {
          employee: <?php echo json_encode($name); ?>,
          rows: collectData(),
          total_hours: document.getElementById('total-hours').textContent
        };
        try { localStorage.setItem('worksheet_draft', JSON.stringify(data)); } catch(e) {}
        // also store per-week draft keyed by currentSunday
        try{
          const sundayKeyDate = (currentSunday || (function(){const t=new Date(); const dow=t.getDay(); t.setDate(t.getDate()-dow); return t; })()).toISOString().slice(0,10);
          const weekKey = 'worksheet_week_' + sundayKeyDate;
          localStorage.setItem(weekKey, JSON.stringify(data));
        }catch(e){}
        try {
          const res = await fetch('save_worksheet.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
          });
          const j = await res.json();
          if (j && j.success) { showToast('Worksheet saved.', 'success'); }
          else { showToast('Save failed — please try again.', 'error'); }
        } catch(e) {
          showToast('Saved locally (network unavailable).', '');
        }
      }

      function escapeHtml(str){
        return String(str === undefined || str === null ? '' : str)
          .replace(/&/g,'&amp;')
          .replace(/</g,'&lt;')
          .replace(/>/g,'&gt;')
          .replace(/"/g,'&quot;')
          .replace(/'/g,'&#39;');
      }

      // Inline logo SVG (read from server) to ensure it appears in print previews
      const logoSvgHtml = <?php
        $logoPath = __DIR__ . '/../assets/images/Horse.svg';
        $logoHtml = '';
        if (is_readable($logoPath)) {
          $logoHtml = file_get_contents($logoPath);
        }
        echo json_encode($logoHtml);
      ?>;

      function printWeek(){
        const rows = collectData();
        const emp = <?php echo json_encode($name); ?>;
        const totalHours = document.getElementById('total-hours').textContent;

        function safeDateParts(value) {
          const parts = String(value || '').split('-').map(Number);
          if (parts.length !== 3 || parts.some(n => !Number.isFinite(n))) return null;
          return { y: parts[0], m: parts[1], d: parts[2] };
        }

        function formatSheetDate(value) {
          const p = safeDateParts(value);
          return p ? `${p.m}/${p.d}` : '';
        }

        function formatWeekEnding(value) {
          const p = safeDateParts(value);
          if (!p) return '';
          return `${p.m}/${p.d}/${p.y}`;
        }

        const weekEnding = rows[6] && rows[6].date ? formatWeekEnding(rows[6].date) : '';
        const dayOrder = ['SUN','MON','TUE','WED','THU','FRI','SAT'];
        const linesPerDay = { SUN: 2, MON: 5, TUE: 5, WED: 5, THU: 5, FRI: 5, SAT: 3 };

        const rowMap = {};
        rows.forEach(r => {
          rowMap[r.day] = {
            day: r.day || '',
            date: r.date || '',
            description: r.description || '',
            hours: r.hours || ''
          };
        });

        let bodyRows = '';
        let officeUsePrinted = false;

        dayOrder.forEach(day => {
          const r = rowMap[day] || { day, date: '', description: '', hours: '' };
          const lineCount = linesPerDay[day];

          for (let line = 0; line < lineCount; line++) {
            bodyRows += '<tr class="data-row">';

            bodyRows += '<td class="office-left">' +
              (!officeUsePrinted ? '<span class="office-left-label">Office Use</span>' : '') +
              '</td>';
            officeUsePrinted = true;

            if (line === 0) {
              bodyRows += '<td class="day-cell" rowspan="' + lineCount + '">' +
                '<span>' + escapeHtml(day) + '</span></td>';
              bodyRows += '<td class="date-cell" rowspan="' + lineCount + '">' +
                escapeHtml(formatSheetDate(r.date)) + '</td>';
            }

            bodyRows += '<td class="truck-cell">&nbsp;</td>';

            if (line === 0) {
              bodyRows += '<td class="description-cell" rowspan="' + lineCount + '">' +
                '<div class="description-text">' +
                escapeHtml(r.description).replace(/\r?\n/g, '<br>') +
                '</div></td>';
            }

            bodyRows += '<td class="hours-cell">' +
              (line === 0 ? escapeHtml(r.hours) : '&nbsp;') +
              '</td>';

            bodyRows += '<td class="office-small">&nbsp;</td>';
            bodyRows += '<td class="office-small">&nbsp;</td>';
            bodyRows += '</tr>';
          }
        });

        const html = `<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <title>Weekly Time Tracker</title>
  <style>
    @page {
      size: letter landscape;
      margin: 0.22in;
    }

    * { box-sizing: border-box; }

    html, body {
      margin: 0;
      padding: 0;
      background: #fff;
      color: #000;
      font-family: Arial, Helvetica, sans-serif;
    }

    body {
      width: 100%;
    }

    .sheet {
      width: 10.56in;
      min-height: 8.02in;
      margin: 0 auto;
      border: 2px solid #111;
      padding: 6px 7px 6px;
    }

    .sheet-header {
      height: 76px;
      position: relative;
      margin-bottom: 2px;
    }

    .brand-lockup {
      position: absolute;
      left: 7px;
      top: 0;
      width: 72px;
      height: 72px;
      text-align: center;
      overflow: hidden;
    }
    .brand-lockup svg { width: 72px; height: 72px; display: block; }

    .brand-logo {
      display: block;
      width: 58px;
      height: 42px;
      object-fit: contain;
      margin: 0 auto 0;
    }

    .brand-name {
      font-size: 14px;
      line-height: 13px;
      font-weight: 700;
      margin-top: -1px;
      white-space: nowrap;
    }

    .brand-subtitle {
      font-family: Georgia, 'Times New Roman', serif;
      font-size: 8px;
      line-height: 10px;
      font-weight: 700;
      letter-spacing: 0.15px;
      white-space: nowrap;
    }

    .sheet-title {
      position: absolute;
      left: 120px;
      right: 160px;
      top: -1px;
      text-align: center;
      font-size: 14px;
      font-weight: 700;
    }

    .name-field {
      position: absolute;
      left: 148px;
      top: 31px;
      font-size: 12px;
      font-weight: 700;
      width: 285px;
      white-space: nowrap;
    }

    .week-field {
      position: absolute;
      left: 620px;
      top: 31px;
      font-size: 12px;
      font-weight: 700;
      width: 270px;
      white-space: nowrap;
    }

    .field-line {
      display: inline-block;
      min-width: 175px;
      margin-left: 5px;
      border-bottom: 2px solid #111;
      padding: 0 4px 1px;
      font-weight: 400;
      vertical-align: baseline;
      height: 18px;
    }

    .time-table {
      width: 100%;
      border-collapse: collapse;
      table-layout: fixed;
      font-size: 11px;
      line-height: 1;
    }

    .time-table th,
    .time-table td {
      border: 1.5px solid #111;
      color: #000;
      padding: 0 4px;
      vertical-align: middle;
    }

    .time-table thead th {
      height: 19px;
      font-size: 11px;
      font-weight: 700;
      text-align: left;
      white-space: nowrap;
    }

    .time-table thead .office-use-title {
      font-size: 8px;
      text-align: center;
      vertical-align: bottom;
      padding-bottom: 1px;
      height: 11px;
      border-bottom: 0;
    }

    .time-table thead .office-sub {
      text-align: center;
      font-size: 11px;
      height: 16px;
      border-top: 0;
    }

    .blank-head {
      background: #fff;
    }

    .office-col { width: 96px; }
    .day-col { width: 24px; }
    .date-col { width: 58px; }
    .truck-col { width: 145px; }
    .desc-col { width: auto; }
    .hours-col { width: 78px; }
    .office-small-col { width: 42px; }

    .data-row {
      height: 18px;
    }

    .data-row td {
      height: 18px;
      font-size: 11px;
    }

    .office-left {
      text-align: left;
      padding-left: 6px !important;
    }

    .office-left-label {
      display: inline-block;
      font-style: italic;
      font-weight: 700;
      font-size: 11px;
      transform: translateY(1px);
    }

    .day-cell {
      position: relative;
      text-align: center;
      padding: 0 !important;
      font-weight: 700;
    }

    .day-cell span {
      display: inline-block;
      writing-mode: vertical-rl;
      transform: rotate(180deg);
      letter-spacing: 0.4px;
      font-size: 11px;
      line-height: 1;
    }

    .date-cell {
      text-align: center;
      font-size: 10px !important;
      font-weight: 600;
      vertical-align: middle;
      padding: 0 2px !important;
    }

    .truck-cell {
      padding: 0 4px !important;
    }

    .description-cell {
      position: relative;
      vertical-align: top !important;
      padding: 0 !important;
      background-image:
        repeating-linear-gradient(
          to bottom,
          transparent 0,
          transparent 17px,
          #111 17px,
          #111 18px
        );
      background-position: 0 0;
      overflow: hidden;
    }

    .description-text {
      padding: 3px 6px 0;
      font-size: 10px;
      line-height: 14px;
      font-weight: 400;
      white-space: normal;
      overflow-wrap: anywhere;
      max-height: 100%;
    }

    .hours-cell {
      text-align: center;
      font-size: 11px !important;
      font-weight: 600;
    }

    .office-small {
      text-align: center;
    }

    .totals-row td {
      height: 20px;
      font-size: 11px;
      font-weight: 700;
    }

    .totals-label {
      text-align: right;
      border-left: 0 !important;
      border-right: 0 !important;
      padding-right: 8px !important;
    }

    .totals-value {
      text-align: center;
      font-size: 12px !important;
    }

    .left-total-fill {
      border-right: 0 !important;
    }

    .total-desc-fill {
      border-left: 0 !important;
    }

    @media print {
      .sheet { break-inside: avoid; page-break-inside: avoid; }
    }
  </style>
</head>
<body>
  <div class="sheet">
    <div class="sheet-header">
      <div class="brand-lockup">
        ${logoSvgHtml}
        <div class="brand-name">Dark Horse</div>
        <div class="brand-subtitle">Custom Spreader Trucks</div>
      </div>
      <div class="sheet-title">Weekly Time Tracker</div>
      <div class="name-field">Name:<span class="field-line">${escapeHtml(emp)}</span></div>
      <div class="week-field">Week Ending:<span class="field-line">${escapeHtml(weekEnding)}</span></div>
    </div>

    <table class="time-table">
      <colgroup>
        <col class="office-col">
        <col class="day-col">
        <col class="date-col">
        <col class="truck-col">
        <col class="desc-col">
        <col class="hours-col">
        <col class="office-small-col">
        <col class="office-small-col">
      </colgroup>
      <thead>
        <tr>
          <th class="blank-head" rowspan="2"></th>
          <th class="blank-head" rowspan="2"></th>
          <th rowspan="2">Date</th>
          <th rowspan="2">Truck #/DSS Project</th>
          <th rowspan="2">Description</th>
          <th rowspan="2" style="text-align:center;">Hours</th>
          <th class="office-use-title" colspan="2">Office Use</th>
        </tr>
        <tr>
          <th class="office-sub">T</th>
          <th class="office-sub">PD</th>
        </tr>
      </thead>
      <tbody>
        ${bodyRows}
      </tbody>
      <tfoot>
        <tr class="totals-row">
          <td class="left-total-fill" colspan="4">&nbsp;</td>
          <td class="totals-label total-desc-fill">Totals</td>
          <td class="totals-value">${escapeHtml(totalHours)}</td>
          <td>&nbsp;</td>
          <td>&nbsp;</td>
        </tr>
      </tfoot>
    </table>
  </div>
</body>
</html>`;

        // Create a hidden iframe, write the printable HTML into it, then call print()
        const iframe = document.createElement('iframe');
        iframe.style.position = 'fixed';
        iframe.style.right = '0';
        iframe.style.bottom = '0';
        iframe.style.width = '0';
        iframe.style.height = '0';
        iframe.style.border = '0';
        iframe.style.visibility = 'hidden';
        document.body.appendChild(iframe);

        try {
          // Use srcdoc for modern browsers
          iframe.srcdoc = html;
        } catch (e) {
          // Fallback: write into iframe document
          const idoc = iframe.contentWindow.document;
          idoc.open(); idoc.write(html); idoc.close();
        }

        iframe.onload = () => {
          try {
            const w = iframe.contentWindow;
            // Wait for images to load inside iframe before printing
            const imgs = iframe.contentDocument.images || [];
            const loadPromises = [];
            for (let i = 0; i < imgs.length; i++) {
              const img = imgs[i];
              if (!img.complete) {
                loadPromises.push(new Promise(res => { img.addEventListener('load', res); img.addEventListener('error', res); }));
              }
            }

            (loadPromises.length ? Promise.all(loadPromises) : Promise.resolve()).then(() => {
              try {
                w.focus();
                w.print();
              } catch (err) {
                showToast('Print failed: ' + (err.message || err), 'error');
              }
              setTimeout(() => { try { document.body.removeChild(iframe); } catch(e){} }, 600);
            });
          } catch (err) {
            showToast('Print failed: ' + (err.message || err), 'error');
            try { document.body.removeChild(iframe); } catch(e){}
          }
        };
      }

      function clearTable() {
        if (!confirm('Clear all descriptions and hours? Dates will be kept.')) return;
        document.querySelectorAll('#worksheet-table tbody textarea, #worksheet-table tbody input[name="hours[]"]').forEach(el => { el.value = ''; });
        recalcTotals();
      }

      document.addEventListener('input', e => {
        if (e.target && e.target.name === 'hours[]') recalcTotals();
      });

      (function init() {
        try {
          const today = new Date(); const dayOfWeek = today.getDay(); const sunday = new Date(today); sunday.setDate(today.getDate()-dayOfWeek);
          renderWeek(sunday);
        } catch(e) {}
      })();
    </script>
  </body>
</html>
