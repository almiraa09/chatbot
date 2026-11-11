<?php
/**
 * index.php
 * Router & Layout Utama Prototype: "Chatbot Knowledge Base Kabupaten Purworejo"
 * Mendukung halaman:
 * - /chat
 * - /knowledge-base
 * - /testing
 * - /dashboard
 */

require_once __DIR__ . '/app/chat/page.php';
require_once __DIR__ . '/app/knowledge-base/page.php';
require_once __DIR__ . '/app/testing/page.php';
require_once __DIR__ . '/app/dashboard/page.php';

$rawRoute = trim($_GET['route'] ?? '', '/');
$routeMap = [
    ''               => 'page-chatbot',
    'chat'           => 'page-chatbot',
    'knowledge-base' => 'page-kb',
    'testing'        => 'page-testing',
    'dashboard'      => 'page-dashboard',
];
$activeTab = $routeMap[$rawRoute] ?? 'page-chatbot';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chatbot Knowledge Base Kabupaten Purworejo — Prototype PoC</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body data-initial-tab="<?= htmlspecialchars($activeTab) ?>">

    <!-- Navigasi Utama Pemerintah Kabupaten Purworejo -->
    <header class="topbar">
        <div class="brand">
            <div class="brand-badge">PURWOREJO</div>
            <div>
                <h1>Chatbot Knowledge Base Kabupaten Purworejo</h1>
                <p>Proof of Concept (PoC) &bull; Knowledge Base sebagai Sumber Informasi Utama</p>
            </div>
        </div>
        <nav class="nav-tabs">
            <button type="button" class="nav-btn <?= $activeTab === 'page-chatbot' ? 'active' : '' ?>" data-target="page-chatbot" data-route="chat">
                1. Chatbot
            </button>
            <button type="button" class="nav-btn <?= $activeTab === 'page-kb' ? 'active' : '' ?>" data-target="page-kb" data-route="knowledge-base">
                2. Knowledge Base
            </button>
            <button type="button" class="nav-btn <?= $activeTab === 'page-testing' ? 'active' : '' ?>" data-target="page-testing" data-route="testing">
                3. Testing
            </button>
            <button type="button" class="nav-btn <?= $activeTab === 'page-dashboard' ? 'active' : '' ?>" data-target="page-dashboard" data-route="dashboard">
                4. Dashboard
            </button>
        </nav>
    </header>

    <main class="container">
        <!-- Indikator Alur Arsitektur PoC -->
        <div class="pipeline-bar">
            <span>ALUR SISTEM:</span>
            <span class="pipeline-step">USER</span> &rarr;
            <span class="pipeline-step">CHATBOT</span> &rarr;
            <span class="pipeline-step">NORMALIZE</span> &rarr;
            <span class="pipeline-step">SEARCH KB</span> &rarr;
            <span class="pipeline-step">RELEVANCE SCORING</span> &rarr;
            <span class="pipeline-step">TOP 3&ndash;5 KB</span> &rarr;
            <span class="pipeline-step">AI / LLM</span> &rarr;
            <span class="pipeline-step">ANSWER</span> &rarr;
            <span class="pipeline-step">SOURCE</span> &rarr;
            <span class="pipeline-step">EVALUATION</span>
        </div>

        <?php
        renderChatPage($activeTab === 'page-chatbot');
        renderKnowledgeBasePage($activeTab === 'page-kb');
        renderTestingPage($activeTab === 'page-testing');
        renderDashboardPage($activeTab === 'page-dashboard');
        ?>
    </main>

    <script src="public/js/app.js"></script>
</body>
</html>
