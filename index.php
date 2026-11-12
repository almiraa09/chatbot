<?php
/**
 * index.php
 * Layout Aplikasi Chat 3 Area — Chatbot Knowledge Base Kabupaten Purworejo
 * Area 1: Sidebar Kiri (Navigasi 4 Halaman Utama)
 * Area 2: Daftar Percakapan di Tengah (Saat di halaman Chatbot)
 * Area 3: Ruang Percakapan Utama di Kanan
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
    <title>Purworejo — Chatbot Knowledge Base</title>
    <link rel="stylesheet" href="public/css/style.css">
</head>
<body data-initial-tab="<?= htmlspecialchars($activeTab) ?>">

    <div class="app-shell">
        <!-- AREA 1: SIDEBAR KIRI SEDERHANA -->
        <aside class="left-sidebar">
            <div>
                <div class="sidebar-brand">
                    <div class="brand-crest">PWR</div>
                    <div class="brand-text">
                        <h1>Purworejo</h1>
                        <p>Chatbot Knowledge Base</p>
                    </div>
                </div>

                <nav class="sidebar-nav">
                    <button type="button" class="nav-btn <?= $activeTab === 'page-chatbot' ? 'active' : '' ?>" data-target="page-chatbot" data-route="chat">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
                        <span>Chatbot</span>
                    </button>
                    <button type="button" class="nav-btn <?= $activeTab === 'page-kb' ? 'active' : '' ?>" data-target="page-kb" data-route="knowledge-base">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"/><path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"/></svg>
                        <span>Knowledge Base</span>
                    </button>
                    <button type="button" class="nav-btn <?= $activeTab === 'page-testing' ? 'active' : '' ?>" data-target="page-testing" data-route="testing">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><path d="M10 2v7.31"/><path d="M14 9.3V1.99"/><path d="M8.5 2h7"/><path d="M14 9.3a6.5 6.5 0 1 1-4 0"/><path d="M5.52 16h12.96"/></svg>
                        <span>Testing</span>
                    </button>
                    <button type="button" class="nav-btn <?= $activeTab === 'page-dashboard' ? 'active' : '' ?>" data-target="page-dashboard" data-route="dashboard">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>
                        <span>Dashboard</span>
                    </button>
                </nav>
            </div>

            <div class="sidebar-footer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <div>
                    <strong>PoC</strong>
                    <span>Kabupaten Purworejo</span>
                </div>
            </div>
        </aside>

        <!-- AREA KANAN: WORKSPACE HALAMAN AKTIF -->
        <main class="main-workspace">
            <?php
            renderChatPage($activeTab === 'page-chatbot');
            renderKnowledgeBasePage($activeTab === 'page-kb');
            renderTestingPage($activeTab === 'page-testing');
            renderDashboardPage($activeTab === 'page-dashboard');
            ?>
        </main>
    </div>

    <script src="public/js/app.js"></script>
</body>
</html>
