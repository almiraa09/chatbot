<?php
/**
 * components/SearchBox.php
 * Komponen input pencarian dan filter kategori Knowledge Base
 */

function renderSearchBox(string $inputId = 'kbSearchInput', string $selectId = 'kbCategoryFilter', string $onSearchJs = 'loadKbTable(1)'): void
{
    ?>
    <div class="search-box-bar">
        <div class="search-input-wrap">
            <input
                type="text"
                id="<?= htmlspecialchars($inputId) ?>"
                placeholder="Cari judul layanan, instansi, kata kunci, atau isi Knowledge Base..."
                onkeydown="if(event.key==='Enter') <?= htmlspecialchars($onSearchJs) ?>"
            >
        </div>
        <div class="search-filter-wrap">
            <select id="<?= htmlspecialchars($selectId) ?>" onchange="<?= htmlspecialchars($onSearchJs) ?>">
                <option value="">Semua Kategori Layanan</option>
            </select>
        </div>
        <button class="btn" onclick="<?= htmlspecialchars($onSearchJs) ?>">Cari Knowledge Base</button>
    </div>
    <?php
}
