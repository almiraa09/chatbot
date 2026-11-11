<?php
/**
 * api/stats.php
 * Endpoint Ringkasan Metrik untuk Halaman 4: Dashboard PoC
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/SupabaseClient.php';

$db = new SupabaseClient();

// 1. Total Knowledge Base di Database Supabase
$totalKb     = $db->count('knowledge_base');
$activeKb    = $db->count('knowledge_base', 'is_active=eq.true');

// 2. Statistik Chat Logs
$totalChats  = $db->count('chat_logs');
$answeredChats = $db->count('chat_logs', 'has_answer=eq.true');

// 3. Statistik Pengujian (Testing & Akurasi)
$testsRes    = $db->request('GET', 'test_evaluations?select=id,is_accurate,has_answer,tested_at&order=id.desc&limit=200');
$tests       = ($testsRes['ok'] && is_array($testsRes['data'])) ? $testsRes['data'] : [];

$totalTests    = count($tests);
$gradedTests   = 0;
$accurateCount = 0;
$inaccurateCount = 0;

foreach ($tests as $t) {
    if ($t['is_accurate'] !== null) {
        $gradedTests++;
        if ($t['is_accurate'] === true) {
            $accurateCount++;
        } else {
            $inaccurateCount++;
        }
    }
}

$accuracyRate = $gradedTests > 0 ? round(($accurateCount / $gradedTests) * 100, 1) : 0.0;

// 4. Rata-rata waktu respons dari 20 chat terakhir
$recentChatsRes = $db->request('GET', 'chat_logs?select=response_time_ms&order=id.desc&limit=20');
$recentChats    = ($recentChatsRes['ok'] && is_array($recentChatsRes['data'])) ? $recentChatsRes['data'] : [];
$avgResponseMs  = 0;
if (!empty($recentChats)) {
    $sumMs = array_sum(array_column($recentChats, 'response_time_ms'));
    $avgResponseMs = (int) round($sumMs / count($recentChats));
}

// 5. Daftar 10 Pertanyaan yang Belum Terjawab (Gap Knowledge Base untuk pengayaan sinonim/konten)
$unansweredRes  = $db->request('GET', 'chat_logs?select=id,user_query,normalized_query,created_at&has_answer=eq.false&order=id.desc&limit=10');
$unansweredLogs = ($unansweredRes['ok'] && is_array($unansweredRes['data'])) ? $unansweredRes['data'] : [];

echo json_encode([
    'ok'                 => true,
    'total_kb'           => $totalKb,
    'active_kb'          => $activeKb,
    'total_chats'        => $totalChats,
    'answered_chats'     => $answeredChats,
    'unanswered_chats'   => max(0, $totalChats - $answeredChats),
    'unanswered_logs'    => $unansweredLogs,
    'total_tests'        => $totalTests,
    'graded_tests'       => $gradedTests,
    'accurate_tests'     => $accurateCount,
    'inaccurate_tests'   => $inaccurateCount,
    'accuracy_percent'   => $accuracyRate,
    'avg_response_ms'    => $avgResponseMs
]);
