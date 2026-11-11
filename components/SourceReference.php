<?php
/**
 * components/SourceReference.php
 * Komponen panel Sumber Knowledge Base & hasil Normalisasi / Relevance Scoring
 */

function renderSourceReference(): void
{
    ?>
    <div class="card source-reference-panel">
        <div class="card-header">
            <div>
                <div class="card-title">Sumber Knowledge Base &amp; Skor Relevansi</div>
                <div class="card-subtitle">Transparansi proses Normalisasi &rarr; Search &rarr; Top 3&ndash;5 KB</div>
            </div>
            <span class="badge badge-green">Top 3&ndash;5 KB</span>
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

        <div class="source-list-header">
            <span>Daftar Referensi Knowledge Base Terpilih</span>
        </div>

        <div id="inspSources" class="source-list-body">
            <div class="empty-state-small">
                Belum ada pencarian. Ajukan pertanyaan pada panel Chatbot untuk melihat dokumen Knowledge Base yang menjadi sumber jawaban beserta skor relevansinya.
            </div>
        </div>
    </div>
    <?php
}
