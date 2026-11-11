// api/chat.js — Vercel Serverless Function (Node.js)
// Alur: USER -> CHATBOT -> NORMALIZE -> SEARCH KB -> RELEVANCE SCORING -> TOP KB -> AI -> ANSWER -> SOURCE

const { SUPABASE_URL, SUPABASE_KEY, AI_API_KEY, AI_MODEL } = require('./env-loader');
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
    if (!cleaned) {
        return { original: rawQuestion, cleaned: '', normalized: '', tokens: [] };
    }
    const rawTokens = cleaned.split(' ');
    let baseTokens = rawTokens.filter(w => w.length >= 2 && !STOPWORDS.has(w));
    if (baseTokens.length === 0) {
        baseTokens = rawTokens.filter(w => w.length >= 2);
    }
    const uniqueBase = [...new Set(baseTokens)];
    const expanded = [...uniqueBase];
    for (const t of uniqueBase) {
        if (SYNONYM_MAP[t]) {
            for (const syn of SYNONYM_MAP[t]) {
                if (!expanded.includes(syn)) expanded.push(syn);
            }
        }
    }
    return {
        original: rawQuestion,
        cleaned,
        normalized: uniqueBase.join(' '),
        tokens: expanded
    };
}

async function supabaseFetch(endpoint, method = 'GET', body = null, extraHeaders = {}) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${endpoint}`, {
        method,
        headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            'Content-Type': 'application/json',
            ...extraHeaders
        },
        body: body ? JSON.stringify(body) : undefined
    });
    const text = await res.text();
    let data = null;
    try { data = JSON.parse(text); } catch { data = text; }
    return { ok: res.ok, status: res.status, data };
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

    const rpc = await supabaseFetch('rpc/search_kb_relevance', 'POST', {
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

    const fallback = await supabaseFetch(
        `knowledge_base?select=id,category,title,answer,keywords,source_ref&is_active=eq.true&or=(${orParts.join(',')})&limit=100`
    );
    if (!fallback.ok || !Array.isArray(fallback.data)) return [];

    return fallback.data
        .map(kb => ({
            ...kb,
            relevance_score: calculateScore(kb, norm.normalized, norm.tokens)
        }))
        .filter(kb => kb.relevance_score >= MIN_RELEVANCE_SCORE)
        .sort((a, b) => b.relevance_score - a.relevance_score)
        .slice(0, TOP_KB_LIMIT);
}

async function generateAnswerWithAI(question, topKBs) {
    const NO_KB_MESSAGE = 'Maaf, informasi mengenai pertanyaan Anda belum tersedia di dalam Knowledge Base Kabupaten Purworejo.';
    if (!topKBs || topKBs.length === 0) {
        return { answer: NO_KB_MESSAGE, has_answer: false, mode: 'strict_gatekeeper_no_kb' };
    }

    const contextBlocks = topKBs.map((kb, idx) =>
        `=== DOKUMEN KB #${idx + 1} ===\nID: ${kb.id}\nKategori: ${kb.category}\nJudul: ${kb.title}\nSkor: ${kb.relevance_score}\nIsi Informasi:\n${kb.answer}`
    ).join('\n\n');

    const systemInstruction = `Anda adalah Asisten Layanan Informasi Resmi Kabupaten Purworejo.
TUGAS UTAMA ANDA ADALAH MENYUSUN JAWABAN HANYA BERDASARKAN DOKUMEN KNOWLEDGE BASE YANG DIBERIKAN.
ATURAN KETAT:
1. Knowledge Base adalah SATU-SATUNYA sumber informasi Anda.
2. DILARANG KERAS mengarang informasi di luar Knowledge Base.
3. Jika dokumen Knowledge Base TIDAK memuat jawaban atas pertanyaan pengguna, Anda WAJIB menjawab persis:
"${NO_KB_MESSAGE}"`;

    const userPrompt = `PERTANYAAN PENGGUNA:\n"${question}"\n\nDAFTAR TOP KNOWLEDGE BASE:\n${contextBlocks}\n\nSusunlah jawaban berdasarkan Knowledge Base di atas:`;

    const payload = {
        systemInstruction: { parts: [{ text: systemInstruction }] },
        contents: [{ role: 'user', parts: [{ text: userPrompt }] }],
        generationConfig: { temperature: 0.0, maxOutputTokens: 1024 }
    };

    const endpoints = [
        `https://generativelanguage.googleapis.com/v1beta/models/${AI_MODEL}:generateContent?key=${encodeURIComponent(AI_API_KEY)}`,
        `https://aiplatform.googleapis.com/v1/publishers/google/models/${AI_MODEL}:generateContent?key=${encodeURIComponent(AI_API_KEY)}`
    ];

    for (const url of endpoints) {
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            if (res.ok) {
                const json = await res.json();
                const text = json?.candidates?.[0]?.content?.parts?.[0]?.text || '';
                if (text.trim()) {
                    const reply = text.trim();
                    const isNoAns = reply.toLowerCase().includes('belum tersedia di dalam knowledge base');
                    return { answer: reply, has_answer: !isNoAns, mode: 'gemini_llm' };
                }
            }
        } catch (_) {}
    }

    const best = topKBs[0];
    return {
        answer: `Berdasarkan Knowledge Base **${best.title}** (Kategori: *${best.category}*):\n\n${best.answer}`,
        has_answer: true,
        mode: 'kb_direct_synthesis'
    };
}

module.exports = async (req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');

    if (req.method === 'GET') {
        const logs = await supabaseFetch('chat_logs?select=*&order=created_at.desc&limit=20');
        return res.status(200).json({ ok: logs.ok, logs: Array.isArray(logs.data) ? logs.data : [] });
    }

    const body = typeof req.body === 'string' ? JSON.parse(req.body || '{}') : (req.body || {});
    const question = String(body.question || '').trim();
    if (!question) {
        return res.status(400).json({ ok: false, error: 'Pertanyaan tidak boleh kosong.' });
    }

    const start = Date.now();
    const norm = normalizeQuestion(question);
    const topKBs = await searchTopKB(norm);
    const aiOut = await generateAnswerWithAI(question, topKBs);
    const elapsedMs = Date.now() - start;

    await supabaseFetch('chat_logs', 'POST', {
        user_query: question,
        normalized_query: norm.normalized,
        matched_kb: topKBs,
        ai_response: aiOut.answer,
        has_answer: aiOut.has_answer,
        response_time_ms: elapsedMs
    }, { Prefer: 'return=minimal' });

    return res.status(200).json({
        ok: true,
        user_query: question,
        normalization: norm,
        top_kb_count: topKBs.length,
        sources: topKBs,
        answer: aiOut.answer,
        has_answer: aiOut.has_answer,
        generation_mode: aiOut.mode,
        response_time_ms: elapsedMs
    });
};
