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

/**
 * Live TicketStore access:
 * - /astropius/api.php → PHP bridge that requires Asclepius api.php (same SQLite)
 * - fallback /asclepius/api.php when bridge is unavailable
 */
function resolveApiUrl(): string {
  const envUrl =
    typeof import.meta !== 'undefined'
      ? (import.meta as ImportMeta & { env?: Record<string, string> }).env?.PUBLIC_ASCLEPIUS_API_URL
      : undefined;
  if (envUrl) return envUrl;

  if (typeof location !== 'undefined') {
    if (location.pathname.startsWith('/astropius')) {
      return `${location.origin}/astropius/api.php`;
    }
  }

  return '/asclepius/api.php';
}

function readCookie(name: string): string {
  if (typeof document === 'undefined') return '';
  const prefix = `${name}=`;
  for (const part of document.cookie.split(';')) {
    const trimmed = part.trim();
    if (trimmed.startsWith(prefix)) {
      return decodeURIComponent(trimmed.slice(prefix.length));
    }
  }
  return '';
}

/** Pull Asclepius handoff cookies (from /asclepius/astropius_launch.php) into localStorage. */
export function adoptLiveAsclepiusHandoff(): { apiKey: string; email: string } {
  const apiKey = readCookie('asclepius_astropius_key');
  const email = readCookie('asclepius_astropius_email');
  if (apiKey) setStoredApiKey(apiKey);
  if (email && typeof localStorage !== 'undefined') {
    localStorage.setItem('asclepius.viewer_email', email);
  }
  return { apiKey, email };
}

function storedApiKey(): string {
  if (typeof localStorage === 'undefined') return '';
  const stored = localStorage.getItem('asclepius.api_key') || '';
  if (stored) return stored;
  return readCookie('asclepius_astropius_key');
}

export function storedViewerEmail(): string {
  if (typeof localStorage === 'undefined') return '';
  return localStorage.getItem('asclepius.viewer_email') || readCookie('asclepius_astropius_email') || '';
}

export function setStoredApiKey(key: string): void {
  if (typeof localStorage === 'undefined') return;
  if (key) localStorage.setItem('asclepius.api_key', key);
  else localStorage.removeItem('asclepius.api_key');
}

async function apiFetch<T>(
  init: RequestInit & { bodyObject?: Record<string, unknown> }
): Promise<T> {
  const apiKey = storedApiKey();
  const headers: Record<string, string> = {
    Accept: 'application/json',
    ...(init.headers as Record<string, string> | undefined),
  };
  if (apiKey) headers['X-API-Key'] = apiKey;

  let body = init.body;
  if (init.bodyObject) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(init.bodyObject);
  }

  const response = await fetch(resolveApiUrl(), {
    ...init,
    credentials: 'include',
    headers,
    body,
  });

  const data = (await response.json()) as T & { error?: string; success?: boolean };
  if (!response.ok) {
    throw new Error(data.error || `HTTP ${response.status}`);
  }
  return data;
}

export async function apiPost<T = Record<string, unknown>>(
  action: string,
  body: Record<string, unknown> = {}
): Promise<T> {
  return apiFetch<T>({
    method: 'POST',
    bodyObject: { action, format: 'json', ...body },
  });
}

export async function fetchSessionContext(
  extras: Record<string, unknown> = {}
): Promise<SessionContext> {
  adoptLiveAsclepiusHandoff();
  const email = storedViewerEmail();
  return apiPost<SessionContext>('session_context', {
    current_language: 'nl',
    ...(email ? { viewer_email: email } : {}),
    ...extras,
  });
}

export async function pollTickets(
  params: Record<string, unknown>
): Promise<PollResponse> {
  const email = storedViewerEmail();
  return apiPost<PollResponse>('ticket_poll', {
    ...(email ? { viewer_email: email } : {}),
    ...params,
  });
}

export async function fetchTicketThread(
  ticketId: number,
  params: Record<string, unknown> = {}
): Promise<{ success: boolean; ticket?: Ticket; messages?: TicketMessage[]; error?: string }> {
  return apiPost('ticket_thread', { ticket_id: ticketId, format: 'json', ...params });
}

export async function createTicket(payload: {
  title: string;
  category: string;
  description: string;
  user_email: string;
  priority?: number;
  csrf_token?: string;
}): Promise<{ success: boolean; ticket_id?: number; error?: string; errors?: string[] }> {
  return apiFetch({
    method: 'POST',
    bodyObject: payload,
  });
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
