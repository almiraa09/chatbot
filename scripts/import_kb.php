<?php
/**
 * scripts/import_kb.php
 * Skrip utilitas CLI untuk mengimpor 2.229 record dari knowledge-base-2026-11-10.json ke Supabase
 * Cara menjalankan di terminal: php scripts/import_kb.php
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/supabase.php';

$filePath = file_exists(KB_JSON_PATH) ? KB_JSON_PATH : (file_exists(KB_FALLBACK_PATH) ? KB_FALLBACK_PATH : null);

if (!$filePath) {
    fwrite(STDERR, "Gagal: File knowledge-base-2026-11-10.json tidak ditemukan.\n");
    exit(1);
}

$raw  = file_get_contents($filePath);
$json = json_decode($raw, true);
$rows = $json['rows'] ?? [];

echo "Memulai proses import " . count($rows) . " record Knowledge Base ke Supabase...\n";

$db     = getSupabaseClient();
$chunks = array_chunk($rows, 250);
$total  = 0;

foreach ($chunks as $idx => $chunk) {
    $batch = [];
    foreach ($chunk as $item) {
        $batch[] = [
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

    $res = $db->upsertBatch('knowledge_base', $batch, 'id');
    if (!$res['ok']) {
        fwrite(STDERR, "Gagal pada Batch #" . ($idx + 1) . ": " . ($res['error'] ?? '') . "\n");
        exit(1);
    }

    $total += count($batch);
    echo "Batch #" . ($idx + 1) . " selesai ({$total}/" . count($rows) . ")\n";
}

echo "Selesai! Total record di Supabase: " . $db->count('knowledge_base') . "\n";
