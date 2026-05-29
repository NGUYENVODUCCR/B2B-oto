{{-- Floating AI box (Layer 1 guided + Layer 2 natural answer) --}}
<div id="aiBox" class="ai-box" aria-label="AI Box">
  <button type="button" id="aiBoxToggle" class="ai-box-toggle" aria-label="Mở AI chat">
    <span class="ai-box-toggle-icon">
        <img src="https://res.cloudinary.com/dilvws4q7/image/upload/v1779729434/z7865520985916_94676667749ce69308d11a25a5a56ad3_qgmkeq.jpg" alt="AI icon">
    </span>
  </button>

  <div id="aiBoxPanel" class="ai-box-panel hidden" role="dialog" aria-modal="false">
    <div class="ai-box-header">
      <div>
        <div class="ai-box-title">AI Assistant</div>
        <div class="ai-box-subtitle">Hướng dẫn & trả lời theo kiến thức B2B Escrow</div>
      </div>
      <button type="button" id="aiBoxClose" class="ai-box-close" aria-label="Đóng">×</button>
    </div>

    <div class="ai-box-body">
      <div class="ai-layer ai-layer-1">
        <div class="ai-layer-label">Layer 1 — Guided Assistant</div>
        <div class="ai-guided-menu" id="aiGuidedMenu"></div>
      </div>

      <div class="ai-layer ai-layer-2">
        <div class="ai-layer-label">Layer 2 — AI Knowledge Assistant</div>
        <div class="ai-chat-messages" id="aiMessages"></div>
      </div>
    </div>

    <form id="aiAskForm" class="ai-box-footer" autocomplete="off" onsubmit="return false;">
      <input
        type="text"
        id="aiMessageInput"
        name="message"
        placeholder="Nhập câu hỏi..."
        required
      />
      <button type="submit" class="ai-send-btn">Gửi</button>
    </form>
  </div>
</div>
