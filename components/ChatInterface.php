<?php
/**
 * components/ChatInterface.php
 * Komponen utama ruang percakapan Chatbot dan tombol pertanyaan cepat
 */

require_once __DIR__ . '/ChatMessage.php';

function renderChatInterface(): void
{
    ?>
    <div class="card chat-interface-panel">
        <div class="card-header">
            <div>
                <div class="card-title">Layanan Tanya Jawab Knowledge Base</div>
                <div class="card-subtitle">Kabupaten Purworejo &bull; Jawaban Berbasis Data Resmi</div>
            </div>
            <span class="badge badge-blue">Anti-Halusinasi Aktif</span>
        </div>

        <div id="chatBox" class="chat-box">
            <?php renderInitialChatMessage(); ?>
        </div>

        <div class="quick-chips-wrap">
            <span class="quick-chips-label">Contoh Pertanyaan Uji:</span>
            <div class="quick-chips">
                <button type="button" class="chip" onclick="askSample('Dimana alamat dan jam pelayanan Dinas Perhubungan Purworejo?')">Alamat &amp; Jam Dinhub</button>
                <button type="button" class="chip" onclick="askSample('Apa syarat pendirian badan usaha CV atau PT?')">Syarat Pendirian CV/PT</button>
                <button type="button" class="chip" onclick="askSample('Bagaimana syarat Asuransi Usaha Tani Padi (AUTP)?')">Asuransi Tani Padi</button>
                <button type="button" class="chip" onclick="askSample('Siapa pemenang Piala Dunia 2022?')">Uji Pertanyaan di Luar KB</button>
            </div>
        </div>

        <form class="chat-input-row" onsubmit="event.preventDefault(); sendChat();">
            <input
                type="text"
                id="chatInput"
                placeholder="Ketik pertanyaan Anda mengenai layanan Kabupaten Purworejo..."
                autocomplete="off"
            >
            <button type="submit" id="btnSendChat" class="btn">Kirim Pertanyaan</button>
        </form>
    </div>
    <?php
}
