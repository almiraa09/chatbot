<?php
/**
 * types/KnowledgeBaseTypes.php
 * Definisi struktur tipe data utama untuk Knowledge Base Kabupaten Purworejo
 */

class KnowledgeBaseItem
{
    public string $id;
    public string $category;
    public string $title;
    public string $answer;
    public array $keywords;
    public array $aliases;
    public ?string $entity;
    public ?string $source_ref;
    public array $agencies;
    public ?string $agency_level;
    public bool $is_active;
    public float $relevance_score;

    public static function fromArray(array $data): self
    {
        $item                  = new self();
        $item->id              = (string) ($data['id'] ?? '');
        $item->category        = (string) ($data['category'] ?? 'Umum');
        $item->title           = (string) ($data['title'] ?? '');
        $item->answer          = (string) ($data['answer'] ?? '');
        $item->keywords        = is_array($data['keywords'] ?? null) ? $data['keywords'] : [];
        $item->aliases         = is_array($data['aliases'] ?? null) ? $data['aliases'] : [];
        $item->entity          = $data['entity'] ?? null;
        $item->source_ref      = $data['source_ref'] ?? null;
        $item->agencies        = is_array($data['agencies'] ?? null) ? $data['agencies'] : [];
        $item->agency_level    = $data['agency_level'] ?? null;
        $item->is_active       = (bool) ($data['is_active'] ?? true);
        $item->relevance_score = (float) ($data['relevance_score'] ?? 0.0);
        return $item;
    }

    public function toArray(): array
    {
        return [
            'id'              => $this->id,
            'category'        => $this->category,
            'title'           => $this->title,
            'answer'          => $this->answer,
            'keywords'        => $this->keywords,
            'aliases'         => $this->aliases,
            'entity'          => $this->entity,
            'source_ref'      => $this->source_ref,
            'agencies'        => $this->agencies,
            'agency_level'    => $this->agency_level,
            'is_active'       => $this->is_active,
            'relevance_score' => $this->relevance_score,
        ];
    }
}
