import { SupportAPI } from '../../api/support.js';

function toPositiveInt(value) {
  const number = Number(value || 0);

  if (!Number.isFinite(number) || number <= 0) {
    return 0;
  }

  return Math.trunc(number);
}

function normalizeKind(value) {
  const kind = String(value || '').trim().toLowerCase();

  if (['rfq', 'bulk', 'contract', 'order'].includes(kind)) {
    return kind;
  }

  return '';
}

function normalizeContext(rawContext = {}, ticket = null) {
  const context = rawContext && typeof rawContext === 'object' ? rawContext : {};

  return {
    kind: normalizeKind(context.kind),
    order_id: toPositiveInt(context.order_id || ticket?.order_id),
    contract_id: toPositiveInt(context.contract_id),
    rfq_id: toPositiveInt(context.rfq_id || ticket?.rfq_id),
    bulk_id: toPositiveInt(context.bulk_id || ticket?.bulk_id),
    reference_label: String(context.reference_label || '').trim(),
    is_contract_ticket: Boolean(context.is_contract_ticket),
    is_order_ticket: Boolean(context.is_order_ticket),
  };
}

export function createSupportTicketContext({ state }) {
  function clearActiveTicketContext() {
    state.activeTicketContext = null;
  }

  function activeContext(ticket = state.activeTicket) {
    if (!state.activeTicketContext) {
      return normalizeContext({}, ticket);
    }

    return state.activeTicketContext;
  }

  async function hydrateActiveTicketContext(ticketId, ticket = state.activeTicket) {
    const id = toPositiveInt(ticketId);

    if (id <= 0) {
      clearActiveTicketContext();
      return activeContext(ticket);
    }

    try {
      const context = await SupportAPI.ticketContext(id);
      state.activeTicketContext = normalizeContext(context, ticket);
    } catch (error) {
      state.activeTicketContext = normalizeContext({}, ticket);
    }

    return state.activeTicketContext;
  }

  function supportTicketOrderId(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return toPositiveInt(context.order_id || ticket?.order_id);
  }

  function supportTicketContractId(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return toPositiveInt(context.contract_id);
  }

  function supportTicketRfqId(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return toPositiveInt(context.rfq_id || ticket?.rfq_id);
  }

  function supportTicketBulkId(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return toPositiveInt(context.bulk_id || ticket?.bulk_id);
  }

  function supportTicketMetaKind(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    if (context.kind) {
      return context.kind;
    }

    if (supportTicketBulkId(ticket) > 0) {
      return 'bulk';
    }

    if (supportTicketContractId(ticket) > 0) {
      return 'contract';
    }

    if (supportTicketOrderId(ticket) > 0) {
      return 'order';
    }

    if (supportTicketRfqId(ticket) > 0) {
      return 'rfq';
    }

    return '';
  }

  function supportTicketReferenceLabel(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    if (context.reference_label) {
      return context.reference_label;
    }

    const rfqId = supportTicketRfqId(ticket);
    const bulkId = supportTicketBulkId(ticket);
    const orderId = supportTicketOrderId(ticket);
    const contractId = supportTicketContractId(ticket);

    if (rfqId > 0) {
      return `RFQ #${rfqId}`;
    }

    if (bulkId > 0) {
      return `Bulk #${bulkId}`;
    }

    if (orderId > 0) {
      return `Order #${orderId}`;
    }

    if (contractId > 0) {
      return `Contract #${contractId}`;
    }

    return 'Khong co';
  }

  function isContractSupportTicket(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return Boolean(context.is_contract_ticket || supportTicketMetaKind(ticket) === 'contract');
  }

  function isOrderSupportTicket(ticket = state.activeTicket) {
    const context = activeContext(ticket);

    return Boolean(context.is_order_ticket || supportTicketMetaKind(ticket) === 'order');
  }

  return {
    clearActiveTicketContext,
    hydrateActiveTicketContext,
    supportTicketOrderId,
    supportTicketContractId,
    supportTicketRfqId,
    supportTicketBulkId,
    supportTicketMetaKind,
    supportTicketReferenceLabel,
    isContractSupportTicket,
    isOrderSupportTicket,
  };
}
