/**
 * public/js/app.js
 * Logika Frontend untuk 4 Halaman & 8 Komponen Utama:
 * - /chat            (ChatInterface, ChatMessage, SourceReference)
 * - /knowledge-base  (SearchBox, KnowledgeBaseList, KnowledgeBaseDetail)
 * - /testing         (TestingPanel)
 * - /dashboard       (DashboardCard)
 */

let currentKbPage = 1;
let cachedKbRows = [];

document.addEventListener('DOMContentLoaded', () => {
    // Navigasi antar halaman (/chat, /knowledge-base, /testing, /dashboard)
    document.querySelectorAll('.nav-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const target = btn.getAttribute('data-target');
            const route = btn.getAttribute('data-route') || '';
            switchTab(target, route);
        });
    });

    const initialTab = document.body.getAttribute('data-initial-tab') || 'page-chatbot';
    switchTab(initialTab, '', false);

    loadKbFileInfo();
    loadDashboardStats();
});

function switchTab(tabId, routeSlug = '', pushUrl = true) {
    document.querySelectorAll('.nav-btn').forEach(b => {
        b.classList.toggle('active', b.getAttribute('data-target') === tabId);
    });
    document.querySelectorAll('.page-section').forEach(sec => {
        sec.classList.toggle('active', sec.id === tabId);
    });

    if (pushUrl && routeSlug && window.history && window.history.replaceState) {
        const baseUrl = window.location.pathname.replace(/\/(chat|knowledge-base|testing|dashboard)$/, '');
        window.history.replaceState({}, '', baseUrl.replace(/\/$/, '') + '/' + routeSlug);
    }

    if (tabId === 'page-kb') {
        loadKbFileInfo();
        loadKbTable(1);
    } else if (tabId === 'page-testing') {
        loadTestList();
    } else if (tabId === 'page-dashboard') {
        loadDashboardStats();
    }
}

/* =========================================================
   1. HALAMAN /chat (ChatInterface, ChatMessage, SourceReference)
   ========================================================= */
function askSample(text) {
    const input = document.getElementById('chatInput');
    if (!input) return;
    input.value = text;
    sendChat();
}

function getCurrentTimeStr() {
    const now = new Date();
    return now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace('.', ':');
}

function toggleSourceDrawer() {
    const drawer = document.getElementById('sourceDrawer');
    if (drawer) {
        drawer.classList.toggle('open');
    }
}

async function sendChat() {
    const input = document.getElementById('chatInput');
    const btn = document.getElementById('btnSendChat');
    const question = input.value.trim();

    if (!question) return;

    appendChatMessage('user', question);
    input.value = '';
    btn.disabled = true;

    const loadingId = appendChatMessage('bot', 'Sebentar ya, saya bantu cek informasinya...');

    try {
        const res = await fetch('api/chat.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ question })
        });
        const data = await res.json();
        const replyText = data.answer || data.error || 'Terjadi kesalahan.';

        updateChatMessage(loadingId, replyText, data);
        renderSourceReference(data);

        // Perbarui pratinjau pada daftar percakapan tengah
        const previewEl = document.getElementById('threadLastPreview');
        const timeEl = document.getElementById('threadLastTime');
        if (previewEl) {
            previewEl.textContent = replyText.replace(/\s+/g, ' ').substring(0, 55) + '...';
        }
        if (timeEl) {
            timeEl.textContent = getCurrentTimeStr();
        }
    } catch (err) {
        updateChatMessage(loadingId, 'Maaf ya, koneksi ke server sedang terkendala: ' + err.message);
    } finally {
        btn.disabled = false;
    }
}

function appendChatMessage(role, text) {
    const box = document.getElementById('chatBox');
    const div = document.createElement('div');
    const id = 'msg_' + Date.now() + '_' + Math.floor(Math.random() * 1000);
    div.id = id;
    div.className = 'msg ' + (role === 'user' ? 'msg-user' : 'msg-bot');

    const body = document.createElement('div');
    body.className = 'msg-text';
    body.textContent = text;

    const timeRow = document.createElement('div');
    timeRow.className = 'msg-time';
    timeRow.innerHTML = role === 'user'
        ? `<span>${getCurrentTimeStr()}</span> <span class="msg-check">&check;&check;</span>`
        : `<span>${getCurrentTimeStr()}</span>`;

    div.appendChild(body);
    div.appendChild(timeRow);
    box.appendChild(div);
    box.scrollTop = box.scrollHeight;
    return id;
}

function updateChatMessage(id, text, meta = null) {
    const div = document.getElementById(id);
    if (!div) return;
    div.innerHTML = '';

    const contentEl = document.createElement('div');
    contentEl.className = 'msg-text';
    contentEl.textContent = text;
    div.appendChild(contentEl);

    const timeRow = document.createElement('div');
    timeRow.className = 'msg-time';
    timeRow.innerHTML = `<span>${getCurrentTimeStr()}</span>`;
    div.appendChild(timeRow);

    const box = document.getElementById('chatBox');
    box.scrollTop = box.scrollHeight;
}

function renderSourceReference(data) {
    if (!data || !data.ok) return;

    document.getElementById('inspOriginal').textContent = data.user_query || '-';
    document.getElementById('inspNormalized').textContent = data.normalization?.normalized || '(kosong)';

    const tokensWrap = document.getElementById('inspTokens');
    tokensWrap.innerHTML = '';
    (data.normalization?.tokens || []).forEach(t => {
        const span = document.createElement('span');
        span.className = 'badge badge-blue';
        span.style.marginRight = '4px';
        span.textContent = t;
        tokensWrap.appendChild(span);
    });

    const sourcesWrap = document.getElementById('inspSources');
    sourcesWrap.innerHTML = '';

    if (!data.sources || data.sources.length === 0) {
        sourcesWrap.innerHTML = `
            <div class="kb-source-item" style="border-color:#fde68a;background:#fffbeb;">
                <strong>0 Knowledge Base Relevan Ditemukan</strong>
                <p style="font-size:0.8rem;color:#92400e;margin-top:4px;">
                    Tidak ada dokumen yang memenuhi ambang skor relevansi. Sistem menolak mengarang informasi di luar Knowledge Base.
                </p>
            </div>
        `;
        return;
    }

    data.sources.forEach((kb, idx) => {
        const item = document.createElement('div');
        item.className = 'kb-source-item';
        const kwText = (kb.keywords && kb.keywords.length) ? kb.keywords.slice(0, 5).join(', ') : '-';

        item.innerHTML = `
            <div class="kb-source-header">
                <div>
                    <span class="badge badge-blue">#${idx + 1}</span>
                    <span class="kb-source-title">${escapeHtml(kb.title)}</span>
                    <div style="font-size:0.74rem;color:#64748b;margin-top:2px;">
                        Kategori: <strong>${escapeHtml(kb.category)}</strong> &bull; ID: <code>${escapeHtml((kb.id || '').substring(0, 12))}...</code>
                    </div>
                </div>
                <span class="badge badge-green">Skor: ${kb.relevance_score}</span>
            </div>
            <div style="font-size:0.75rem;color:#475569;"><strong>Kata Kunci:</strong> ${escapeHtml(kwText)}</div>
            <div class="kb-source-snippet">${escapeHtml(kb.answer)}</div>
        `;
        sourcesWrap.appendChild(item);
    });
}

/* =========================================================
   2. HALAMAN /knowledge-base (SearchBox, KnowledgeBaseList, KnowledgeBaseDetail)
   ========================================================= */
async function loadKbFileInfo() {
    try {
        const res = await fetch('api/kb.php?action=file_info');
        const data = await res.json();

        const statusEl = document.getElementById('kbStatusBanner');
        const catSelect = document.getElementById('kbCategoryFilter');

        if (data.categories && catSelect && catSelect.options.length <= 1) {
            data.categories.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c;
                opt.textContent = c;
                catSelect.appendChild(opt);
            });
        }

        if (statusEl) {
            statusEl.innerHTML = `
                File Sumber: <code>knowledge-base-2026-11-10.json</code> (${data.json_count || 2229} record)
                &nbsp;&bull;&nbsp;
                Tersimpan di Database Supabase:
                <span class="badge ${data.db_count > 0 ? 'badge-green' : 'badge-amber'}">${data.db_count || 0} record</span>
            `;
        }
    } catch (e) {
        console.error(e);
    }
}

async function importKnowledgeBase() {
    const btn = document.getElementById('btnImportKb');
    const msg = document.getElementById('importProgressMsg');

    btn.disabled = true;
    btn.textContent = 'Mengimpor 2.229 Record ke Supabase...';
    msg.textContent = 'Mohon tunggu ±5-15 detik, sedang mengirim batch ke Supabase...';

    try {
        const res = await fetch('api/kb.php?action=import', { method: 'POST' });
        const data = await res.json();

        if (data.ok) {
            msg.innerHTML = `<span class="badge badge-green">Berhasil! ${data.imported_count} record dimasukkan. Total di Supabase: ${data.total_in_db} record.</span>`;
            loadKbFileInfo();
            loadKbTable(1);
            loadDashboardStats();
        } else {
            const errDetail = (data.errors && data.errors.length) ? data.errors.join('; ') : (data.error || 'Gagal import');
            msg.innerHTML = `<span class="badge badge-red">Gagal: ${escapeHtml(errDetail)}</span>`;
        }
    } catch (e) {
        msg.innerHTML = `<span class="badge badge-red">Error: ${escapeHtml(e.message)}</span>`;
    } finally {
        btn.disabled = false;
        btn.textContent = 'Import JSON ke Supabase (2.229 Record)';
    }
}

async function loadKbTable(page = 1) {
    currentKbPage = Math.max(1, page);
    const q = document.getElementById('kbSearchInput')?.value.trim() || '';
    const category = document.getElementById('kbCategoryFilter')?.value || '';
    const tbody = document.getElementById('kbTableBody');

    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#64748b;">Memuat data Knowledge Base dari Supabase...</td></tr>`;

    try {
        const url = `api/kb.php?action=list&page=${currentKbPage}&limit=15&q=${encodeURIComponent(q)}&category=${encodeURIComponent(category)}`;
        const res = await fetch(url);
        const data = await res.json();

        if (!data.ok) {
            tbody.innerHTML = `<tr><td colspan="5" style="color:#b91c1c;">Gagal mengambil data dari Supabase: ${escapeHtml(data.error || '')}</td></tr>`;
            return;
        }

        cachedKbRows = data.rows || [];

        if (cachedKbRows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada data yang ditampilkan. Pastikan sudah klik tombol <strong>"Import JSON ke Supabase"</strong>.</td></tr>`;
            document.getElementById('kbPageInfo').textContent = '0 data';
            return;
        }

        tbody.innerHTML = '';
        cachedKbRows.forEach((row, index) => {
            const tr = document.createElement('tr');
            const kwBadges = (row.keywords || []).slice(0, 3).map(k => `<span class="badge badge-blue" style="margin:2px;">${escapeHtml(k)}</span>`).join(' ');
            tr.innerHTML = `
                <td>
                    <span class="badge badge-blue">${escapeHtml(row.category || 'Umum')}</span>
                    <div style="font-size:0.72rem;color:#64748b;margin-top:4px;"><code>${escapeHtml((row.id || '').substring(0, 10))}...</code></div>
                </td>
                <td style="font-weight:700;">${escapeHtml(row.title)}</td>
                <td>
                    <div style="max-height:85px;overflow-y:auto;white-space:pre-wrap;font-size:0.81rem;color:#334155;">${escapeHtml(row.answer)}</div>
                </td>
                <td>${kwBadges || '-'}</td>
                <td>
                    <button type="button" class="btn btn-sm btn-outline" onclick="openKbDetail(${index})">Detail</button>
                </td>
            `;
            tbody.appendChild(tr);
        });

        const totalPages = Math.max(1, Math.ceil((data.total || 0) / data.limit));
        document.getElementById('kbPageInfo').textContent = `Halaman ${data.page} dari ${totalPages} (Total: ${data.total} record)`;
        document.getElementById('btnPrevKb').disabled = (data.page <= 1);
        document.getElementById('btnNextKb').disabled = (data.page >= totalPages);
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:#b91c1c;">Gagal memuat data: ${escapeHtml(e.message)}</td></tr>`;
    }
}

function openKbDetail(index) {
    const row = cachedKbRows[index];
    if (!row) return;

    document.getElementById('detailCategory').textContent = row.category || 'Umum';
    document.getElementById('detailTitle').textContent = row.title || '-';
    document.getElementById('detailId').textContent = row.id || '-';
    document.getElementById('detailSourceRef').textContent = row.source_ref || 'Data Resmi Pemkab Purworejo';
    document.getElementById('detailAnswer').textContent = row.answer || '-';

    const kwContainer = document.getElementById('detailKeywords');
    kwContainer.innerHTML = '';
    if (row.keywords && row.keywords.length > 0) {
        row.keywords.forEach(kw => {
            const span = document.createElement('span');
            span.className = 'badge badge-blue';
            span.textContent = kw;
            kwContainer.appendChild(span);
        });
    } else {
        kwContainer.textContent = '-';
    }

    document.getElementById('kbDetailModal').style.display = 'flex';
}

function closeKbDetail() {
    const modal = document.getElementById('kbDetailModal');
    if (modal) modal.style.display = 'none';
}

/* =========================================================
   3. HALAMAN /testing (TestingPanel)
   ========================================================= */
async function loadTestList() {
    const tbody = document.getElementById('testTableBody');
    tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#64748b;">Memuat daftar pengujian...</td></tr>`;

    try {
        const res = await fetch('api/test.php?action=list');
        const data = await res.json();

        if (!data.ok || !data.tests || data.tests.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align:center;color:#64748b;">Belum ada hasil pengujian. Tambahkan pertanyaan uji atau klik <strong>"Jalankan 8 Pertanyaan Uji Standar"</strong>.</td></tr>`;
            updateTestingSummary([]);
            return;
        }

        updateTestingSummary(data.tests);
        tbody.innerHTML = '';

        data.tests.forEach((t) => {
            const topKbs = Array.isArray(t.top_kb_results) ? t.top_kb_results : [];
            const topKbHtml = topKbs.length > 0
                ? topKbs.slice(0, 3).map((k, i) => `<div style="margin-bottom:3px;"><span class="badge badge-blue">#${i + 1} (${k.relevance_score})</span> ${escapeHtml(k.title)}</div>`).join('')
                : `<span class="badge badge-amber">0 KB (Ditolak Anti-Halusinasi)</span>`;

            let gradeBadge = `<span class="badge badge-amber">Belum Dinilai</span>`;
            if (t.is_accurate === true) gradeBadge = `<span class="badge badge-green">&check; Akurat</span>`;
            if (t.is_accurate === false) gradeBadge = `<span class="badge badge-red">&times; Tidak Akurat</span>`;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    <strong>${escapeHtml(t.test_question)}</strong>
                    <div style="font-size:0.75rem;color:#64748b;margin-top:3px;">
                        Ekspektasi: <strong>${escapeHtml(t.expected_topic || '-')}</strong> &bull; Normalisasi: <code>${escapeHtml(t.normalized_query || '-')}</code>
                    </div>
                </td>
                <td style="font-size:0.8rem;">${topKbHtml}</td>
                <td>
                    <div style="max-height:105px;overflow-y:auto;white-space:pre-wrap;font-size:0.8rem;background:#f8fafc;padding:6px;border-radius:6px;border:1px solid #e2e8f0;">${escapeHtml(t.ai_answer || '')}</div>
                </td>
                <td>${gradeBadge}</td>
                <td>
                    <div style="display:flex;gap:4px;flex-wrap:wrap;">
                        <button type="button" class="btn btn-sm btn-success" onclick="gradeTest(${t.id}, true)">Akurat</button>
                        <button type="button" class="btn btn-sm btn-danger" onclick="gradeTest(${t.id}, false)">Tidak</button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="deleteTest(${t.id})">Hapus</button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (e) {
        tbody.innerHTML = `<tr><td colspan="5" style="color:#b91c1c;">Gagal memuat pengujian: ${escapeHtml(e.message)}</td></tr>`;
    }
}

function updateTestingSummary(tests) {
    const total = tests.length;
    const graded = tests.filter(t => t.is_accurate !== null).length;
    const accurate = tests.filter(t => t.is_accurate === true).length;
    const accPct = graded > 0 ? ((accurate / graded) * 100).toFixed(1) : '0.0';

    const el = document.getElementById('testSummaryBanner');
    if (el) {
        el.innerHTML = `
            Total Pertanyaan Diuji: <strong>${total}</strong> &nbsp;&bull;&nbsp;
            Sudah Dinilai: <strong>${graded}</strong> &nbsp;&bull;&nbsp;
            Jawaban Akurat: <strong>${accurate}</strong> &nbsp;&bull;&nbsp;
            Skor Akurasi Prototype: <span class="badge badge-green">${accPct}%</span>
        `;
    }
}

async function runSingleCustomTest() {
    const qInput = document.getElementById('testQuestionInput');
    const expInput = document.getElementById('testExpectedInput');
    const btn = document.getElementById('btnRunSingleTest');

    const question = qInput.value.trim();
    const expected = expInput.value.trim();
    if (!question) return;

    btn.disabled = true;
    btn.textContent = 'Menguji...';

    try {
        await fetch('api/test.php?action=run_single', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                test_question: question,
                expected_topic: expected
            })
        });
        qInput.value = '';
        expInput.value = '';
        await loadTestList();
        loadDashboardStats();
    } finally {
        btn.disabled = false;
        btn.textContent = 'Uji Pertanyaan';
    }
}

async function runPresetBatchTests() {
    const btn = document.getElementById('btnRunBatchTest');
    btn.disabled = true;
    btn.textContent = 'Menjalankan 8 Pengujian...';

    try {
        const seedRes = await fetch('api/test.php?action=seed_questions');
        const seedData = await seedRes.json();
        const questions = seedData.questions || [];

        for (let i = 0; i < questions.length; i++) {
            btn.textContent = `Menguji (${i + 1}/${questions.length})...`;
            await fetch('api/test.php?action=run_single', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(questions[i])
            });
        }
        await loadTestList();
        loadDashboardStats();
    } finally {
        btn.disabled = false;
        btn.textContent = 'Jalankan 8 Pertanyaan Uji Standar';
    }
}

async function gradeTest(id, isAccurate) {
    await fetch('api/test.php?action=grade', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, is_accurate: isAccurate })
    });
    loadTestList();
    loadDashboardStats();
}

async function deleteTest(id) {
    await fetch('api/test.php?action=delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    loadTestList();
    loadDashboardStats();
}

/* =========================================================
   4. HALAMAN /dashboard (DashboardCard)
   ========================================================= */
async function loadDashboardStats() {
    try {
        const res = await fetch('api/stats.php');
        const d = await res.json();
        if (!d.ok) return;

        document.getElementById('statTotalKb').textContent = (d.total_kb || 0).toLocaleString('id-ID');
        document.getElementById('statTotalChats').textContent = (d.total_chats || 0).toLocaleString('id-ID');
        document.getElementById('statTotalTests').textContent = `${d.accurate_tests || 0} / ${d.graded_tests || 0}`;
        document.getElementById('statAccuracy').textContent = `${d.accuracy_percent || 0}%`;
        document.getElementById('statAvgTime').textContent = `${d.avg_response_ms || 0} ms`;

        setPocCheck('poc1', d.total_kb >= 2000);
        setPocCheck('poc2', d.total_chats > 0 || d.total_tests > 0);
        setPocCheck('poc3', d.total_chats > 0 || d.total_tests > 0);
        setPocCheck('poc4', d.answered_chats > 0 || d.total_tests > 0);
        setPocCheck('poc5', true);
        setPocCheck('poc6', d.total_tests > 0);
        setPocCheck('poc7', d.graded_tests > 0);

        const unBody = document.getElementById('unansweredTableBody');
        if (unBody) {
            const logs = d.unanswered_logs || [];
            if (logs.length === 0) {
                unBody.innerHTML = `<tr><td colspan="3" style="text-align:center;color:#64748b;">Belum ada pertanyaan yang ditolak (semua pertanyaan dalam cakupan KB berhasil terjawab).</td></tr>`;
            } else {
                unBody.innerHTML = logs.map(item => `
                    <tr>
                        <td><strong>${escapeHtml(item.user_query)}</strong></td>
                        <td><code>${escapeHtml(item.normalized_query || '-')}</code></td>
                        <td style="font-size:0.78rem;color:#64748b;">${escapeHtml((item.created_at || '').replace('T', ' ').substring(0, 19))}</td>
                    </tr>
                `).join('');
            }
        }
    } catch (e) {
        console.error(e);
    }
}

function setPocCheck(id, passed) {
    const el = document.getElementById(id);
    if (!el) return;
    el.className = 'badge ' + (passed ? 'badge-green' : 'badge-amber');
    el.textContent = passed ? 'Terbukti ✓' : 'Siap Diuji';
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
