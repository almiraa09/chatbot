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

const FRIENDLY_OPENERS = [
    'Tentu, saya bantu informasinya ya.',
    'Baik, saya bantu cek informasinya.',
    'Tentu, berikut informasinya.',
    'Baik, untuk informasi tersebut berikut detailnya.',
    'Silakan, berikut informasi yang tersedia.',
    'Tentu, saya bantu cek ya.'
];

const NO_KB_MESSAGES = [
    'Maaf ya, informasi tersebut belum tersedia di data yang saya miliki.',
    'Maaf, untuk informasi tersebut saat ini belum tersedia di layanan informasi Kabupaten Purworejo.'
];

function hashQuestion(str) {
    let h = 0;
    for (let i = 0; i < str.length; i++) {
        h = (h * 31 + str.charCodeAt(i)) >>> 0;
    }
    return h;
}

function sanitizeMetadataLeaks(text) {
    return String(text || '')
        .replace(/^Berdasarkan Knowledge Base[^:\n]*:\s*/i, '')
        .replace(/\(Kategori:\s*[^)]+\)\s*:?/gi, '')
        .replace(/^(Kategori|KB ID|Source Ref|Entity|Confidence|Record)\s*:.*$/gim, '')
        .replace(/^#\s*KNOWLEDGE BASE\s+/gim, '')
        .replace(/\n{3,}/g, '\n\n')
        .trim();
}

function formatNaturalFallback(question, kb) {
    const opener = FRIENDLY_OPENERS[hashQuestion(question) % FRIENDLY_OPENERS.length];
    const title = String(kb.title || '').trim();
    let cleanBody = String(kb.answer || '').trim()
        .replace(/^#\s*KNOWLEDGE BASE[^\n]*\n+/i, '')
        .replace(/^\*Berikut informasi terkait pertanyaan Anda:\*\s*\n*/i, '');

    const lines = cleanBody.split('\n');
    if (lines.length > 1) {
        const firstPlain = lines[0].replace(/[^\p{L}\p{N}\s]/gu, '').trim().toLowerCase();
        const titlePlain = title.replace(/[^\p{L}\p{N}\s]/gu, '').trim().toLowerCase();
        if (firstPlain && titlePlain && firstPlain === titlePlain) {
            lines.shift();
        }
    }
    const bodyText = lines.join('\n').trim();
    return `${opener}\n\n${bodyText}\n\nSemoga informasinya membantu ya.`;
}

async function generateAnswerWithAI(question, topKBs) {
    if (!topKBs || topKBs.length === 0) {
        const msg = NO_KB_MESSAGES[hashQuestion(question) % NO_KB_MESSAGES.length];
        return { answer: msg, has_answer: false, mode: 'strict_gatekeeper_no_kb' };
    }

    const contextBlocks = topKBs.map((kb, idx) =>
        `[Referensi Internal #${idx + 1} - ${kb.title}]\n${kb.answer}`
    ).join('\n\n');

    const systemInstruction = `Anda adalah petugas informasi digital Kabupaten Purworejo yang ramah, sopan, natural, humanis, ringkas, dan membantu masyarakat.

ATURAN SUMBER DATA (ANTI-HALUSINASI):
1. Gunakan HANYA fakta dari Referensi Internal yang diberikan di bawah.
2. JANGAN mengubah nama instansi, alamat, nomor telepon, website, jadwal, angka, URL, data statistik, atau ketentuan yang tertulis di referensi.
3. Jika Referensi Internal sama sekali tidak memuat jawaban untuk pertanyaan pengguna, jawab dengan ramah:
"Maaf ya, informasi tersebut belum tersedia di data yang saya miliki."
4. Jika pertanyaan pengguna terlalu singkat/ambigu sehingga bisa bermakna ganda, tanyakan klarifikasi singkat dengan ramah.
5. Jika pertanyaan pengguna majemuk (menanyakan beberapa hal sekaligus), jawab semua bagian yang datanya tersedia.

ATURAN GAYA BAHASA & LARANGAN METADATA (SANGAT PENTING):
1. Gunakan gaya percakapan yang ramah, natural, dan mudah dipahami masyarakat umum.
2. Awali dengan sapaan/pengantar pendek yang bervariasi dan natural (misal: "Tentu, saya bantu informasinya ya.", "Baik, saya bantu cek ya.", "Tentu, berikut informasinya.", "Silakan, berikut informasi yang tersedia.").
3. DILARANG KERAS menampilkan identitas/metadata database kepada pengguna seperti:
   - "Berdasarkan Knowledge Base..."
   - "Sumber: Knowledge Base..."
   - "Kategori: ..."
   - "KB ID: ..." / "Entity: ..." / "Confidence: ..." / "Record: ..."
   - "Berdasarkan uraian yang terdapat dalam..."
4. Jangan menjadikan judul dokumen KB sebagai pembuka yang kaku. Langsung masuk ke jawaban setelah kalimat pengantar pendek.
5. Jika pertanyaan sederhana (misal hanya tanya nomor telepon atau pengertian singkat), jawab secara ringkas dan langsung ke intinya.
6. Jika pertanyaan membutuhkan detail lengkap (misal syarat, alur, atau profil layanan), susun dengan poin/ikon yang rapi dan mudah dibaca, lalu boleh ditutup singkat seperti "Semoga informasinya membantu ya."`;

    const userPrompt = `Pertanyaan Masyarakat:\n"${question}"\n\nReferensi Internal (Gunakan di belakang layar, JANGAN sebutkan istilah Knowledge Base/Kategori/ID di jawaban):\n${contextBlocks}\n\nBerikan jawaban percakapan yang ramah, natural, dan akurat:`;

    const payload = {
        systemInstruction: { parts: [{ text: systemInstruction }] },
        contents: [{ role: 'user', parts: [{ text: userPrompt }] }],
        generationConfig: { temperature: 0.2, maxOutputTokens: 1024 }
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
                    const reply = sanitizeMetadataLeaks(text);
                    const isNoAns = reply.toLowerCase().includes('belum tersedia di');
                    return { answer: reply, has_answer: !isNoAns, mode: 'gemini_llm' };
                }
            }
        } catch (_) {}
    }

    const best = topKBs[0];
    return {
        answer: formatNaturalFallback(question, best),
        has_answer: true,
        mode: 'natural_conversational_fallback'
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
