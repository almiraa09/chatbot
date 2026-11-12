<?php
/**
 * components/ChatInterface.php
 * Komponen ruang chat dengan layout daftar percakapan tengah + area percakapan utama kanan
 * sesuai referensi desain visual
 */

require_once __DIR__ . '/ChatMessage.php';
require_once __DIR__ . '/SourceReference.php';

function renderChatInterface(): void
{
    $nowTime = date('H:i');
    ?>
    <div class="chat-three-pane">

        <!-- AREA TENGAH: DAFTAR PERCAKAPAN (CHAT LIST) -->
        <div class="chat-middle-list">
            <div class="chat-list-top">
                <div class="chat-list-title-row">
                    <h2>Chatbot Purworejo</h2>
                    <div class="chat-list-icons">
                        <button type="button" class="icon-btn" title="Cari topik di Knowledge Base" onclick="switchTab('page-kb', 'knowledge-base')">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                        </button>
                        <button type="button" class="icon-btn" title="Informasi Retrieval KB" onclick="toggleSourceDrawer()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>
                        </button>
                    </div>
                </div>
                <div class="chat-list-status">
                    <span class="online-dot"></span>
                    <span>Siap membantu informasi seputar Kabupaten Purworejo</span>
                </div>
            </div>

            <!-- Item Percakapan Aktif -->
            <div class="chat-thread-item">
                <div class="bot-avatar">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>
                </div>
                <div class="thread-content">
                    <div class="thread-header">
                        <span class="thread-name">Layanan Informasi Purworejo</span>
                        <span id="threadLastTime" class="thread-time"><?= $nowTime ?></span>
                    </div>
                    <div id="threadLastPreview" class="thread-preview">Silakan tanyakan informasi layanan Purworejo...</div>
                </div>
            </div>

            <!-- Pintasan Pertanyaan Populer -->
            <div class="chat-list-prompts">
                <div class="prompts-title">Topik Pertanyaan Cepat</div>
                <div class="prompt-pill-list">
                    <button type="button" class="chip" onclick="askSample('Dimana alamat dan jam pelayanan Dinas Perhubungan Purworejo?')">📍 Alamat &amp; jam pelayanan Dinhub</button>
                    <button type="button" class="chip" onclick="askSample('Apa syarat pendirian badan usaha CV atau PT?')">📋 Syarat pendirian usaha CV / PT</button>
                    <button type="button" class="chip" onclick="askSample('Bagaimana syarat Asuransi Usaha Tani Padi (AUTP)?')">🌾 Asuransi Usaha Tani Padi (AUTP)</button>
                    <button type="button" class="chip" onclick="askSample('Apa syarat donor darah di PMI Purworejo?')">🩸 Syarat donor darah PMI Purworejo</button>
                    <button type="button" class="chip" onclick="askSample('Siapa pemenang Piala Dunia 2022?')">🛡️ Uji pertanyaan di luar KB</button>
                </div>
            </div>
        </div>

        <!-- AREA KANAN: RUANG PERCAKAPAN UTAMA -->
        <div class="chat-right-conversation">
            <!-- Header Chat Compact -->
            <header class="chat-conv-header">
                <div class="conv-header-left">
                    <div class="bot-avatar" style="width:38px;height:38px;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="10" rx="2"/><circle cx="12" cy="5" r="2"/><path d="M12 7v4"/><line x1="8" y1="16" x2="8" y2="16"/><line x1="16" y1="16" x2="16" y2="16"/></svg>
                    </div>
                    <div>
                        <div class="conv-header-title">Layanan Informasi Purworejo</div>
                        <div class="conv-header-status">Online</div>
                    </div>
                </div>
                <div>
                    <button type="button" class="icon-btn" title="Lihat Sumber Knowledge Base (Retrieval Info)" onclick="toggleSourceDrawer()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    </button>
                </div>
            </header>

            <!-- Area Bubble Percakapan -->
            <div id="chatBox" class="chat-box">
                <?php renderInitialChatMessage(); ?>
            </div>

            <!-- Message Composer di Bagian Bawah -->
            <form class="chat-composer-bar" onsubmit="event.preventDefault(); sendChat();">
                <div class="composer-input-pill">
                    <span class="composer-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="20" height="20"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>
                    </span>
                    <input
                        type="text"
                        id="chatInput"
                        placeholder="Ketik pertanyaan Anda..."
                        autocomplete="off"
                    >
                    <span class="composer-icon" title="Lihat Sumber KB" style="cursor:pointer;" onclick="toggleSourceDrawer()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" width="19" height="19"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                    </span>
                </div>
                <button type="submit" id="btnSendChat" class="btn-send-circle" title="Kirim Pertanyaan">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="19" height="19"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                </button>
            </form>

            <!-- Drawer Sumber Knowledge Base (Tersembunyi secara default, buka lewat ikon (i)) -->
            <?php renderSourceReference(); ?>
        </div>

    </div>
    <?php
}
