<?php
/**
 * SupabaseClient.php
 * Helper komunikasi REST API & RPC ke Supabase menggunakan cURL
 */

require_once __DIR__ . '/../config/config.php';

class SupabaseClient
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(SUPABASE_URL, '/');
        $this->apiKey  = SUPABASE_KEY;
    }

    /**
     * Request umum ke PostgREST Supabase
     */
    public function request(string $method, string $endpoint, $payload = null, array $extraHeaders = []): array
    {
        $url = $this->baseUrl . '/rest/v1/' . ltrim($endpoint, '/');
        $ch  = curl_init($url);

        $headers = array_merge([
            'apikey: ' . $this->apiKey,
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json',
        ], $extraHeaders);

        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_HEADER         => true,
            CURLOPT_TIMEOUT        => 60,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);

        if ($payload !== null) {
            $jsonPayload = is_string($payload) ? $payload : json_encode($payload, JSON_UNESCAPED_UNICODE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonPayload);
        }

        $response   = curl_exec($ch);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $curlError  = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return [
                'ok'     => false,
                'status' => 0,
                'error'  => 'cURL Error: ' . $curlError,
                'data'   => null
            ];
        }

        $rawHeaders = substr($response, 0, $headerSize);
        $rawBody    = substr($response, $headerSize);
        $decoded    = json_decode($rawBody, true);

        // Ambil total count dari Content-Range jika ada (misal: 0-19/2229)
        $totalCount = null;
        if (preg_match('/Content-Range:\s*(?:\d+-\d+|\*)\/(\d+)/i', $rawHeaders, $matches)) {
            $totalCount = (int) $matches[1];
        }

        $isOk = ($httpCode >= 200 && $httpCode < 300);

        return [
            'ok'     => $isOk,
            'status' => $httpCode,
            'count'  => $totalCount,
            'data'   => $decoded !== null ? $decoded : $rawBody,
            'error'  => $isOk ? null : ($decoded['message'] ?? $decoded['error'] ?? $rawBody)
        ];
    }

    /**
     * Hitung jumlah baris di sebuah tabel
     */
    public function count(string $table, string $filterQuery = ''): int
    {
        $query = $table . '?select=id&limit=1' . ($filterQuery ? '&' . $filterQuery : '');
        $res   = $this->request('GET', $query, null, ['Prefer: count=exact']);
        return $res['count'] ?? 0;
    }

    /**
     * Batch Upsert ke tabel Supabase
     */
    public function upsertBatch(string $table, array $rows, string $onConflict = 'id'): array
    {
        $endpoint = $table . '?on_conflict=' . urlencode($onConflict);
        return $this->request('POST', $endpoint, $rows, [
            'Prefer: resolution=merge-duplicates,return=minimal'
        ]);
    }

    /**
     * Insert 1 record ke tabel
     */
    public function insert(string $table, array $row): array
    {
        return $this->request('POST', $table, $row, [
            'Prefer: return=representation'
        ]);
    }

    /**
     * Update record di tabel
     */
    public function update(string $table, string $filter, array $data): array
    {
        return $this->request('PATCH', $table . '?' . $filter, $data, [
            'Prefer: return=representation'
        ]);
    }

    /**
     * Panggil fungsi RPC PostgreSQL di Supabase
     */
    public function rpc(string $functionName, array $params): array
    {
        return $this->request('POST', 'rpc/' . $functionName, $params);
    }
}
