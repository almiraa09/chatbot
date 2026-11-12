<?php
/**
 * app/knowledge-base/page.php
 * Halaman /knowledge-base
 */

require_once __DIR__ . '/../../components/KnowledgeBaseList.php';

function renderKnowledgeBasePage(bool $isActive = false): void
{
    ?>
    <section id="page-kb" class="page-section scrollable-page <?= $isActive ? 'active' : '' ?>">
        <?php renderKnowledgeBaseList(); ?>
    </section>
    <?php
}
