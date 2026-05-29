export function createInitialSupportState() {
  return {
  tickets: [],
  bulkRequests: [],
  loading: false,
  bulkLoading: false,
  payoutLoading: false,
  sellerChannelLoading: false,
  supportAgentsLoading: false,
  formDirty: false,
  pendingPayouts: [],
  supportAgents: [],
  sellerChannel: {
    ticket: null,
    messages: [],
  },

  activeAdminId: null,     
  adminList: [],          
  currentSubTab: 'tickets',
  unreadCounts: {},         
  lastMessagesTimestamps: {},
  access: {
    hydrated: false,
    defaultView: 'help',
    roles: [],
    flags: {
      is_admin: false,
      is_support: false,
      is_seller: false,
      is_buyer: false,
      can_manage_support_workspace: false,
      can_open_seller_channel: false,
    },
  },

};
}
