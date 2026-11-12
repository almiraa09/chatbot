<?php
/**
 * components/ChatMessage.php
 * Komponen pesan percakapan awal bergaya aplikasi chat modern
 */

function renderInitialChatMessage(): void
{
    $nowTime = date('H:i');
    ?>
    <div class="date-chip-row">
        <span class="date-chip">Hari ini</span>
    </div>
    <div class="msg msg-bot">
        <div class="msg-text">Halo! 👋
Selamat datang di Layanan Informasi Kabupaten Purworejo.

Ada yang bisa saya bantu hari ini? Silakan tanyakan seputar layanan publik, administrasi kependudukan, perizinan, kesehatan, atau informasi daerah lainnya ya ✨</div>
        <div class="msg-time"><?= $nowTime ?></div>
    </div>
    <?php
}
