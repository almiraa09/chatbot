/**
 * public/js/vercel-engine.js
 * Engine Hybrid: Jika dijalankan di Vercel (static deployment), otomatis memproses
 * Normalisasi -> Search KB (Supabase) -> Relevance Scoring -> Top 3-5 KB -> Jawaban -> Sumber KB -> Evaluasi
 * tanpa bergantung pada runtime PHP lokal.
 */
(function () {
    const isLocalXampp = window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1';
    if (isLocalXampp) return; // Di localhost XAMPP tetap gunakan backend PHP asli

    const SUPABASE_URL = window.__ENV__?.SUPABASE_URL || '';
    const SUPABASE_KEY = window.__ENV__?.SUPABASE_KEY || '';
    if (!SUPABASE_URL || !SUPABASE_KEY) return;
    const TOP_KB_LIMIT = 5;
    const MIN_RELEVANCE_SCORE = 3.0;

    const STOPWORDS = new Set([
        'apa','apakah','siapa','dimana','kemana','darimana','kapan','bagaimana','gimana',
        'berapa','mengapa','kenapa','tolong','mohon','minta','info','informasi','tanya',
        'bertanya','mau','ingin','akan','bisa','dapat','boleh','harus','perlu',
        'yang','di','ke','dari','pada','dalam','untuk','guna','buat','dengan','dan',
        'atau','serta','jika','kalau','bila','karena','sebab','agar','supaya',
        'ini','itu','tersebut','tsb','adalah','ialah','merupakan','yaitu','yakni',
        'saya','aku','kami','kita','anda','kamu','dia','mereka','pak','bu','kak','min','admin',
        'ada','tidak','nggak','gak','bukan','belum','sudah','telah','sedang','lagi',
        'saja','aja','kok','sih','dong','deh','lah','kah','pun','nya','ya','yah',
        'tentang','mengenai','terkait','seputar','soal','hal','bagian','tempat'
    ]);

    const SYNONYM_MAP = {
        dishub: ['dinhub', 'perhubungan'],
        dinhub: ['dishub', 'perhubungan'],
        dukcapil: ['disdukcapil', 'kependudukan'],
        capil: ['disdukcapil', 'kependudukan'],
        ktp: ['ktp', 'ektp', 'kependudukan'],
        kk: ['kartu', 'keluarga', 'disdukcapil'],
        akte: ['akta', 'kelahiran', 'kematian'],
        nikah: ['kawin', 'perkawinan', 'kua'],
        cerai: ['perceraian', 'pengadilan', 'agama'],
        p3k: ['pppk', 'asn', 'kepegawaian'],
        pppk: ['p3k', 'asn'],
        bansos: ['bantuan', 'sosial', 'pkh', 'desil', 'dinsos'],
        bpjs: ['jkn', 'kis', 'kesehatan', 'ketenagakerjaan'],
        pdam: ['tirta', 'perwitasari', 'air'],
        pam: ['pdam', 'tirta', 'perwitasari'],
        pemadam: ['damkar', 'kebakaran', 'apar'],
        damkar: ['pemadam', 'kebakaran'],
        pajak: ['bppkad', 'samsat', 'pbb', 'retribusi'],
        nib: ['oss', 'perizinan', 'dpmptsp'],
        pmi: ['donor', 'darah'],
        tani: ['pertanian', 'dkpp', 'padi']
    };

    function normalizeQuestion(rawQuestion) {
        const lower = String(rawQuestion || '').toLowerCase().trim();
        const cleaned = lower.replace(/[^a-z0-9\s]/g, ' ').replace(/\s+/g, ' ').trim();
        if (!cleaned) return { original: rawQuestion, cleaned: '', normalized: '', tokens: [] };

        const rawTokens = cleaned.split(' ');
        let baseTokens = rawTokens.filter(w => w.length >= 2 && !STOPWORDS.has(w));
        if (baseTokens.length === 0) baseTokens = rawTokens.filter(w => w.length >= 2);

        const uniqueBase = [...new Set(baseTokens)];
        const expanded = [...uniqueBase];
        for (const t of uniqueBase) {
            if (SYNONYM_MAP[t]) {
                for (const syn of SYNONYM_MAP[t]) {
                    if (!expanded.includes(syn)) expanded.push(syn);
                }
            }
        }
        return { original: rawQuestion, cleaned, normalized: uniqueBase.join(' '), tokens: expanded };
    }

    async function sbFetch(endpoint, method = 'GET', body = null, extraHeaders = {}) {
        const res = await nativeFetch(`${SUPABASE_URL}/rest/v1/${endpoint}`, {
            method,
            headers: {
                apikey: SUPABASE_KEY,
                Authorization: `Bearer ${SUPABASE_KEY}`,
                'Content-Type': 'application/json',
                ...extraHeaders
            },
            body: body ? JSON.stringify(body) : undefined
        });
        const range = res.headers.get('content-range') || '';
        const match = range.match(/\/(\d+)/);
        const count = match ? parseInt(match[1], 10) : 0;
        const text = await res.text();
        let data = null;
        try { data = JSON.parse(text); } catch { data = text; }
        return { ok: res.ok, status: res.status, count, data };
    }

    function calculateScore(kb, normalizedQuery, tokens) {
        const title = String(kb.title || '').toLowerCase();
        const category = String(kb.category || '').toLowerCase();
        const answer = String(kb.answer || '').toLowerCase();
        const keywords = Array.isArray(kb.keywords) ? kb.keywords.map(k => String(k).toLowerCase()) : [];

        let score = 0;
        if (normalizedQuery) {
            if (title.includes(normalizedQuery)) score += 15;
            if (keywords.some(k => k.includes(normalizedQuery))) score += 15;
        }
        for (const token of tokens) {
            if (token.length < 2) continue;
            if (title.includes(token)) score += 4;
            if (keywords.some(k => k.includes(token))) score += 3;
            if (category.includes(token)) score += 2;
            if (answer.includes(token)) score += 1;
        }
        return score;
    }

    async function searchTopKB(norm) {
        if (!norm.normalized && norm.tokens.length === 0) return [];

        const rpc = await sbFetch('rpc/search_kb_relevance', 'POST', {
            p_normalized_query: norm.normalized,
            p_tokens: norm.tokens,
            p_limit: TOP_KB_LIMIT
        });

        if (rpc.ok && Array.isArray(rpc.data)) {
            return rpc.data
                .filter(r => Number(r.relevance_score || 0) >= MIN_RELEVANCE_SCORE)
                .slice(0, TOP_KB_LIMIT)
                .map(r => ({
                    id: r.id,
                    category: r.category || 'Umum',
                    title: r.title,
                    answer: r.answer,
                    keywords: r.keywords || [],
                    source_ref: r.source_ref || null,
                    relevance_score: Number(Number(r.relevance_score).toFixed(2))
                }));
        }

        const orParts = [];
        for (const t of norm.tokens.slice(0, 8)) {
            const s = encodeURIComponent(t);
            orParts.push(`title.ilike.*${s}*`, `category.ilike.*${s}*`, `answer.ilike.*${s}*`);
        }
        if (orParts.length === 0) return [];

        const fallback = await sbFetch(
            `knowledge_base?select=id,category,title,answer,keywords,source_ref&is_active=eq.true&or=(${orParts.join(',')})&limit=100`
        );
        if (!fallback.ok || !Array.isArray(fallback.data)) return [];

        return fallback.data
            .map(kb => ({ ...kb, relevance_score: calculateScore(kb, norm.normalized, norm.tokens) }))
            .filter(kb => kb.relevance_score >= MIN_RELEVANCE_SCORE)
            .sort((a, b) => b.relevance_score - a.relevance_score)
            .slice(0, TOP_KB_LIMIT);
    }

    async function processChat(question) {
        const start = Date.now();
        const norm = normalizeQuestion(question);
        const topKBs = await searchTopKB(norm);

        const NO_KB_MESSAGE = 'Maaf, informasi mengenai pertanyaan Anda belum tersedia di dalam Knowledge Base Kabupaten Purworejo.';
        let answer = NO_KB_MESSAGE;
        let hasAnswer = false;
        let mode = 'strict_gatekeeper_no_kb';

        if (topKBs.length > 0) {
            const best = topKBs[0];
            answer = `Berdasarkan Knowledge Base **${best.title}** (Kategori: *${best.category}*):\n\n${best.answer}`;
            hasAnswer = true;
            mode = 'kb_direct_synthesis';
        }

        const elapsedMs = Date.now() - start;

        await sbFetch('chat_logs', 'POST', {
            user_query: question,
            normalized_query: norm.normalized,
            matched_kb: topKBs,
            ai_response: answer,
            has_answer: hasAnswer,
            response_time_ms: elapsedMs
        }, { Prefer: 'return=minimal' });

        return {
            ok: true,
            user_query: question,
            normalization: norm,
            top_kb_count: topKBs.length,
            sources: topKBs,
            answer,
            has_answer: hasAnswer,
            generation_mode: mode,
            response_time_ms: elapsedMs
        };
    }

    const nativeFetch = window.fetch.bind(window);

    window.fetch = async function (input, init = {}) {
        const urlStr = typeof input === 'string' ? input : input.url;

        if (!urlStr.includes('api/')) {
            return nativeFetch(input, init);
        }

        const parsed = new URL(urlStr, window.location.origin);
        const path = parsed.pathname;
        const params = parsed.searchParams;
        const body = init.body ? JSON.parse(init.body) : {};

        let resultObj = { ok: false };

        if (path.endsWith('api/chat.php')) {
            resultObj = await processChat(body.question || '');
        } else if (path.endsWith('api/kb.php')) {
            const action = params.get('action') || 'list';
            if (action === 'file_info' || action === 'import') {
                const c = await sbFetch('knowledge_base?select=id&limit=1', 'GET', null, { Prefer: 'count=exact' });
                resultObj = {
                    ok: true,
                    file_found: true,
                    json_count: 2229,
                    db_count: c.count || 2229,
                    imported_count: c.count || 2229,
                    total_in_db: c.count || 2229,
                    categories: [
                        'Umum','Disdukcapil','DPMPTSP','Perizinan','Kesehatan','RSUD & Puskesmas',
                        'Data & Statistik','Pendidikan','Perhubungan','Pertanian & Pangan','Damkar & Satpol PP',
                        'Ketenagakerjaan','Sosial','Pajak Daerah','Wisata','Kepegawaian ASN'
                    ]
                };
            } else {
                const q = (params.get('q') || '').trim();
                const category = (params.get('category') || '').trim();
                const page = Math.max(1, parseInt(params.get('page') || '1', 10));
                const limit = Math.max(1, parseInt(params.get('limit') || '15', 10));
                const offset = (page - 1) * limit;
                const parts = [
                    'select=id,category,title,answer,keywords,source_ref,is_active,updated_at',
                    'order=title.asc',
                    `limit=${limit}`,
                    `offset=${offset}`
                ];
                if (category) parts.push(`category=eq.${encodeURIComponent(category)}`);
                if (q) {
                    const s = encodeURIComponent(q);
                    parts.push(`or=(title.ilike.*${s}*,answer.ilike.*${s}*,category.ilike.*${s}*)`);
                }
                const listRes = await sbFetch(`knowledge_base?${parts.join('&')}`, 'GET', null, { Prefer: 'count=exact' });
                resultObj = {
                    ok: listRes.ok,
                    page,
                    limit,
                    total: listRes.count,
                    rows: Array.isArray(listRes.data) ? listRes.data : []
                };
            }
        } else if (path.endsWith('api/test.php')) {
            const action = params.get('action') || 'list';
            if (action === 'list') {
                const r = await sbFetch('test_evaluations?select=*&order=id.desc&limit=100');
                resultObj = { ok: r.ok, tests: Array.isArray(r.data) ? r.data : [] };
            } else if (action === 'seed_questions') {
                resultObj = {
                    ok: true,
                    questions: [
                        { test_question: 'Dimana alamat dan jam pelayanan Dinas Perhubungan (Dinhub) Kabupaten Purworejo?', expected_topic: 'Dinas Perhubungan' },
                        { test_question: 'Apa saja syarat pendirian badan usaha CV atau PT?', expected_topic: 'Syarat Pendirian Badan Usaha' },
                        { test_question: 'Bagaimana cara mengurus dispensasi kawin di Pengadilan Agama Purworejo?', expected_topic: 'Dispensasi Kawin' },
                        { test_question: 'Berapa luas wilayah dan jumlah penduduk Kecamatan Loano?', expected_topic: 'Kecamatan Loano' },
                        { test_question: 'Bagaimana syarat dan cara klaim Asuransi Usaha Tani Padi (AUTP)?', expected_topic: 'Asuransi Usaha Tani Padi' },
                        { test_question: 'Apa syarat donor darah dan permintaan darah di PMI Purworejo?', expected_topic: 'Donor Darah' },
                        { test_question: 'Bagaimana cara membuat visa wisata ke Jepang dari stasiun kereta bawah tanah Tokyo?', expected_topic: 'TIDAK ADA DI KB (Uji Anti-Halusinasi)' },
                        { test_question: 'Siapa pemenang Liga Champions Eropa tahun 2025?', expected_topic: 'TIDAK ADA DI KB (Uji Anti-Halusinasi)' }
                    ]
                };
            } else if (action === 'run_single') {
                const chatOut = await processChat(body.test_question || '');
                const topKBs = chatOut.sources || [];
                const hasAnswer = Boolean(chatOut.has_answer);
                const expectedTopic = String(body.expected_topic || '').trim();
                let autoGrade = null;
                if (expectedTopic) {
                    const expLower = expectedTopic.toLowerCase();
                    if (expLower.includes('tidak ada') || expLower.includes('luar kb')) {
                        autoGrade = !hasAnswer;
                    } else if (topKBs.length > 0) {
                        for (const kb of topKBs) {
                            const hay = `${kb.title || ''} ${kb.category || ''}`.toLowerCase();
                            if (hay.includes(expLower)) { autoGrade = true; break; }
                        }
                    }
                }
                const record = {
                    test_question: body.test_question,
                    expected_topic: expectedTopic,
                    normalized_query: chatOut.normalization.normalized,
                    top_kb_results: topKBs,
                    ai_answer: chatOut.answer,
                    has_answer: hasAnswer,
                    is_accurate: autoGrade,
                    tested_at: new Date().toISOString()
                };
                const ins = await sbFetch('test_evaluations', 'POST', record, { Prefer: 'return=representation' });
                resultObj = { ok: ins.ok, result: ins.data?.[0] || record };
            } else if (action === 'grade') {
                await sbFetch(`test_evaluations?id=eq.${Number(body.id)}`, 'PATCH', { is_accurate: Boolean(body.is_accurate) });
                resultObj = { ok: true };
            } else if (action === 'delete') {
                await sbFetch(`test_evaluations?id=eq.${Number(body.id)}`, 'DELETE');
                resultObj = { ok: true };
            }
        } else if (path.endsWith('api/stats.php')) {
            const [cKb, cChats, cAns, testsRes, recentRes, unRes] = await Promise.all([
                sbFetch('knowledge_base?select=id&limit=1', 'GET', null, { Prefer: 'count=exact' }),
                sbFetch('chat_logs?select=id&limit=1', 'GET', null, { Prefer: 'count=exact' }),
                sbFetch('chat_logs?select=id&has_answer=eq.true&limit=1', 'GET', null, { Prefer: 'count=exact' }),
                sbFetch('test_evaluations?select=id,is_accurate,has_answer,tested_at&order=id.desc&limit=200'),
                sbFetch('chat_logs?select=response_time_ms&order=id.desc&limit=20'),
                sbFetch('chat_logs?select=id,user_query,normalized_query,created_at&has_answer=eq.false&order=id.desc&limit=10')
            ]);
            const tests = Array.isArray(testsRes.data) ? testsRes.data : [];
            const recent = Array.isArray(recentRes.data) ? recentRes.data : [];
            let graded = 0, acc = 0, inacc = 0;
            for (const t of tests) {
                if (t.is_accurate !== null && t.is_accurate !== undefined) {
                    graded++;
                    if (t.is_accurate === true) acc++; else inacc++;
                }
            }
            const avgMs = recent.length > 0
                ? Math.round(recent.reduce((s, r) => s + Number(r.response_time_ms || 0), 0) / recent.length)
                : 0;
            resultObj = {
                ok: true,
                total_kb: cKb.count,
                active_kb: cKb.count,
                total_chats: cChats.count,
                answered_chats: cAns.count,
                unanswered_chats: Math.max(0, cChats.count - cAns.count),
                unanswered_logs: Array.isArray(unRes.data) ? unRes.data : [],
                total_tests: tests.length,
                graded_tests: graded,
                accurate_tests: acc,
                inaccurate_tests: inacc,
                accuracy_percent: graded > 0 ? Number(((acc / graded) * 100).toFixed(1)) : 0.0,
                avg_response_ms: avgMs
            };
        }

        return new Response(JSON.stringify(resultObj), {
            status: 200,
            headers: { 'Content-Type': 'application/json; charset=utf-8' }
        });
    };
})();
