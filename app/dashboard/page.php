<?php
/**
 * app/dashboard/page.php
 * Halaman /dashboard: Ringkasan Metrik PoC & Matriks Pembuktian 7 Kriteria
 */

require_once __DIR__ . '/../../components/DashboardCard.php';

function renderDashboardPage(bool $isActive = false): void
{
    ?>
    <section id="page-dashboard" class="page-section scrollable-page <?= $isActive ? 'active' : '' ?>">
        <div class="stat-grid">
            <?php
            renderDashboardCard(
                '1. Total Knowledge Base',
                'statTotalKb',
                '0',
                'Target PoC: ~2.229 record di Supabase'
            );
            renderDashboardCard(
                '2. Total Interaksi Pertanyaan',
                'statTotalChats',
                '0',
                'Pertanyaan pengguna yang telah diproses'
            );
            renderDashboardCard(
                '3. Pengujian Terverifikasi',
                'statTotalTests',
                '0 / 0',
                'Jawaban Akurat dibanding Total Dinilai'
            );
            renderDashboardCard(
                '4. Tingkat Akurasi Prototype',
                'statAccuracy',
                '0%',
                'Persentase ketepatan dari halaman Testing',
                'stat-card-highlight'
            );
            renderDashboardCard(
                '5. Rata-rata Waktu Respons',
                'statAvgTime',
                '0 ms',
                'Kecepatan Normalisasi + Search + Scoring'
            );
            ?>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Matriks Pembuktian Proof of Concept (PoC)</div>
                    <div class="card-subtitle">Status ketercapaian 7 indikator utama prototype Kabupaten Purworejo</div>
                </div>
                <button type="button" class="btn btn-sm btn-outline" onclick="loadDashboardStats()">Segarkan Data</button>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px;">No</th>
                            <th>Indikator Pembuktian PoC</th>
                            <th>Mekanisme pada Sistem</th>
                            <th style="width:140px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>1</td>
                            <td><strong>Knowledge Base dapat dimasukkan ke database</strong></td>
                            <td>Batch Upsert 2.229 record dari <code>knowledge-base-2026-11-10.json</code> ke tabel <code>knowledge_base</code> di Supabase.</td>
                            <td><span id="poc1" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                        <tr>
                            <td>2</td>
                            <td><strong>Knowledge Base dapat dicari berdasarkan pertanyaan pengguna</strong></td>
                            <td>Pencarian kata kunci terukur setelah melewati tahap <code>Normalizer</code>.</td>
                            <td><span id="poc2" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                        <tr>
                            <td>3</td>
                            <td><strong>Sistem dapat memilih Knowledge Base yang relevan</strong></td>
                            <td>Kalkulasi <code>Relevance Scoring</code> (Judul, Keywords, Kategori, Isi) untuk mengambil <strong>Top 3&ndash;5 KB</strong>.</td>
                            <td><span id="poc3" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                        <tr>
                            <td>4</td>
                            <td><strong>Jawaban disusun murni berdasarkan Knowledge Base</strong></td>
                            <td>Jawaban mengacu langsung pada dokumen Knowledge Base terpilih beserta daftar sumber referensinya.</td>
                            <td><span id="poc4" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                        <tr>
                            <td>5</td>
                            <td><strong>Sistem tidak mengarang informasi jika KB tidak memiliki jawabannya</strong></td>
                            <td>Gatekeeper otomatis menolak menjawab apabila tidak ada Knowledge Base yang memenuhi ambang skor relevansi.</td>
                            <td><span id="poc5" class="badge badge-green">Terbukti &check;</span></td>
                        </tr>
                        <tr>
                            <td>6</td>
                            <td><strong>Sistem dapat diuji menggunakan sejumlah pertanyaan</strong></td>
                            <td>Modul <code>/testing</code> menyediakan uji satuan dan 8 pertanyaan uji standar (positif &amp; luar KB).</td>
                            <td><span id="poc6" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                        <tr>
                            <td>7</td>
                            <td><strong>Hasil pengujian dapat digunakan untuk menilai akurasi prototype</strong></td>
                            <td>Penilaian <em>Akurat / Tidak Akurat</em> dihitung otomatis menjadi persentase akurasi pada Dashboard.</td>
                            <td><span id="poc7" class="badge badge-amber">Siap Diuji</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div>
                    <div class="card-title">Log Pertanyaan Belum Terjawab (Evaluasi Sinonim &amp; Gap Knowledge Base)</div>
                    <div class="card-subtitle">Pertanyaan yang ditolak oleh Gatekeeper Anti-Halusinasi karena belum ditemukan di Knowledge Base</div>
                </div>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Pertanyaan Pengguna</th>
                            <th>Hasil Normalisasi Kata Kunci</th>
                            <th style="width:190px;">Waktu</th>
                        </tr>
                    </thead>
                    <tbody id="unansweredTableBody">
                        <tr>
                            <td colspan="3" style="text-align:center;color:#64748b;">Belum ada pertanyaan yang tidak terjawab.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
    <?php
}
