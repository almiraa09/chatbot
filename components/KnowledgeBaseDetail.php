<?php
/**
 * components/KnowledgeBaseDetail.php
 * Komponen panel/modal detail satu record Knowledge Base lengkap
 */

function renderKnowledgeBaseDetail(): void
{
    ?>
    <div id="kbDetailModal" class="kb-modal-backdrop" style="display:none;" onclick="if(event.target===this) closeKbDetail()">
        <div class="kb-modal-card">
            <div class="card-header">
                <div>
                    <span id="detailCategory" class="badge badge-blue">Kategori</span>
                    <h3 id="detailTitle" class="card-title" style="margin-top:0.35rem;">Judul Knowledge Base</h3>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="closeKbDetail()">Tutup &times;</button>
            </div>

            <div class="kb-detail-meta">
                <div><strong>ID Record:</strong> <code id="detailId">-</code></div>
                <div><strong>Sumber / Referensi:</strong> <span id="detailSourceRef">-</span></div>
            </div>

            <div class="kb-detail-section">
                <div class="kb-detail-label">Isi Informasi / Jawaban Resmi:</div>
                <div id="detailAnswer" class="kb-detail-answer">-</div>
            </div>

            <div class="kb-detail-section">
                <div class="kb-detail-label">Kata Kunci Terdaftar (Keywords):</div>
                <div id="detailKeywords" class="kb-detail-keywords">-</div>
            </div>
        </div>
    </div>
    <?php
}
