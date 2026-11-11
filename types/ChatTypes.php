<?php
/**
 * types/ChatTypes.php
 * Definisi struktur data untuk alur Chatbot, Testing, dan Dashboard
 */

class NormalizedQueryResult
{
    public string $original;
    public string $normalized;
    public array $tokens;

    public function __construct(string $original, string $normalized, array $tokens)
    {
        $this->original   = $original;
        $this->normalized = $normalized;
        $this->tokens     = $tokens;
    }

    public function toArray(): array
    {
        return [
            'original'   => $this->original,
            'normalized' => $this->normalized,
            'tokens'     => $this->tokens,
        ];
    }
}

class ChatResponsePayload
{
    public string $user_query;
    public array $normalization;
    public array $sources;
    public string $answer;
    public bool $has_answer;
    public int $response_time_ms;

    public function toArray(): array
    {
        return [
            'ok'               => true,
            'user_query'       => $this->user_query,
            'normalization'    => $this->normalization,
            'top_kb_count'     => count($this->sources),
            'sources'          => $this->sources,
            'answer'           => $this->answer,
            'has_answer'       => $this->has_answer,
            'response_time_ms' => $this->response_time_ms,
        ];
    }
}
