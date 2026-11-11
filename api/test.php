<?php
/**
 * api/test.php
 * Endpoint Pengujian & Penilaian Akurasi Prototype (Poin 6 & 7 PoC):
 * - GET  ?action=list        : Daftar semua hasil pengujian
 * - POST ?action=seed        : Muat daftar pertanyaan uji standar Kabupaten Purworejo (positif & negatif/anti-halusinasi)
 * - POST ?action=run_single  : Uji 1 pertanyaan & simpan hasilnya
 * - POST ?action=grade       : Nilai hasil uji (Akurat / Tidak Akurat)
 * - POST ?action=delete      : Hapus 1 baris uji
 */

header('Content-Type: application/json; charset=utf-8');
set_time_limit(180);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/SupabaseClient.php';
require_once __DIR__ . '/../core/Normalizer.php';
require_once __DIR__ . '/../core/SearchEngine.php';
require_once __DIR__ . '/../core/AIClient.php';

$db     = new SupabaseClient();
$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    $res = $db->request('GET', 'test_evaluations?select=*&order=id.desc&limit=100');
    echo json_encode([
        'ok'    => $res['ok'],
        'tests' => $res['ok'] && is_array($res['data']) ? $res['data'] : [],
        'error' => $res['error']
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$body     = json_decode($rawInput, true) ?? $_POST;

if ($action === 'run_single') {
    $question      = trim($body['test_question'] ?? '');
    $expectedTopic = trim($body['expected_topic'] ?? '');
    $testId        = isset($body['id']) ? (int) $body['id'] : null;

    if ($question === '') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Pertanyaan uji wajib diisi.']);
        exit;
    }

    // Jalankan alur: Normalisasi -> Search KB -> Relevance Scoring -> Top 3-5 -> AI
    $norm   = Normalizer::normalize($question);
    $search = new SearchEngine($db);
    $topKBs = $search->searchTopKB($norm, TOP_KB_LIMIT);

    $ai       = new AIClient();
    $aiOutput = $ai->generateAnswer($question, $topKBs);

    // Evaluasi otomatis awal:
    // - Jika ekspektasi adalah "TIDAK ADA DI KB" dan sistem menolak mengarang (has_answer == false), maka otomatis Akurat (true)
    // - Jika ekspektasi topik cocok dengan judul/kategori Top KB, beri rekomendasi Akurat (true)
    $autoGrade = null;
    if ($expectedTopic !== '') {
        $expLower = mb_strtolower($expectedTopic, 'UTF-8');
        if (str_contains($expLower, 'tidak ada') || str_contains($expLower, 'out of topic')) {
            $autoGrade = ($aiOutput['has_answer'] === false);
        } elseif (!empty($topKBs)) {
            foreach ($topKBs as $kb) {
                $haystack = mb_strtolower(($kb['title'] ?? '') . ' ' . ($kb['category'] ?? ''), 'UTF-8');
                if (str_contains($haystack, $expLower)) {
                    $autoGrade = true;
                    break;
                }
            }
        }
    }

    $recordData = [
        'test_question'    => $question,
        'expected_topic'   => $expectedTopic,
        'normalized_query' => $norm['normalized'],
        'top_kb_results'   => $topKBs,
        'ai_answer'        => $aiOutput['answer'],
        'has_answer'       => $aiOutput['has_answer'],
        'is_accurate'      => $autoGrade,
        'tested_at'        => date('c')
    ];

    if ($testId) {
        $res = $db->update('test_evaluations', 'id=eq.' . $testId, $recordData);
    } else {
        $res = $db->insert('test_evaluations', $recordData);
    }

    echo json_encode([
        'ok'     => $res['ok'],
        'result' => $res['data'][0] ?? $recordData,
        'error'  => $res['error']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'grade') {
    $id         = (int) ($body['id'] ?? 0);
    $isAccurate = $body['is_accurate'] ?? null; // true / false
    $notes      = trim($body['notes'] ?? '');

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'ID pengujian tidak valid.']);
        exit;
    }

    $res = $db->update('test_evaluations', 'id=eq.' . $id, [
        'is_accurate' => $isAccurate === null ? null : (bool) $isAccurate,
        'notes'       => $notes
    ]);

    echo json_encode([
        'ok'      => $res['ok'],
        'updated' => $res['data'][0] ?? null,
        'error'   => $res['error']
    ]);
    exit;
}

if ($action === 'delete') {
    $id  = (int) ($body['id'] ?? 0);
    $res = $db->request('DELETE', 'test_evaluations?id=eq.' . $id);
    echo json_encode(['ok' => $res['ok'], 'error' => $res['error']]);
    exit;
}

if ($action === 'seed_questions') {
    // 8 Pertanyaan Uji Standar PoC (6 di dalam KB + 2 di luar KB untuk uji anti-halusinasi)
    $presetQuestions = [
        [
            'test_question'  => 'Dimana alamat dan jam pelayanan Dinas Perhubungan (Dinhub) Kabupaten Purworejo?',
            'expected_topic' => 'Dinas Perhubungan'
        ],
        [
            'test_question'  => 'Apa saja syarat pendirian badan usaha CV atau PT?',
            'expected_topic' => 'Syarat Pendirian Badan Usaha'
        ],
        [
            'test_question'  => 'Bagaimana cara mengurus dispensasi kawin di Pengadilan Agama Purworejo?',
            'expected_topic' => 'Dispensasi Kawin'
        ],
        [
            'test_question'  => 'Berapa luas wilayah dan jumlah penduduk Kecamatan Loano?',
            'expected_topic' => 'Kecamatan Loano'
        ],
        [
            'test_question'  => 'Bagaimana syarat dan cara klaim Asuransi Usaha Tani Padi (AUTP)?',
            'expected_topic' => 'Asuransi Usaha Tani Padi'
        ],
        [
            'test_question'  => 'Apa syarat donor darah dan permintaan darah di PMI Purworejo?',
            'expected_topic' => 'Donor Darah'
        ],
        [
            'test_question'  => 'Bagaimana cara membuat visa wisata ke Jepang dari stasiun kereta bawah tanah Tokyo?',
            'expected_topic' => 'TIDAK ADA DI KB (Uji Anti-Halusinasi)'
        ],
        [
            'test_question'  => 'Siapa pemenang Liga Champions Eropa tahun 2025?',
            'expected_topic' => 'TIDAK ADA DI KB (Uji Anti-Halusinasi)'
        ]
    ];

    echo json_encode([
        'ok'        => true,
        'questions' => $presetQuestions
    ]);
    exit;
}
