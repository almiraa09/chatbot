<?php
/**
 * components/TestingPanel.php
 * Komponen panel pengujian skenario pertanyaan dan verifikasi akurasi prototype
 */

function renderTestingPanel(): void
{
    ?>
    <div class="card">
        <div class="card-header">
            <div>
                <div class="card-title">Panel Pengujian &amp; Evaluasi Akurasi Prototype</div>
                <div id="testSummaryBanner" class="card-subtitle">
                    Memuat ringkasan hasil pengujian...
                </div>
            </div>
            <button type="button" id="btnRunBatchTest" class="btn" onclick="runPresetBatchTests()">
                Jalankan 8 Pertanyaan Uji Standar
            </button>
        </div>

        <form class="testing-form-bar" onsubmit="event.preventDefault(); runSingleCustomTest();">
            <input
                type="text"
                id="testQuestionInput"
                placeholder="Masukkan pertanyaan uji untuk menilai ketepatan pencarian Knowledge Base..."
                autocomplete="off"
            >
            <input
                type="text"
                id="testExpectedInput"
                placeholder="Ekspektasi Topik KB (atau: TIDAK ADA DI KB)"
                autocomplete="off"
            >
            <button type="submit" id="btnRunSingleTest" class="btn">Uji Pertanyaan</button>
        </form>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Pertanyaan Uji &amp; Hasil Normalisasi</th>
                        <th style="width:260px;">Top 3&ndash;5 KB Terpilih (Skor)</th>
                        <th>Jawaban Berdasarkan KB</th>
                        <th style="width:125px;">Status Akurasi</th>
                        <th style="width:195px;">Penilaian Penguji</th>
                    </tr>
                </thead>
                <tbody id="testTableBody">
                    <tr>
                        <td colspan="5" style="text-align:center;color:#64748b;">Memuat daftar pengujian...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}
