// api/stats.js — Vercel Serverless Function (Node.js)
const { SUPABASE_URL, SUPABASE_KEY } = require('./env-loader');

async function sbCount(table, filter = '') {
    const q = `${table}?select=id&limit=1${filter ? '&' + filter : ''}`;
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${q}`, {
        headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`,
            Prefer: 'count=exact'
        }
    });
    const range = res.headers.get('content-range') || '';
    const m = range.match(/\/(\d+)/);
    return m ? parseInt(m[1], 10) : 0;
}

async function sbGet(endpoint) {
    const res = await fetch(`${SUPABASE_URL}/rest/v1/${endpoint}`, {
        headers: {
            apikey: SUPABASE_KEY,
            Authorization: `Bearer ${SUPABASE_KEY}`
        }
    });
    return res.ok ? await res.json() : [];
}

module.exports = async (req, res) => {
    res.setHeader('Content-Type', 'application/json; charset=utf-8');

    const [totalKb, activeKb, totalChats, answeredChats, tests, recentChats, unansweredLogs] = await Promise.all([
        sbCount('knowledge_base'),
        sbCount('knowledge_base', 'is_active=eq.true'),
        sbCount('chat_logs'),
        sbCount('chat_logs', 'has_answer=eq.true'),
        sbGet('test_evaluations?select=id,is_accurate,has_answer,tested_at&order=id.desc&limit=200'),
        sbGet('chat_logs?select=response_time_ms&order=id.desc&limit=20'),
        sbGet('chat_logs?select=id,user_query,normalized_query,created_at&has_answer=eq.false&order=id.desc&limit=10')
    ]);

    let gradedTests = 0;
    let accurateCount = 0;
    let inaccurateCount = 0;

    for (const t of tests) {
        if (t.is_accurate !== null && t.is_accurate !== undefined) {
            gradedTests++;
            if (t.is_accurate === true) accurateCount++;
            else inaccurateCount++;
        }
    }

    const accuracyRate = gradedTests > 0 ? Number(((accurateCount / gradedTests) * 100).toFixed(1)) : 0.0;
    const avgResponseMs = recentChats.length > 0
        ? Math.round(recentChats.reduce((acc, c) => acc + Number(c.response_time_ms || 0), 0) / recentChats.length)
        : 0;

    return res.status(200).json({
        ok: true,
        total_kb: totalKb,
        active_kb: activeKb,
        total_chats: totalChats,
        answered_chats: answeredChats,
        unanswered_chats: Math.max(0, totalChats - answeredChats),
        unanswered_logs: unansweredLogs,
        total_tests: tests.length,
        graded_tests: gradedTests,
        accurate_tests: accurateCount,
        inaccurate_tests: inaccurateCount,
        accuracy_percent: accuracyRate,
        avg_response_ms: avgResponseMs
    });
};
