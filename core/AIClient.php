<?php
/**
 * AIClient.php
 * Tahap AI / LLM -> JAWABAN -> SUMBER KNOWLEDGE BASE
 * Prinsip Utama:
 * 1. Knowledge Base = sumber kebenaran utama (di belakang layar).
 * 2. AI = petugas informasi digital yang ramah, sopan, natural, humanis, ringkas, dan informatif.
 * 3. Dilarang menampilkan metadata internal KB ("Berdasarkan Knowledge Base...", "Kategori:", "KB ID:", dll.) ke pengguna.
 * 4. Dilarang mengarang informasi jika tidak ada di KB.
 */

require_once __DIR__ . '/../config/config.php';

class AIClient
{
    private string $apiKey;
    private string $model;

    private static array $friendlyOpeners = [
        'Tentu, saya bantu informasinya ya.',
        'Baik, saya bantu cek informasinya.',
        'Tentu, berikut informasinya.',
        'Baik, untuk informasi tersebut berikut detailnya.',
        'Silakan, berikut informasi yang tersedia.',
        'Tentu, saya bantu cek ya.'
    ];

    private static array $noKbMessages = [
        'Maaf ya, informasi tersebut belum tersedia di data yang saya miliki.',
        'Maaf, untuk informasi tersebut saat ini belum tersedia di layanan informasi Kabupaten Purworejo.'
    ];

    public function __construct()
    {
        $this->apiKey = AI_API_KEY;
        $this->model  = AI_MODEL;
    }

    /**
     * Menyusun jawaban percakapan natural dari Top 3-5 Knowledge Base
     */
    public function generateAnswer(string $userQuestion, array $topKBs): array
    {
        // GATEKEEPER 1: Jika Top KB kosong, kembalikan pesan ramah tanpa mengarang
        if (empty($topKBs)) {
            $idx = abs(crc32($userQuestion)) % count(self::$noKbMessages);
            return [
                'answer'     => self::$noKbMessages[$idx],
                'has_answer' => false,
                'used_ai'    => false,
                'mode'       => 'strict_gatekeeper_no_kb'
            ];
        }

        // Susun konteks internal untuk AI (hanya isi fakta, tanpa menyuruh AI menyebut metadata KB)
        $contextBlocks = [];
        foreach ($topKBs as $idx => $kb) {
            $num    = $idx + 1;
            $title  = $kb['title'] ?? '';
            $answer = $kb['answer'] ?? '';
            $contextBlocks[] = "[Referensi Internal #{$num} - {$title}]\n{$answer}";
        }
        $kbContextText = implode("\n\n", $contextBlocks);

        // System Prompt: Gaya Petugas Informasi Digital yang Ramah & Natural
        $systemInstruction = <<<PROMPT
Anda adalah petugas informasi digital Kabupaten Purworejo yang ramah, sopan, natural, humanis, ringkas, dan membantu masyarakat.

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
5. Jika pertanyaan sederhana (misal hanya tanya nomor telepon atau pengertian singkat), jawab secara ringkas dan langsung ke intinya tanpa menyalin seluruh isi dokumen yang tidak ditanyakan.
6. Jika pertanyaan membutuhkan detail lengkap (misal syarat, alur, atau profil layanan), susun dengan poin/ikon yang rapi dan mudah dibaca, lalu boleh ditutup singkat seperti "Semoga informasinya membantu ya."
PROMPT;

        $userPrompt = "Pertanyaan Masyarakat:\n\"{$userQuestion}\"\n\n"
                    . "Referensi Internal (Gunakan di belakang layar, JANGAN sebutkan istilah Knowledge Base/Kategori/ID di jawaban):\n{$kbContextText}\n\n"
                    . "Berikan jawaban percakapan yang ramah, natural, dan akurat:";

        $aiResult = $this->callGeminiAPI($systemInstruction, $userPrompt);

        if ($aiResult['ok'] && !empty($aiResult['text'])) {
            $replyText  = $this->sanitizeMetadataLeaks(trim($aiResult['text']));
            $isNoAnswer = (
                stripos($replyText, 'belum tersedia di data yang saya miliki') !== false ||
                stripos($replyText, 'belum tersedia di') !== false
            );

            return [
                'answer'     => $replyText,
                'has_answer' => !$isNoAnswer,
                'used_ai'    => true,
                'mode'       => 'gemini_llm'
            ];
        }

        // Fallback Natural & Humanis (Jika API LLM tidak terjangkau, format secara percakapan ramah tanpa metadata KB)
        $bestKb      = $topKBs[0];
        $humanReply  = $this->formatNaturalFallback($userQuestion, $bestKb);

        return [
            'answer'     => $humanReply,
            'has_answer' => true,
            'used_ai'    => false,
            'mode'       => 'natural_conversational_fallback',
            'ai_notice'  => $aiResult['error'] ?? null
        ];
    }

    /**
     * Membersihkan jika ada kebocoran frasa metadata internal pada teks jawaban
     */
    private function sanitizeMetadataLeaks(string $text): string
    {
        $patterns = [
            '/^Berdasarkan Knowledge Base[^:\n]*:\s*/iu',
            '/\(Kategori:\s*[^)]+\)\s*:?/iu',
            '/^(Kategori|KB ID|Source Ref|Entity|Confidence|Record)\s*:.*$/imu',
            '/^#\s*KNOWLEDGE BASE\s+/imu',
        ];
        $cleaned = preg_replace($patterns, '', $text);
        return trim(preg_replace("/\n{3,}/", "\n\n", $cleaned));
    }

    /**
     * Menyusun respons percakapan yang ramah & natural dari data KB (tanpa menyebut "Berdasarkan Knowledge Base" / "Kategori")
     */
    private function formatNaturalFallback(string $userQuestion, array $kb): string
    {
        $openerIdx = abs(crc32($userQuestion)) % count(self::$friendlyOpeners);
        $opener    = self::$friendlyOpeners[$openerIdx];

        $rawAnswer = trim($kb['answer'] ?? '');
        $title     = trim($kb['title'] ?? '');

        // Bersihkan header markdown internal seperti "# KNOWLEDGE BASE ..." atau "*Berikut informasi terkait pertanyaan Anda:*"
        $cleanBody = preg_replace('/^#\s*KNOWLEDGE BASE[^\n]*\n+/iu', '', $rawAnswer);
        $cleanBody = preg_replace('/^\*Berikut informasi terkait pertanyaan Anda:\*\s*\n*/iu', '', $cleanBody);

        // Hilangkan baris pertama jika persis mengulang judul KB secara kaku dengan tanda bintang/emoji
        $lines = explode("\n", trim($cleanBody));
        if (count($lines) > 1) {
            $firstLinePlain = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $lines[0]));
            $titlePlain     = trim(preg_replace('/[^\p{L}\p{N}\s]/u', '', $title));
            if ($firstLinePlain !== '' && $titlePlain !== '' && mb_strtolower($firstLinePlain) === mb_strtolower($titlePlain)) {
                array_shift($lines);
            }
        }

        $bodyText = trim(implode("\n", $lines));

        // Jika pertanyaan spesifik (misal hanya menanyakan alamat atau telepon), tetap sajikan dengan pengantar natural
        return "{$opener}\n\n{$bodyText}\n\nSemoga informasinya membantu ya.";
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
                'temperature'     => 0.2,
                'maxOutputTokens' => 1024
            ]
        ];

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
