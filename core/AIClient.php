<?php
/**
 * AIClient.php
 * Tahap AI / LLM -> JAWABAN -> SUMBER KNOWLEDGE BASE
 * Prinsip Utama:
 * 1. Knowledge Base = sumber informasi utama.
 * 2. AI = penyusun jawaban berdasarkan Knowledge Base.
 * 3. AI tidak boleh mengarang informasi (halusinasi) jika KB tidak memiliki jawabannya.
 */

require_once __DIR__ . '/../config/config.php';

class AIClient
{
    private string $apiKey;
    private string $model;

    public const NO_KB_MESSAGE = 'Maaf, informasi mengenai pertanyaan Anda belum tersedia di dalam Knowledge Base Kabupaten Purworejo.';

    public function __construct()
    {
        $this->apiKey = AI_API_KEY;
        $this->model  = AI_MODEL;
    }

    /**
     * Menyusun jawaban dari Top 3-5 Knowledge Base
     */
    public function generateAnswer(string $userQuestion, array $topKBs): array
    {
        // GATEKEEPER 1: Jika Top KB kosong, jangan izinkan AI mengarang jawaban
        if (empty($topKBs)) {
            return [
                'answer'     => self::NO_KB_MESSAGE,
                'has_answer' => false,
                'used_ai'    => false,
                'mode'       => 'strict_gatekeeper_no_kb'
            ];
        }

        // Susun konteks hanya dari Top 3-5 KB
        $contextBlocks = [];
        foreach ($topKBs as $idx => $kb) {
            $num      = $idx + 1;
            $id       = $kb['id'] ?? '-';
            $cat      = $kb['category'] ?? 'Umum';
            $title    = $kb['title'] ?? '';
            $answer   = $kb['answer'] ?? '';
            $score    = $kb['relevance_score'] ?? 0;

            $contextBlocks[] = "=== DOKUMEN KB #{$num} ===\n"
                             . "ID: {$id}\n"
                             . "Kategori: {$cat}\n"
                             . "Judul: {$title}\n"
                             . "Skor Relevansi: {$score}\n"
                             . "Isi Informasi:\n{$answer}\n";
        }
        $kbContextText = implode("\n", $contextBlocks);

        // Prompt Sistem Ketat (Anti-Halusinasi)
        $systemInstruction = <<<PROMPT
Anda adalah Asisten Layanan Informasi Resmi Kabupaten Purworejo.
TUGAS UTAMA ANDA ADALAH MENYUSUN JAWABAN HANYA BERDASARKAN DOKUMEN KNOWLEDGE BASE YANG DIBERIKAN DI BAWAH INI.

ATURAN KETAT (WAJIB DITAATI):
1. Knowledge Base adalah SATU-SATUNYA sumber informasi Anda.
2. DILARANG KERAS mengarang, menebak, atau menambahkan informasi dari pengetahuan umum di luar teks Knowledge Base yang diberikan.
3. Jika dokumen Knowledge Base yang diberikan SAMA SEKALI TIDAK memuat jawaban atas pertanyaan pengguna, Anda WAJIB menjawab persis dengan kalimat ini saja:
"Maaf, informasi mengenai pertanyaan Anda belum tersedia di dalam Knowledge Base Kabupaten Purworejo."
4. Jika jawaban tersedia di dalam Knowledge Base, susun jawaban dengan bahasa Indonesia yang rapi, jelas, sopan, dan pertahankan detail penting seperti alamat, nomor telepon, syarat, biaya, tautan/link, dan instansi pengampu sesuai isi Knowledge Base.
PROMPT;

        $userPrompt = "PERTANYAAN PENGGUNA:\n\"{$userQuestion}\"\n\n"
                    . "DAFTAR TOP KNOWLEDGE BASE YANG DITEMUKAN:\n{$kbContextText}\n\n"
                    . "Susunlah jawaban berdasarkan Knowledge Base di atas sesuai aturan ketat:";

        // Panggil Gemini API
        $aiResult = $this->callGeminiAPI($systemInstruction, $userPrompt);

        if ($aiResult['ok'] && !empty($aiResult['text'])) {
            $replyText = trim($aiResult['text']);
            $isNoAnswer = (stripos($replyText, 'belum tersedia di dalam Knowledge Base') !== false);

            return [
                'answer'     => $replyText,
                'has_answer' => !$isNoAnswer,
                'used_ai'    => true,
                'mode'       => 'gemini_llm'
            ];
        }

        // Fallback Deterministik: Jika API Key AI memerlukan konfigurasi tambahan atau koneksi gagal,
        // tetap tampilkan sintesis langsung dari Top #1 Knowledge Base tanpa mengarang informasi
        $bestKb = $topKBs[0];
        $synthesized = "Berdasarkan Knowledge Base **{$bestKb['title']}** (Kategori: *{$bestKb['category']}*):\n\n"
                     . $bestKb['answer'];

        return [
            'answer'     => $synthesized,
            'has_answer' => true,
            'used_ai'    => false,
            'mode'       => 'kb_direct_synthesis',
            'ai_notice'  => $aiResult['error'] ?? null
        ];
    }

    /**
     * Memanggil Endpoint REST Gemini / Vertex AI
     */
    private function callGeminiAPI(string $systemInstruction, string $userPrompt): array
    {
        if (empty($this->apiKey)) {
            return ['ok' => false, 'error' => 'API Key belum diisi'];
        }

        $payload = [
            'systemInstruction' => [
                'parts' => [['text' => $systemInstruction]]
            ],
            'contents' => [
                [
                    'role'  => 'user',
                    'parts' => [['text' => $userPrompt]]
                ]
            ],
            'generationConfig' => [
                'temperature'     => 0.0,
                'maxOutputTokens' => 1024
            ]
        ];

        // Daftar endpoint kandidat (mendukung Google AI Studio key maupun Vertex AI Express key `AQ.xxx`)
        $endpoints = [
            "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey),
            "https://aiplatform.googleapis.com/v1/publishers/google/models/{$this->model}:generateContent?key=" . urlencode($this->apiKey),
        ];

        $lastError = '';
        foreach ($endpoints as $url) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
                CURLOPT_TIMEOUT        => 25,
                CURLOPT_SSL_VERIFYPEER => false,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($response === false) {
                $lastError = $curlErr;
                continue;
            }

            $decoded = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300) {
                $text = $decoded['candidates'][0]['content']['parts'][0]['text'] ?? '';
                if ($text !== '') {
                    return ['ok' => true, 'text' => $text];
                }
            }

            $lastError = $decoded['error']['message'] ?? "HTTP {$httpCode}";
        }

        return ['ok' => false, 'error' => $lastError];
    }
}
