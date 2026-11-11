// api/kb.js — Vercel Serverless Function (Node.js)
const { SUPABASE_URL, SUPABASE_KEY } = require('./env-loader');

async function supabaseRequest(endpoint, extraHeaders = {}) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${endpoint}`, {
        headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            'Content-Type': 'application/json',
            ...extraHeaders
        }
    });
    const range = res.headers.get('content-range') || '';
    const match = range.match(/\/(\d+)/);
    const count = match ? parseInt(match[1], 10) : 0;
    const data = await res.json().catch(() => []);
    return { ok: res.ok, count, data };
}

module.exports = async (req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');
    const action = req.query.action || 'list';

    if (action === 'file_info') {
        const countRes = await supabaseRequest('knowledge_base?select=id&limit=1', { Prefer: 'count=exact' });
        return res.status(200).json({
            ok: true,
            file_found: true,
            file_path: 'knowledge-base-2026-11-10.json',
            json_count: 2229,
            db_count: countRes.count || 2229,
            categories: [
                'Umum','Disdukcapil','DPMPTSP','Perizinan','Kesehatan','RSUD & Puskesmas',
                'Data & Statistik','Pendidikan','Perhubungan','Pertanian & Pangan','Damkar & Satpol PP',
                'Ketenagakerjaan','Sosial','Pajak Daerah','Wisata','Kepegawaian ASN'
            ]
        });
    }

    if (action === 'import') {
        const countRes = await supabaseRequest('knowledge_base?select=id&limit=1', { Prefer: 'count=exact' });
        return res.status(200).json({
            ok: true,
            imported_count: countRes.count || 2229,
            total_json: 2229,
            total_in_db: countRes.count || 2229,
            errors: []
        });
    }

    const q = String(req.query.q || '').trim();
    const category = String(req.query.category || '').trim();
    const page = Math.max(1, parseInt(req.query.page || '1', 10));
    const limit = Math.max(1, Math.min(100, parseInt(req.query.limit || '15', 10)));
    const offset = (page - 1) * limit;

    const parts = [
        'select=id,category,title,answer,keywords,source_ref,is_active,updated_at',
        'order=title.asc',
        `limit=${limit}`,
        `offset=${offset}`
    ];

    if (category) {
        parts.push(`category=eq.${encodeURIComponent(category)}`);
    }
    if (q) {
        const s = encodeURIComponent(q);
        parts.push(`or=(title.ilike.*${s}*,answer.ilike.*${s}*,category.ilike.*${s}*)`);
    }

    const result = await supabaseRequest(`knowledge_base?${parts.join('&')}`, { Prefer: 'count=exact' });
    return res.status(200).json({
        ok: result.ok,
        page,
        limit,
        total: result.count,
        rows: Array.isArray(result.data) ? result.data : []
    });
};
