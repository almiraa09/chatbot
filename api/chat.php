<?php
/**
 * api/chat.php
 * Endpoint API Tanya Jawab Chatbot (Menggunakan ChatbotService — berbasis Knowledge Base murni)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../services/ChatbotService.php';

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $db  = getSupabaseClient();
    $res = $db->request('GET', 'chat_logs?select=*&order=created_at.desc&limit=20');
    echo json_encode([
        'ok'   => $res['ok'],
        'logs' => $res['ok'] && is_array($res['data']) ? $res['data'] : []
    ]);
    exit;
}

$rawInput = file_get_contents('php://input');
$body     = json_decode($rawInput, true) ?? $_POST;
$question = trim($body['question'] ?? '');

if ($question === '') {
    http_response_code(400);
    echo json_encode([
        'ok'    => false,
        'error' => 'Pertanyaan tidak boleh kosong.'
    ]);
    exit;
}

$chatService = new ChatbotService();
$result      = $chatService->processQuestion($question);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
