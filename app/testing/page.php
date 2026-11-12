<?php
/**
 * app/testing/page.php
 * Halaman /testing
 */

require_once __DIR__ . '/../../components/TestingPanel.php';

function renderTestingPage(bool $isActive = false): void
{
    ?>
    <section id="page-testing" class="page-section scrollable-page <?= $isActive ? 'active' : '' ?>">
        <?php renderTestingPanel(); ?>
    </section>
    <?php
}
