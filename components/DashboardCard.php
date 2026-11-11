<?php
/**
 * components/DashboardCard.php
 * Komponen kartu statistik metrik pada halaman Dashboard
 */

function renderDashboardCard(string $label, string $valueId, string $defaultValue, string $subtitle, string $accentClass = ''): void
{
    ?>
    <div class="stat-card <?= htmlspecialchars($accentClass) ?>">
        <div class="stat-label"><?= htmlspecialchars($label) ?></div>
        <div id="<?= htmlspecialchars($valueId) ?>" class="stat-value"><?= htmlspecialchars($defaultValue) ?></div>
        <div class="stat-sub"><?= htmlspecialchars($subtitle) ?></div>
    </div>
    <?php
}
