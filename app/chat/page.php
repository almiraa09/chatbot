<?php
/**
 * app/chat/page.php
 * Halaman /chat: Layout 3 Area Aplikasi Chat Modern
 */

require_once __DIR__ . '/../../components/ChatInterface.php';

function renderChatPage(bool $isActive = false): void
{
    ?>
    <section id="page-chatbot" class="page-section <?= $isActive ? 'active' : '' ?>">
        <?php renderChatInterface(); ?>
    </section>
    <?php
}
