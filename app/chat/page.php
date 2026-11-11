<?php
/**
 * app/chat/page.php
 * Halaman /chat: Antarmuka Chatbot & Panel Referensi Sumber Knowledge Base
 */

require_once __DIR__ . '/../../components/ChatInterface.php';
require_once __DIR__ . '/../../components/SourceReference.php';

function renderChatPage(bool $isActive = false): void
{
    ?>
    <section id="page-chatbot" class="page-section <?= $isActive ? 'active' : '' ?>">
        <div class="chat-grid">
            <?php renderChatInterface(); ?>
            <?php renderSourceReference(); ?>
        </div>
    </section>
    <?php
}
