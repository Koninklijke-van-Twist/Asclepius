export type ApiViewer = {
  email: string;
  name?: string;
  is_admin?: boolean;
  can_manage_tickets?: boolean;
  user_is_admin?: boolean;
  is_admin_portal?: boolean;
  language?: string;
};

export type TicketMessage = {
  id: number;
  ticket_id?: number;
  sender_email: string;
  sender_name?: string;
  sender_role?: string;
  sender_title?: string;
  message_text: string;
  created_at?: string;
  created_at_label?: string;
  is_ghost?: boolean;
  is_ai_assistant?: boolean;
  attachments?: Array<{
    id: number;
    original_name: string;
    mime_type?: string;
    file_size?: number;
  }>;
  reactions?: Record<string, unknown>;
};

export type Ticket = {
  id: number;
  title: string;
  description?: string;
  status: string;
  status_label?: string;
  status_color?: string;
  category: string;
  category_label?: string;
  priority?: number;
  priority_label?: string;
  priority_color?: string;
  assigned_email?: string;
  assigned_label?: string;
  assigned_name?: string;
  user_email?: string;
  user_name?: string;
  requester_label?: string;
  due_date?: string;
  due_date_label?: string;
  created_at?: string;
  updated_at?: string;
  created_at_label?: string;
  updated_at_label?: string;
  message_count?: number;
  is_private?: boolean;
  messages?: TicketMessage[];
};

export type PollResponse = {
  success: boolean;
  unchanged?: boolean;
  signature?: string;
  format?: string;
  tickets?: Ticket[];
  is_empty?: boolean;
  empty_message?: string;
  page?: number;
  per_page?: number;
  total_pages?: number;
  total_count?: number;
  pagination?: Record<string, unknown>;
  viewer?: ApiViewer;
  error?: string;
};

export type SessionContext = {
  success: boolean;
  viewer: ApiViewer;
  csrf_token: string;
  lookups?: {
    categories?: string[];
    statuses?: string[];
  };
  supported_languages?: string[];
  ui?: {
    astro_base?: string;
    legacy_index?: string;
    legacy_admin?: string;
  };
  error?: string;
};

const API_URL =
  (typeof import.meta !== 'undefined' &&
    (import.meta as ImportMeta & { env?: Record<string, string> }).env?.PUBLIC_ASCLEPIUS_API_URL) ||
  '/asclepius/api.php';

/** Astro is hosted under /astropius; the PHP API stays under /asclepius. */

function storedApiKey(): string {
  if (typeof localStorage === 'undefined') return '';
  return localStorage.getItem('asclepius.api_key') || '';
}

export function setStoredApiKey(key: string): void {
  if (typeof localStorage === 'undefined') return;
  if (key) localStorage.setItem('asclepius.api_key', key);
  else localStorage.removeItem('asclepius.api_key');
}

export async function apiPost<T = Record<string, unknown>>(
  action: string,
  body: Record<string, unknown> = {}
): Promise<T> {
  const apiKey = storedApiKey();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (apiKey) headers['X-API-Key'] = apiKey;

  const response = await fetch(API_URL, {
    method: 'POST',
    credentials: 'include',
    headers,
    body: JSON.stringify({ action, format: 'json', ...body }),
  });

  const data = (await response.json()) as T & { error?: string; success?: boolean };
  if (!response.ok) {
    throw new Error(data.error || `HTTP ${response.status}`);
  }
  return data;
}

export async function fetchSessionContext(
  extras: Record<string, unknown> = {}
): Promise<SessionContext> {
  return apiPost<SessionContext>('session_context', extras);
}

export async function pollTickets(
  params: Record<string, unknown>
): Promise<PollResponse> {
  return apiPost<PollResponse>('ticket_poll', params);
}

export async function fetchTicketThread(
  ticketId: number,
  params: Record<string, unknown> = {}
): Promise<{ success: boolean; ticket?: Ticket; messages?: TicketMessage[]; error?: string }> {
  return apiPost('ticket_thread', { ticket_id: ticketId, ...params });
}

export async function createTicket(payload: {
  title: string;
  category: string;
  description: string;
  user_email: string;
  priority?: number;
  csrf_token?: string;
}): Promise<{ success: boolean; ticket_id?: number; error?: string; errors?: string[] }> {
  const apiKey = storedApiKey();
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
    Accept: 'application/json',
  };
  if (apiKey) headers['X-API-Key'] = apiKey;

  const response = await fetch(API_URL, {
    method: 'POST',
    credentials: 'include',
    headers,
    body: JSON.stringify(payload),
  });
  return response.json();
}

export async function addTicketMessage(payload: {
  ticket_id: number;
  message_text: string;
  sender_email?: string;
  csrf_token?: string;
  is_admin_portal?: boolean;
}): Promise<Record<string, unknown>> {
  return apiPost('add_ticket_message', payload);
}

export async function changeTicketStatus(payload: {
  ticket_id: number;
  status: string;
  csrf_token?: string;
  viewer_email?: string;
  is_admin_portal?: boolean;
  user_is_admin?: boolean;
}): Promise<Record<string, unknown>> {
  return apiPost('change_ticket_status', payload);
}
