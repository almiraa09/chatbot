<?php
/**
 * services/TestingService.php
 * Layanan untuk mengelola pengujian pertanyaan dan perhitungan akurasi prototype
 */

require_once __DIR__ . '/ChatbotService.php';

class TestingService
{
    private SupabaseClient $db;
    private ChatbotService $chatService;

    public function __construct()
    {
        $this->db          = getSupabaseClient();
        $this->chatService = new ChatbotService();
    }

    /**
     * Menjalankan 1 skenario pengujian pertanyaan terhadap Knowledge Base
     */
    public function executeTest(string $question, string $expectedTopic = '', ?int $testId = null): array
    {
        $chatResult = $this->chatService->processQuestion($question);
        $topKBs     = $chatResult['sources'];
        $hasAnswer  = $chatResult['has_answer'];

        $autoGrade = null;
        if ($expectedTopic !== '') {
            $expLower = mb_strtolower($expectedTopic, 'UTF-8');
            if (str_contains($expLower, 'tidak ada') || str_contains($expLower, 'luar kb')) {
                $autoGrade = ($hasAnswer === false);
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

        $record = [
            'test_question'    => $question,
            'expected_topic'   => $expectedTopic,
            'normalized_query' => $chatResult['normalization']['normalized'],
            'top_kb_results'   => $topKBs,
            'ai_answer'        => $chatResult['answer'],
            'has_answer'       => $hasAnswer,
            'is_accurate'      => $autoGrade,
            'tested_at'        => date('c')
        ];

        if ($testId) {
            $res = $this->db->update('test_evaluations', 'id=eq.' . $testId, $record);
        } else {
            $res = $this->db->insert('test_evaluations', $record);
        }

        return [
            'ok'     => $res['ok'],
            'result' => $res['data'][0] ?? $record,
            'error'  => $res['error']
        ];
    }
}
