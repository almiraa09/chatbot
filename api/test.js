// api/test.js — Vercel Serverless Function (Node.js)
const chatHandler = require('./chat.js');
const { SUPABASE_URL, SUPABASE_KEY } = require('./env-loader');

async function sbFetch(endpoint, method = 'GET', body = null) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${endpoint}`, {
        method,
        headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            'Content-Type': 'application/json',
            Prefer: 'return=representation'
        },
        body: body ? JSON.stringify(body) : undefined
    });
    const data = await res.json().catch(() => null);
    return { ok: res.ok, data };
}

module.exports = async (req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    const action = req.query.action || 'list';

    if (action === 'list') {
        const r = await sbFetch('test_evaluations?select=*&order=id.desc&limit=100');
        return res.status(200).json({ ok: r.ok, tests: Array.isArray(r.data) ? r.data : [] });
    }

    if (action === 'seed_questions') {
        return res.status(200).json({
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
        });
    }

    const body = typeof req.body === 'string' ? JSON.parse(req.body || '{}') : (req.body || {});

    if (action === 'run_single') {
        const question = String(body.test_question || '').trim();
        const expectedTopic = String(body.expected_topic || '').trim();
        if (!question) return res.status(400).json({ ok: false, error: 'Pertanyaan uji wajib diisi.' });

        let captured = null;
        const mockRes = {
            setHeader: () => {},
            status: () => ({ json: (obj) => { captured = obj; } })
        };
        await chatHandler({ method: 'POST', body: { question } }, mockRes);

        const topKBs = captured?.sources || [];
        const hasAnswer = Boolean(captured?.has_answer);
        let autoGrade = null;

        if (expectedTopic) {
            const expLower = expectedTopic.toLowerCase();
            if (expLower.includes('tidak ada') || expLower.includes('luar kb')) {
                autoGrade = !hasAnswer;
            } else if (topKBs.length > 0) {
                for (const kb of topKBs) {
                    const hay = `${kb.title || ''} ${kb.category || ''}`.toLowerCase();
                    if (hay.includes(expLower)) {
                        autoGrade = true;
                        break;
                    }
                }
            }
        }

        const record = {
            test_question: question,
            expected_topic: expectedTopic,
            normalized_query: captured?.normalization?.normalized || '',
            top_kb_results: topKBs,
            ai_answer: captured?.answer || '',
            has_answer: hasAnswer,
            is_accurate: autoGrade,
            tested_at: new Date().toISOString()
        };

        const ins = await sbFetch('test_evaluations', 'POST', record);
        return res.status(200).json({ ok: ins.ok, result: ins.data?.[0] || record });
    }

    if (action === 'grade') {
        const id = Number(body.id || 0);
        const isAccurate = body.is_accurate === null ? null : Boolean(body.is_accurate);
        const upd = await sbFetch(`test_evaluations?id=eq.${id}`, 'PATCH', { is_accurate: isAccurate });
        return res.status(200).json({ ok: upd.ok, updated: upd.data?.[0] || null });
    }

    if (action === 'delete') {
        const id = Number(body.id || 0);
        const del = await sbFetch(`test_evaluations?id=eq.${id}`, 'DELETE');
        return res.status(200).json({ ok: del.ok });
    }

    return res.status(400).json({ ok: false, error: 'Action tidak dikenali' });
};
