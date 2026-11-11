<?php
/**
 * services/ChatbotService.php
 * Layanan utama alur:
 * USER -> CHATBOT -> NORMALIZE -> SEARCH KB -> RELEVANCE SCORING -> TOP KB -> AI -> ANSWER -> SOURCE
 */

require_once __DIR__ . '/KnowledgeBaseService.php';
require_once __DIR__ . '/../core/AIClient.php';

class ChatbotService
{
    private KnowledgeBaseService $kbService;
    private SupabaseClient $db;
    private AIClient $aiClient;

    public function __construct()
    {
        $this->kbService = new KnowledgeBaseService();
        $this->db        = getSupabaseClient();
        $this->aiClient  = new AIClient();
    }

    /**
     * Memproses pertanyaan pengguna melalui seluruh tahapan pipeline PoC
     */
    public function processQuestion(string $question): array
    {
        $startTime = microtime(true);

        // 1. NORMALIZE + 2. SEARCH KB + 3. RELEVANCE SCORING -> 4. TOP 3-5 KB
        $searchOutput = $this->kbService->findRelevantKnowledgeBase($question, TOP_KB_LIMIT);
        $norm         = $searchOutput['normalization'];
        $topKBs       = $searchOutput['results'];

        // 5. AI / LLM (Hanya menerima Top 3-5 KB relevan + Gatekeeper Anti-Halusinasi + Fallback deterministik)
        $aiOutput  = $this->aiClient->generateAnswer($question, $topKBs);
        $answer    = $aiOutput['answer'];
        $hasAnswer = $aiOutput['has_answer'];
        $mode      = $aiOutput['mode'];

        $elapsedMs = (int) round((microtime(true) - $startTime) * 1000);

        // 6. Simpan log ke Supabase
        $this->db->insert('chat_logs', [
            'user_query'       => $question,
            'normalized_query' => $norm['normalized'],
            'matched_kb'       => $topKBs,
            'ai_response'      => $answer,
            'has_answer'       => $hasAnswer,
            'response_time_ms' => $elapsedMs
        ]);

        // 7. Kembalikan ANSWER + SOURCE
        return [
            'ok'               => true,
            'user_query'       => $question,
            'normalization'    => $norm,
            'top_kb_count'     => count($topKBs),
            'sources'          => $topKBs,
            'answer'           => $answer,
            'has_answer'       => $hasAnswer,
            'generation_mode'  => $mode,
            'ai_notice'        => $aiOutput['ai_notice'] ?? null,
            'response_time_ms' => $elapsedMs
        ];
    }
}
