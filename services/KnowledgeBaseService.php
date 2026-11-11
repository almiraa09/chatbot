<?php
/**
 * services/KnowledgeBaseService.php
 * Layanan manajemen data Knowledge Base (Import JSON, Pencarian, Relevance Scoring, Detail KB)
 */

require_once __DIR__ . '/../lib/supabase.php';
require_once __DIR__ . '/../lib/normalizer.php';
require_once __DIR__ . '/../core/SearchEngine.php';
require_once __DIR__ . '/../types/KnowledgeBaseTypes.php';

class KnowledgeBaseService
{
    private SupabaseClient $db;
    private SearchEngine $searchEngine;

    public function __construct()
    {
        $this->db           = getSupabaseClient();
        $this->searchEngine = new SearchEngine($this->db);
    }

    /**
     * Mencari Top 3-5 Knowledge Base paling relevan berdasarkan pertanyaan pengguna
     */
    public function findRelevantKnowledgeBase(string $question, int $limit = TOP_KB_LIMIT): array
    {
        $norm   = Normalizer::normalize($question);
        $topKBs = $this->searchEngine->searchTopKB($norm, $limit);

        return [
            'normalization' => $norm,
            'results'       => $topKBs,
        ];
    }

    /**
     * Mengambil daftar Knowledge Base dengan filter kata kunci, kategori, dan paginasi
     */
    public function getKnowledgeBaseList(string $query = '', string $category = '', int $page = 1, int $limit = 15): array
    {
        $offset     = max(0, ($page - 1) * $limit);
        $queryParts = [
            'select=id,category,title,answer,keywords,source_ref,entity,agencies,is_active,updated_at',
            'order=title.asc',
            "limit={$limit}",
            "offset={$offset}"
        ];

        if ($category !== '') {
            $queryParts[] = 'category=eq.' . urlencode($category);
        }

        if ($query !== '') {
            $safeQ        = urlencode($query);
            $queryParts[] = "or=(title.ilike.*{$safeQ}*,answer.ilike.*{$safeQ}*,category.ilike.*{$safeQ}*)";
        }

        $res = $this->db->request('GET', 'knowledge_base?' . implode('&', $queryParts), null, ['Prefer: count=exact']);

        return [
            'ok'    => $res['ok'],
            'page'  => $page,
            'limit' => $limit,
            'total' => $res['count'] ?? 0,
            'rows'  => ($res['ok'] && is_array($res['data'])) ? $res['data'] : [],
            'error' => $res['error']
        ];
    }
}
