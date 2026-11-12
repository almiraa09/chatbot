<?php
/**
 * components/SourceReference.php
 * Drawer Info (i) untuk melihat proses Normalisasi & Sumber Top 3-5 Knowledge Base
 */

function renderSourceReference(): void
{
    ?>
    <aside id="sourceDrawer" class="source-drawer">
        <div class="card-header" style="margin-bottom:0.85rem;padding-bottom:0.65rem;">
            <div>
                <div class="card-title" style="font-size:0.95rem;">Detail Pencarian KB</div>
                <div class="card-subtitle">Transparansi Retrieval &amp; Scoring</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline" onclick="toggleSourceDrawer()">Tutup &times;</button>
        </div>

        <div class="norm-box">
            <div class="norm-row">
                <span class="norm-label">Pertanyaan Asli:</span>
                <span id="inspOriginal" class="norm-value">-</span>
            </div>
            <div class="norm-row">
                <span class="norm-label">Hasil Normalisasi:</span>
                <code id="inspNormalized" class="norm-code">-</code>
            </div>
            <div class="norm-row">
                <span class="norm-label">Kata Kunci (Tokens):</span>
                <div id="inspTokens" class="norm-tokens">-</div>
            </div>
        </div>

        <div style="font-size:0.82rem;font-weight:700;margin-bottom:0.5rem;">
            Top 3&ndash;5 Knowledge Base Terpilih
        </div>

        <div id="inspSources">
            <div style="font-size:0.8rem;color:#667781;background:#f8fafc;padding:0.85rem;border-radius:8px;border:1px dashed #cbd5e1;">
                Kirim pertanyaan pada ruang percakapan untuk melihat referensi Knowledge Base di balik layar.
            </div>
        </div>
    </aside>
    <?php
}
