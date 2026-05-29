import { AiAPI } from '../api/ai.js';

const AI_CHAT = (() => {
  const els = {
    toggle: document.getElementById('aiBoxToggle'),
    close: document.getElementById('aiBoxClose'),
    panel: document.getElementById('aiBoxPanel'),
    guidedMenu: document.getElementById('aiGuidedMenu'),
    messages: document.getElementById('aiMessages'),
    form: document.getElementById('aiAskForm'),
    input: document.getElementById('aiMessageInput'),
  };

  const classHidden = 'hidden';

  const guidedFaqs = [
    {
      question: 'Escrow là gì?',
      answer: 'Escrow là cơ chế giữ tiền trung gian để bảo vệ giao dịch giữa người mua và người bán.',
    },
    {
      question: 'Escrow hoạt động như thế nào?',
      answer: 'Người mua thanh toán vào hệ thống trước. Tiền chỉ được giải ngân cho người bán khi đơn hàng hoàn tất hoặc có xác nhận xử lý hợp lệ.',
    },
    {
      question: 'Khi nào người bán nhận được tiền?',
      answer: 'Người bán nhận tiền khi giao dịch đã hoàn tất theo quy trình của hệ thống hoặc theo quyết định xử lý của support/admin.',
    },
    {
      question: 'RFQ là gì?',
      answer: 'RFQ là yêu cầu báo giá. Khi người mua nhấn “Nhắn người bán”, hệ thống có thể tạo luồng trao đổi để người bán gửi báo giá.',
    },
    {
      question: 'Quy trình từ RFQ đến hợp đồng?',
      answer: 'Quy trình thường là: RFQ → báo giá → thương lượng → xác nhận → hợp đồng.',
    },
    {
      question: 'Làm sao để trở thành người bán?',
      answer: 'Bạn gửi yêu cầu đăng ký bán hàng và chờ admin xét duyệt hồ sơ doanh nghiệp.',
    },
    {
      question: 'Cần gì để đăng ký bán hàng?',
      answer: 'Thường cần thông tin công ty, mã số thuế và các giấy tờ pháp lý theo yêu cầu của hệ thống.',
    },
    {
      question: 'Có thể hủy hợp đồng không?',
      answer: 'Sau khi ký hợp đồng, việc hủy cần theo điều khoản đã thỏa thuận hoặc nhờ support xử lý theo quy định.',
    },
    {
      question: 'Làm sao gửi yêu cầu hỗ trợ/tranh chấp?',
      answer: 'Bạn có thể tạo support ticket hoặc gửi yêu cầu từ luồng chat để đội hỗ trợ tiếp nhận.',
    },
    {
      question: 'Thanh toán có an toàn không?',
      answer: 'Hệ thống dùng cơ chế kiểm soát giao dịch và lưu vết để tăng minh bạch, giảm rủi ro cho hai bên.',
    },
  ];

  function setPanelOpen(open) {
    if (!els.panel || !els.toggle) return;

    if (open) {
      els.panel.classList.remove(classHidden); 
      els.toggle.classList.add(classHidden);   
    } else {
      els.panel.classList.add(classHidden);    
      els.toggle.classList.remove(classHidden); 
    }
  }

  function escapeHtml(str) {
    return String(str ?? '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function scrollToBottom() {
    if (!els.messages) return;

    els.messages.scrollTop = els.messages.scrollHeight;
  }

  function appendMessage(role, text = '') {
    if (!els.messages) return null;

    const bubble = document.createElement('div');
    bubble.className = `ai-msg ${role === 'user' ? 'ai-mine' : 'ai-assistant'}`;
    bubble.innerHTML = `
      <div class="ai-role">${role === 'user' ? 'Bạn' : 'AI Assistant'}</div>
      <div class="ai-text"></div>
    `;

    const textEl = bubble.querySelector('.ai-text');
    textEl.innerHTML = escapeHtml(text);

    els.messages.appendChild(bubble);
    scrollToBottom();

    return textEl;
  }

  async function typeMessage(element, text) {
    if (!element) return;

    element.innerHTML = '';
    const speed = 14;

    for (let i = 0; i < text.length; i += 1) {
      element.innerHTML += escapeHtml(text[i]);
      scrollToBottom();
      await new Promise((resolve) => setTimeout(resolve, speed));
    }
  }

  function createThinkingBubble() {
    const bubble = appendMessage('assistant', 'AI đang suy nghĩ...');

    if (bubble) {
      bubble.classList.add('ai-thinking');
    }

    return bubble;
  }

  function shuffleArray(array) {
    const cloned = [...array];

    for (let i = cloned.length - 1; i > 0; i -= 1) {
      const j = Math.floor(Math.random() * (i + 1));
      [cloned[i], cloned[j]] = [cloned[j], cloned[i]];
    }

    return cloned;
  }

  function getRandomFaqs(limit = 3) {
    return shuffleArray(guidedFaqs).slice(0, limit);
  }

  function bindSuggestedQuestions() {
    els.guidedMenu
      ?.querySelectorAll('[data-ai-question]')
      ?.forEach((btn) => {
        btn.addEventListener('click', async () => {
          const question = btn.getAttribute('data-ai-question');
          const answer = btn.getAttribute('data-ai-answer');

          if (!question || !answer) {
            return;
          }

          appendMessage('user', question);
          const typingBubble = createThinkingBubble();

          await new Promise((resolve) => setTimeout(resolve, 500));
          if (typingBubble) {
            await typeMessage(typingBubble, answer);
            typingBubble.classList.remove('ai-thinking');
          }

          renderGuidedMenu();
        });
      });
  }

  function renderGuidedMenu() {
    if (!els.guidedMenu) return;

    const randomFaqs = getRandomFaqs(3);

    els.guidedMenu.innerHTML = randomFaqs
      .map((item) => `
        <button
          type="button"
          class="ai-guided-item"
          data-ai-question="${escapeHtml(item.question)}"
          data-ai-answer="${escapeHtml(item.answer)}"
        >
          ${escapeHtml(item.question)}
        </button>
      `)
      .join('');

    bindSuggestedQuestions();
  }

  async function askAI(message) {
    try {
      return await AiAPI.ask(message);
    } catch (error) {
      throw new Error(error?.message || 'AI request failed');
    }
  }

  async function onSubmit(event) {
    event.preventDefault();

    const message = (els.input?.value || '').trim();

    if (!message) return;

    appendMessage('user', message);
    els.input.value = '';

    const submitBtn = els.form?.querySelector('button[type="submit"]');
    submitBtn?.setAttribute('disabled', 'disabled');

    const thinkingBubble = createThinkingBubble();

    try {
      const data = await askAI(message);
      const answer = data?.answer || 'Không có phản hồi';

      await new Promise((resolve) => setTimeout(resolve, 600));
      if (thinkingBubble) {
        await typeMessage(thinkingBubble, answer);
        thinkingBubble.classList.remove('ai-thinking');
      }
    } catch (error) {
      console.error(error);
      if (thinkingBubble) {
        thinkingBubble.innerHTML = escapeHtml(error?.message || 'Có lỗi xảy ra');
      }
    } finally {
      submitBtn?.removeAttribute('disabled');
    }
  }

  function boot() {
    if (!els.toggle || !els.panel || !els.form) {
      return;
    }

    els.toggle.addEventListener('click', () => {
      setPanelOpen(true);
      renderGuidedMenu();
    });

    els.close?.addEventListener('click', () => setPanelOpen(false));
    els.form.addEventListener('submit', onSubmit);

    renderGuidedMenu();
    setPanelOpen(false);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
  } else {
    boot();
  }

  return { boot };
})();
