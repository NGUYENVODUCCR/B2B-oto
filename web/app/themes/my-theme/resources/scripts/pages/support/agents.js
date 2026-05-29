export function createSupportAgentLoader({ state, SupportAPI, escapeHtml }) {
  function renderSupportAgentOptions() {
    const select = document.querySelector('[data-support-agent-select]');

    if (!select) return;

    const options = Array.isArray(state.supportAgents) ? state.supportAgents : [];
    const currentValue = String(select.value || '');
    const baseOptionLabel = state.supportAgentsLoading
      ? 'Đang tải danh sách support...'
      : 'Tự động phân công support';

    const optionRows = [
      `<option value="">${escapeHtml(baseOptionLabel)}</option>`,
      ...options.map((agent) => {
        const roleLabel = String(agent.role || '').toLowerCase() === 'admin' ? 'Admin' : 'Support';
        return `<option value="${escapeHtml(agent.id)}">${escapeHtml(agent.name || `Support #${agent.id}`)} (${escapeHtml(roleLabel)})</option>`;
      }),
    ];

    select.innerHTML = optionRows.join('');
    select.disabled = state.supportAgentsLoading;

    const validIds = new Set(options.map((agent) => String(agent.id || '')));

    if (currentValue && validIds.has(currentValue)) {
      select.value = currentValue;
    }
  }

  async function loadSupportAgents() {
    const select = document.querySelector('[data-support-agent-select]');

    if (!select || state.supportAgentsLoading) return;

    state.supportAgentsLoading = true;
    renderSupportAgentOptions();

    try {
      const rows = await SupportAPI.supportAgents();
      state.supportAgents = Array.isArray(rows) ? rows : [];
    } catch (error) {
      state.supportAgents = [];
    } finally {
      state.supportAgentsLoading = false;
      renderSupportAgentOptions();
    }
  }

  return {
    loadSupportAgents,
    renderSupportAgentOptions,
  };
}
