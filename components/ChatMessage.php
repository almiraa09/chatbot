<?php
/**
 * components/ChatMessage.php
 * Komponen pesan percakapan (Pengguna & Sistem Chatbot)
 */

function renderInitialChatMessage(): void
{
    ?>
    <div class="msg msg-bot">
        <div class="msg-sender">Asisten Informasi Kabupaten Purworejo</div>
        <div class="msg-text">Selamat datang di Prototype Chatbot Knowledge Base Pemerintah Kabupaten Purworejo.

Silakan ajukan pertanyaan seputar layanan publik, administrasi kependudukan, perizinan, kesehatan, atau informasi daerah. Sistem hanya akan menjawab berdasarkan data resmi di dalam Knowledge Base.</div>
        <div class="msg-meta">
            <span class="badge badge-blue">Sumber Utama: Knowledge Base Resmi</span>
        </div>
    </div>
    <?php
}
