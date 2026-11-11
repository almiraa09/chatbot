<?php
/**
 * components/KnowledgeBaseList.php
 * Komponen tabel daftar Knowledge Base beserta kontrol import & paginasi
 */

require_once __DIR__ . '/SearchBox.php';
require_once __DIR__ . '/KnowledgeBaseDetail.php';

function renderKnowledgeBaseList(): void
{
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Database Knowledge Base Kabupaten Purworejo</div>
                <div id="kbStatusBanner" class="card-subtitle">
                    Memeriksa status file <code>knowledge-base-2026-11-10.json</code> dan koneksi Supabase...
                </div>
            </div>
            <div style="display:flex;gap:0.5rem;align-items:center;">
                <button type="button" id="btnImportKb" class="btn" onclick="importKnowledgeBase()">
                    Import JSON ke Supabase (2.229 Record)
                </button>
            </div>
        </div>

        <div id="importProgressMsg" style="font-size:0.84rem;margin-bottom:0.75rem;"></div>

        <?php renderSearchBox('kbSearchInput', 'kbCategoryFilter', 'loadKbTable(1)'); ?>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th style="width:170px;">Kategori &amp; ID</th>
                        <th style="width:250px;">Judul Knowledge Base</th>
                        <th>Ringkasan Jawaban / Informasi</th>
                        <th style="width:210px;">Kata Kunci</th>
                        <th style="width:100px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="kbTableBody">
                    <tr>
                        <td colspan="5" style="text-align:center;color:#64748b;">Memuat daftar Knowledge Base...</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pagination-bar">
            <span id="kbPageInfo" class="pagination-info">Halaman 1</span>
            <div style="display:flex;gap:0.45rem;">
                <button type="button" id="btnPrevKb" class="btn btn-sm btn-outline" onclick="loadKbTable(currentKbPage - 1)">&laquo; Sebelumnya</button>
                <button type="button" id="btnNextKb" class="btn btn-sm btn-outline" onclick="loadKbTable(currentKbPage + 1)">Berikutnya &raquo;</button>
            </div>
        </div>
    </div>

    <?php renderKnowledgeBaseDetail(); ?>
    <?php
}
