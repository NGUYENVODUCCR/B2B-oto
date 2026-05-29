export function createInitialChatState() {
  return {
  profile: null,
  conversations: [],
  filtered: [],
  activeId: null,
  activeBulkId: null,
  activeBulk: null,
  activeSupportTicketId: null,
  activeTicket: null,
  rfqDetail: null,
  messages: [],
  quotations: [],
  contractDetail: null,
  orderDetail: null,
  reviewDetail: null,
  messagePoll: null,
  supportRequest: null,
  activeTicketContext: null,
  conversationPoll: null,
  chatSocket: null,
  conversationRefreshInFlight: false,
  activeRefreshInFlight: false,
  lastMessageKey: '',
  formDirty: false,
  formSubmitInFlight: false,
  };
}

export const BULK_SELLER_CHANNEL_STATUSES = new Set(['published', 'fulfilled', 'buyer_notified', 'accepted']);
