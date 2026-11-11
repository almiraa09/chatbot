<?php
/**
 * api/kb.php
 * Endpoint manajemen Knowledge Base:
 * - GET  ?action=list&q=...&category=...&page=1&limit=20  (Daftar & cari KB di Supabase)
 * - GET  ?action=file_info                                (Cek status file JSON lokal)
 * - POST ?action=import                                   (Import 2.229 record dari JSON ke Supabase)
 */

header('Content-Type: application/json; charset=utf-8');
set_time_limit(300);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../core/SupabaseClient.php';

$db     = new SupabaseClient();
$action = $_GET['action'] ?? 'list';

/**
 * Cari lokasi file JSON (di folder data/ proyek atau di folder Downloads)
 */
function resolveJsonFilePath(): ?string
{
    if (file_exists(KB_JSON_PATH)) {
        return KB_JSON_PATH;
    }
    if (file_exists(KB_FALLBACK_PATH)) {
        // Otomatis salin ke folder data/ proyek jika memungkinkan
        $dataDir = dirname(KB_JSON_PATH);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        @copy(KB_FALLBACK_PATH, KB_JSON_PATH);
        return file_exists(KB_JSON_PATH) ? KB_JSON_PATH : KB_FALLBACK_PATH;
    }
    return null;
}

if ($action === 'file_info') {
    $filePath = resolveJsonFilePath();
    $dbCount  = $db->count('knowledge_base');

    if (!$filePath) {
        echo json_encode([
            'ok'        => false,
            'file_found'=> false,
            'db_count'  => $dbCount,
            'message'   => 'File knowledge-base-2026-11-10.json belum ditemukan.'
        ]);
        exit;
    }

    $raw  = file_get_contents($filePath);
    $json = json_decode($raw, true);

    echo json_encode([
        'ok'          => true,
        'file_found'  => true,
        'file_path'   => basename($filePath),
        'json_count'  => $json['count'] ?? count($json['rows'] ?? []),
        'exported_at' => $json['exportedAt'] ?? null,
        'categories'  => $json['categories'] ?? [],
        'db_count'    => $dbCount
    ]);
    exit;
}

if ($action === 'import') {
    $filePath = resolveJsonFilePath();

    // Dukungan upload file JSON langsung dari browser jika diperlukan
    if (!$filePath && isset($_FILES['kb_file']['tmp_name'])) {
        $dataDir = dirname(KB_JSON_PATH);
        if (!is_dir($dataDir)) {
            @mkdir($dataDir, 0777, true);
        }
        move_uploaded_file($_FILES['kb_file']['tmp_name'], KB_JSON_PATH);
        $filePath = KB_JSON_PATH;
    }

    if (!$filePath || !file_exists($filePath)) {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => 'File knowledge-base-2026-11-10.json tidak ditemukan di folder data/ maupun Downloads.'
        ]);
        exit;
    }

    $raw  = file_get_contents($filePath);
    $json = json_decode($raw, true);
    $rows = $json['rows'] ?? [];

    if (empty($rows)) {
        http_response_code(400);
        echo json_encode([
            'ok'    => false,
            'error' => 'Format file JSON tidak valid atau properti rows kosong.'
        ]);
        exit;
    }

    // Siapkan batch per 250 record untuk di-upsert ke Supabase
    $batchSize     = 250;
    $chunks        = array_chunk($rows, $batchSize);
    $importedCount = 0;
    $errors        = [];

    foreach ($chunks as $chunkIndex => $chunk) {
        $formattedBatch = [];
        foreach ($chunk as $item) {
            $formattedBatch[] = [
                'id'           => (string) ($item['id'] ?? uniqid('kb_')),
                'category'     => $item['category'] ?? 'Umum',
                'title'        => $item['title'] ?? '',
                'answer'       => $item['answer'] ?? '',
                'keywords'     => is_array($item['keywords'] ?? null) ? array_values($item['keywords']) : [],
                'aliases'      => is_array($item['aliases'] ?? null) ? array_values($item['aliases']) : [],
                'entity'       => $item['entity'] ?? null,
                'source_ref'   => $item['source_ref'] ?? null,
                'agencies'     => is_array($item['agencies'] ?? null) ? array_values($item['agencies']) : [],
                'agency_level' => $item['agency_level'] ?? null,
                'is_active'    => isset($item['is_active']) ? (bool) $item['is_active'] : true,
                'created_at'   => $item['created_at'] ?? date('c'),
                'updated_at'   => $item['updated_at'] ?? date('c'),
            ];
        }

        $res = $db->upsertBatch('knowledge_base', $formattedBatch, 'id');
        if ($res['ok']) {
            $importedCount += count($formattedBatch);
        } else {
            $errors[] = "Batch #" . ($chunkIndex + 1) . ": " . ($res['error'] ?? 'Unknown error');
            break;
        }
    }

    $totalInDb = $db->count('knowledge_base');

    echo json_encode([
        'ok'             => empty($errors),
        'imported_count' => $importedCount,
        'total_json'     => count($rows),
        'total_in_db'    => $totalInDb,
        'errors'         => $errors
    ]);
    exit;
}

// Default: action === 'list'
$q        = trim($_GET['q'] ?? '');
$category = trim($_GET['category'] ?? '');
$page     = max(1, (int) ($_GET['page'] ?? 1));
$limit    = max(1, min(100, (int) ($_GET['limit'] ?? 15)));
$offset   = ($page - 1) * $limit;

$queryParts = [
    'select=id,category,title,answer,keywords,source_ref,is_active,updated_at',
    'order=title.asc',
    "limit={$limit}",
    "offset={$offset}"
];

if ($category !== '') {
    $queryParts[] = 'category=eq.' . urlencode($category);
}

if ($q !== '') {
    $safeQ        = urlencode($q);
    $queryParts[] = "or=(title.ilike.*{$safeQ}*,answer.ilike.*{$safeQ}*,category.ilike.*{$safeQ}*)";
}

$endpoint = 'knowledge_base?' . implode('&', $queryParts);
$res      = $db->request('GET', $endpoint, null, ['Prefer: count=exact']);

echo json_encode([
    'ok'    => $res['ok'],
    'page'  => $page,
    'limit' => $limit,
    'total' => $res['count'] ?? 0,
    'rows'  => $res['ok'] && is_array($res['data']) ? $res['data'] : [],
    'error' => $res['error']
]);
