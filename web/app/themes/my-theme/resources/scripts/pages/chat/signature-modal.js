export function createSignatureModalController({
  state,
  qs,
  ContractAPI,
  companyId,
  wpUserId,
  currentUserName,
  contractA4Document,
  handleChatError,
  openConversation,
  notifyChatSocket,
  emitRealtimeEvent,
}) {
  const signaturePad = {
    contractId: null,
    party: null,
    drawing: false,
    hasInk: false,
    lastPoint: null,
  };

  function ensureSignatureModal() {
    if (qs('chatSignatureModal')) {
      return;
    }

    document.body.insertAdjacentHTML('beforeend', `
      <div id="chatSignatureModal" class="chat-signature-modal hidden" role="dialog" aria-modal="true">
        <div class="chat-signature-dialog">
          <div class="chat-signature-preview">
            <div class="chat-signature-preview-head">
              <strong>Hợp đồng A4</strong>
              <button type="button" class="secondary" data-signature-action="close">Đóng</button>
            </div>
            <div id="chatSignatureContractPreview" class="chat-signature-contract"></div>
          </div>
          <aside class="chat-signature-pad">
            <h3>Ký điện tử</h3>
            <label>
              <span>Người ký</span>
              <input type="text" id="chatSignatureName" autocomplete="name">
            </label>
            <canvas id="chatSignatureCanvas"></canvas>
            <div class="chat-inline-actions">
              <button type="button" class="secondary" data-signature-action="clear">Ký lại</button>
              <button type="button" data-signature-action="submit">Hoàn tất ký</button>
            </div>
          </aside>
        </div>
      </div>
    `);

    qs('chatSignatureModal')?.addEventListener('click', (event) => {
      if (event.target === qs('chatSignatureModal')) {
        closeSignatureModal();
        return;
      }

      const action = event.target.closest('[data-signature-action]')?.dataset.signatureAction;

      if (action === 'close') {
        closeSignatureModal();
      }

      if (action === 'clear') {
        resetSignatureCanvas();
      }

      if (action === 'submit') {
        submitSignatureFromModal().catch((error) => handleChatError(error, 'Không thể ký hợp đồng.'));
      }
    });

    bindSignatureCanvas();
  }

  function signatureCanvasContext() {
    const canvas = qs('chatSignatureCanvas');

    return canvas?.getContext('2d') || null;
  }

  function resetSignatureCanvas() {
    const canvas = qs('chatSignatureCanvas');
    const context = signatureCanvasContext();

    if (!canvas || !context) {
      return;
    }

    const rect = canvas.getBoundingClientRect();
    const ratio = window.devicePixelRatio || 1;

    canvas.width = Math.max(1, rect.width * ratio);
    canvas.height = Math.max(1, rect.height * ratio);
    context.setTransform(ratio, 0, 0, ratio, 0, 0);
    context.fillStyle = '#ffffff';
    context.fillRect(0, 0, rect.width, rect.height);
    context.strokeStyle = '#111827';
    context.lineWidth = 2;
    context.lineCap = 'round';
    context.lineJoin = 'round';
    signaturePad.hasInk = false;
    signaturePad.lastPoint = null;
  }

  function signaturePoint(event) {
    const canvas = qs('chatSignatureCanvas');
    const rect = canvas.getBoundingClientRect();

    return {
      x: event.clientX - rect.left,
      y: event.clientY - rect.top,
    };
  }

  function bindSignatureCanvas() {
    const canvas = qs('chatSignatureCanvas');

    if (!canvas || canvas.dataset.bound === '1') {
      return;
    }

    canvas.dataset.bound = '1';

    canvas.addEventListener('pointerdown', (event) => {
      event.preventDefault();
      canvas.setPointerCapture(event.pointerId);
      signaturePad.drawing = true;
      signaturePad.lastPoint = signaturePoint(event);
    });

    canvas.addEventListener('pointermove', (event) => {
      if (!signaturePad.drawing) {
        return;
      }

      event.preventDefault();
      const context = signatureCanvasContext();
      const point = signaturePoint(event);

      if (!context) {
        return;
      }

      context.beginPath();
      context.moveTo(signaturePad.lastPoint.x, signaturePad.lastPoint.y);
      context.lineTo(point.x, point.y);
      context.stroke();
      signaturePad.lastPoint = point;
      signaturePad.hasInk = true;
    });

    ['pointerup', 'pointercancel', 'pointerleave'].forEach((eventName) => {
      canvas.addEventListener(eventName, () => {
        signaturePad.drawing = false;
        signaturePad.lastPoint = null;
      });
    });
  }

  async function openSignatureModal(contractId, party) {
    ensureSignatureModal();

    signaturePad.contractId = Number(contractId);
    signaturePad.party = party;
    qs('chatSignatureModal')?.classList.remove('is-view-only');

    if (!state.contractDetail || Number(state.contractDetail.contract?.id) !== Number(contractId)) {
      state.contractDetail = await ContractAPI.detail(contractId);
    }

    qs('chatSignatureContractPreview').innerHTML = contractA4Document(state.contractDetail);
    qs('chatSignatureName').value = currentUserName();
    qs('chatSignatureModal').classList.remove('hidden');
    window.setTimeout(resetSignatureCanvas, 0);
  }

  async function openContractViewModal(contractId) {
    ensureSignatureModal();

    signaturePad.contractId = null;
    signaturePad.party = null;
    signaturePad.drawing = false;

    if (!state.contractDetail || Number(state.contractDetail.contract?.id) !== Number(contractId)) {
      state.contractDetail = await ContractAPI.detail(contractId);
    }

    qs('chatSignatureContractPreview').innerHTML = contractA4Document(state.contractDetail);
    qs('chatSignatureModal')?.classList.add('is-view-only');
    qs('chatSignatureModal')?.classList.remove('hidden');
  }

  function closeSignatureModal() {
    qs('chatSignatureModal')?.classList.add('hidden');
    qs('chatSignatureModal')?.classList.remove('is-view-only');
    signaturePad.contractId = null;
    signaturePad.party = null;
    signaturePad.drawing = false;
  }

  async function submitSignatureFromModal() {
    const canvas = qs('chatSignatureCanvas');
    const name = qs('chatSignatureName')?.value.trim() || currentUserName();

    if (!signaturePad.hasInk) {
      alert('Vui lòng ký vào ô chữ ký trước khi hoàn tất.');
      return;
    }

    await ContractAPI.sign({
      contract_id: signaturePad.contractId,
      company_id: companyId(),
      signed_by: wpUserId(),
      party: signaturePad.party,
      signed_name: name,
      signature_data: canvas.toDataURL('image/png'),
    });

    closeSignatureModal();
    await openConversation(state.activeId);
    notifyChatSocket({ type: 'contract_signed' });
    emitRealtimeEvent('b2b:chat:changed', { rfq_id: state.activeId, type: 'contract_signed' });
  }

  return {
    openSignatureModal,
    openContractViewModal,
  };
}
