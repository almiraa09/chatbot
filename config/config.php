<?php
/**
 * Konfigurasi PoC Chatbot Knowledge Base Kabupaten Purworejo
 * Membaca kredensial secara aman dari file .env atau Environment Variables
 */

// 1. Pemuat otomatis file .env lokal (jika tersedia)
$envFilePath = __DIR__ . '/../.env';
if (file_exists($envFilePath) && is_readable($envFilePath)) {
    $lines = file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#')) {
            continue;
        }
        $parts = explode('=', $trimmed, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1], " \t\n\r\0\x0B\"'");
            if (getenv($key) === false) {
                putenv("{$key}={$val}");
                $_ENV[$key]    = $val;
                $_SERVER[$key] = $val;
            }
        }
    }
}

// 2. Konfigurasi Database Supabase (dari Environment Variable)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: ($_ENV['SUPABASE_URL'] ?? ''));
define('SUPABASE_KEY', getenv('SUPABASE_KEY') ?: ($_ENV['SUPABASE_KEY'] ?? ''));

// 3. Konfigurasi AI / LLM (dari Environment Variable, tanpa hardcode API Key)
define('AI_API_KEY', getenv('AI_API_KEY') ?: ($_ENV['AI_API_KEY'] ?? ''));
define('AI_MODEL', getenv('AI_MODEL') ?: ($_ENV['AI_MODEL'] ?? 'gemini-2.5-flash'));

// 4. Konfigurasi Pencarian & Relevance Scoring
define('TOP_KB_LIMIT', 5);
define('MIN_RELEVANCE_SCORE', 3.0);

// 5. Lokasi File Sumber Knowledge Base
define('KB_JSON_PATH', __DIR__ . '/../data/knowledge-base-2026-11-10.json');
define('KB_FALLBACK_PATH', 'C:/Users/Almira Ramadani/Downloads/knowledge-base-2026-11-10.json');
