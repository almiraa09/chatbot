<?php
/**
 * app/testing/page.php
 * Halaman /testing: Evaluasi pertanyaan uji & pengukuran akurasi prototype
 */

require_once __DIR__ . '/../../components/TestingPanel.php';

function renderTestingPage(bool $isActive = false): void
{
    ?>
    <section id="page-testing" class="page-section <?= $isActive ? 'active' : '' ?>">
        <?php renderTestingPanel(); ?>
    </section>
    <?php
}
