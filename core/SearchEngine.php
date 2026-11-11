<?php
/**
 * SearchEngine.php
 * Tahap KNOWLEDGE BASE SEARCH -> RELEVANCE SCORING -> TOP 3-5 KNOWLEDGE BASE
 * Tanpa Vector DB / Embedding / RAG Kompleks.
 * Menggunakan pencocokan kata kunci terukur (Lexical + Weighted Field Scoring).
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/SupabaseClient.php';
require_once __DIR__ . '/Normalizer.php';

class SearchEngine
{
    private SupabaseClient $db;

    public function __construct(SupabaseClient $db)
    {
        $this->db = $db;
    }

    /**
     * Cari Knowledge Base berdasarkan hasil normalisasi dan kembalikan Top 3-5 KB
     */
    public function searchTopKB(array $normResult, int $limit = TOP_KB_LIMIT): array
    {
        $normalizedQuery = $normResult['normalized'] ?? '';
        $tokens          = $normResult['tokens'] ?? [];

        if ($normalizedQuery === '' && empty($tokens)) {
            return [];
        }

        // 1. Utamakan memanggil fungsi RPC PostgreSQL `search_kb_relevance` di Supabase
        $rpcRes = $this->db->rpc('search_kb_relevance', [
            'p_normalized_query' => $normalizedQuery,
            'p_tokens'           => array_values($tokens),
            'p_limit'            => $limit
        ]);

        if ($rpcRes['ok'] && is_array($rpcRes['data'])) {
            $filtered = [];
            foreach ($rpcRes['data'] as $row) {
                $score = (float) ($row['relevance_score'] ?? 0);
                if ($score >= MIN_RELEVANCE_SCORE) {
                    $filtered[] = [
                        'id'              => $row['id'],
                        'category'        => $row['category'] ?? 'Umum',
                        'title'           => $row['title'],
                        'answer'          => $row['answer'],
                        'keywords'        => $row['keywords'] ?? [],
                        'source_ref'      => $row['source_ref'] ?? null,
                        'relevance_score' => round($score, 2)
                    ];
                }
            }
            return array_slice($filtered, 0, $limit);
        }

        // 2. Fallback otomatis: jika fungsi RPC belum dibuat di Supabase, gunakan query PostgREST + PHP Scoring
        return $this->fallbackRestSearch($normalizedQuery, $tokens, $limit);
    }

    /**
     * Fallback pencarian via PostgREST + kalkulasi Relevance Score di PHP
     */
    private function fallbackRestSearch(string $normalizedQuery, array $tokens, int $limit): array
    {
        $orConditions = [];
        foreach ($tokens as $token) {
            $safeToken      = urlencode($token);
            $orConditions[] = "title.ilike.*{$safeToken}*";
            $orConditions[] = "category.ilike.*{$safeToken}*";
            $orConditions[] = "answer.ilike.*{$safeToken}*";
        }

        if (empty($orConditions)) {
            return [];
        }

        $query = 'knowledge_base?select=id,category,title,answer,keywords,source_ref'
               . '&is_active=eq.true'
               . '&or=(' . implode(',', $orConditions) . ')'
               . '&limit=100';

        $res = $this->db->request('GET', $query);
        if (!$res['ok'] || !is_array($res['data'])) {
            return [];
        }

        $scoredRows = [];
        foreach ($res['data'] as $kb) {
            $score = $this->calculateRelevanceScore($kb, $normalizedQuery, $tokens);
            if ($score >= MIN_RELEVANCE_SCORE) {
                $scoredRows[] = [
                    'id'              => $kb['id'],
                    'category'        => $kb['category'] ?? 'Umum',
                    'title'           => $kb['title'],
                    'answer'          => $kb['answer'],
                    'keywords'        => $kb['keywords'] ?? [],
                    'source_ref'      => $kb['source_ref'] ?? null,
                    'relevance_score' => round($score, 2)
                ];
            }
        }

        usort($scoredRows, fn($a, $b) => $b['relevance_score'] <=> $a['relevance_score']);

        return array_slice($scoredRows, 0, $limit);
    }

    /**
     * Rumus Relevance Scoring yang mudah diuji & dijelaskan:
     * - Frasa utuh di Title: +15
     * - Frasa utuh di Keywords: +15
     * - Per kata kunci cocok di Title: +4
     * - Per kata kunci cocok di Keywords: +3
     * - Per kata kunci cocok di Category: +2
     * - Per kata kunci cocok di Answer: +1
     */
    public function calculateRelevanceScore(array $kb, string $normalizedQuery, array $tokens): float
    {
        $title    = mb_strtolower($kb['title'] ?? '', 'UTF-8');
        $category = mb_strtolower($kb['category'] ?? '', 'UTF-8');
        $answer   = mb_strtolower($kb['answer'] ?? '', 'UTF-8');
        $keywords = array_map(fn($k) => mb_strtolower($k, 'UTF-8'), $kb['keywords'] ?? []);

        $score = 0.0;

        // 1. Kecocokan Frasa Utuh
        if ($normalizedQuery !== '') {
            if (str_contains($title, $normalizedQuery)) {
                $score += 15.0;
            }
            foreach ($keywords as $kw) {
                if (str_contains($kw, $normalizedQuery)) {
                    $score += 15.0;
                    break;
                }
            }
        }

        // 2. Kecocokan Per Kata Kunci (Token)
        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }
            if (str_contains($title, $token)) {
                $score += 4.0;
            }
            foreach ($keywords as $kw) {
                if (str_contains($kw, $token)) {
                    $score += 3.0;
                    break;
                }
            }
            if (str_contains($category, $token)) {
                $score += 2.0;
            }
            if (str_contains($answer, $token)) {
                $score += 1.0;
            }
        }

        return $score;
    }
}
