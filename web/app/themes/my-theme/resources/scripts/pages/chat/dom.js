export function qs(id) {
  return document.getElementById(id);
}

export function createChatDom({ shouldStickToBottom }) {
  function setConversationList(html) {
    const list = qs('chatConversationList');

    if (!list) return;


    if (list.__b2bLastHtml === html) {
      return;
    }

    const previousScrollTop = list.scrollTop;

    list.innerHTML = html;
    list.__b2bLastHtml = html;

    window.requestAnimationFrame(() => {
      list.scrollTop = Math.min(previousScrollTop, list.scrollHeight);
    });
  }

  function setDealPanelHtml(html) {
    const panel = qs('chatDealPanel');

    if (!panel) return;
    if (panel.__b2bLastHtml === html) {
      return;
    }

    const previousScrollTop = panel.scrollTop;

    panel.innerHTML = html;
    panel.__b2bLastHtml = html;

    window.requestAnimationFrame(() => {
      panel.scrollTop = Math.min(previousScrollTop, panel.scrollHeight);
    });
  }

  function setMessageBoxHtml(html, options = {}) {
    const messageBox = qs('chatMessages');

    if (!messageBox) return false;

    if (messageBox.__b2bLastHtml === html) {
      return false;
    }

    const stickToBottom = options.forceScroll || shouldStickToBottom(messageBox);

    messageBox.innerHTML = html;
    messageBox.__b2bLastHtml = html;

    if (stickToBottom) {
      window.requestAnimationFrame(() => {
        messageBox.scrollTop = messageBox.scrollHeight;
      });
    }

    return true;
  }

  return {
    setConversationList,
    setDealPanelHtml,
    setMessageBoxHtml,
  };
}
