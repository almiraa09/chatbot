<?php
/**
 * app/knowledge-base/page.php
 * Halaman /knowledge-base: Daftar, Pencarian, Detail, dan Import Knowledge Base
 */

require_once __DIR__ . '/../../components/KnowledgeBaseList.php';

function renderKnowledgeBasePage(bool $isActive = false): void
{
    ?>
    <section id="page-kb" class="page-section <?= $isActive ? 'active' : '' ?>">
        <?php renderKnowledgeBaseList(); ?>
    </section>
    <?php
}
