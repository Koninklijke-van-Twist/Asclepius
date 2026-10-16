import {
  addTicketMessage,
  adoptLiveAsclepiusHandoff,
  apiPost,
  changeTicketStatus,
  createTicket,
  fetchSessionContext,
  fetchTicketThread,
  pollTickets,
  setStoredApiKey,
  storedViewerEmail,
  type PollResponse,
  type SessionContext,
  type Ticket,
} from '../lib/api';

type AppState = {
  session: SessionContext | null;
  tickets: Ticket[];
  selectedId: number;
  signature: string;
  page: number;
  totalPages: number;
  search: string;
  status: string;
  error: string;
  notice: string;
  loading: boolean;
  isAdminPortal: boolean;
};

function el<K extends keyof HTMLElementTagNameMap>(
  tag: K,
  attrs: Record<string, string> = {},
  text?: string
): HTMLElementTagNameMap[K] {
  const node = document.createElement(tag);
  for (const [key, value] of Object.entries(attrs)) {
    if (key === 'class') node.className = value;
    else node.setAttribute(key, value);
  }
  if (text !== undefined) node.textContent = text;
  return node;
}

function labeled(labelText: string, control: HTMLElement): HTMLLabelElement {
  const label = el('label');
  label.append(labelText, control);
  return label;
}

export function mountTicketApp(root: HTMLElement): void {
  const state: AppState = {
    session: null,
    tickets: [],
    selectedId: 0,
    signature: '',
    page: 1,
    totalPages: 1,
    search: '',
    status: '',
    error: '',
    notice: '',
    loading: false,
    isAdminPortal: true,
  };

  const shell = el('div', { class: 'stack' });
  const statusBar = el('div', { class: 'panel row' });
  const controls = el('div', { class: 'panel row' });
  const split = el('div', { class: 'split' });
  const listPanel = el('div', { class: 'panel stack' });
  const detailPanel = el('div', { class: 'panel stack' });
  split.append(listPanel, detailPanel);
  shell.append(statusBar, controls, split);
  root.replaceChildren(shell);

  const apiKeyInput = el('input', {
    type: 'password',
    placeholder: 'API-key (service of sessie)',
    autocomplete: 'off',
  }) as HTMLInputElement;
  const saveKeyBtn = el('button', { class: 'btn', type: 'button' }, 'Key opslaan');
  const refreshBtn = el('button', { class: 'btn', type: 'button' }, 'Vernieuwen');
  const portalToggle = el('button', { class: 'btn', type: 'button' }, 'ICT-portaal');
  statusBar.append(
    el('span', { class: 'muted' }, 'Sessie / key'),
    apiKeyInput,
    saveKeyBtn,
    refreshBtn,
    portalToggle
  );

  const searchInput = el('input', { type: 'search', placeholder: 'Zoeken…' }) as HTMLInputElement;
  const statusSelect = el('select') as HTMLSelectElement;
  statusSelect.append(new Option('Alle statussen', ''));
  controls.append(searchInput, statusSelect);

  saveKeyBtn.addEventListener('click', () => {
    setStoredApiKey(apiKeyInput.value.trim());
    state.notice = 'API-key opgeslagen in deze browser.';
    void boot();
  });
  refreshBtn.addEventListener('click', () => void loadTickets());
  portalToggle.addEventListener('click', () => {
    state.isAdminPortal = !state.isAdminPortal;
    portalToggle.textContent = state.isAdminPortal ? 'ICT-portaal' : 'Gebruikersportaal';
    void loadTickets();
  });
  searchInput.addEventListener('change', () => {
    state.search = searchInput.value.trim();
    state.page = 1;
    void loadTickets();
  });
  statusSelect.addEventListener('change', () => {
    state.status = statusSelect.value;
    state.page = 1;
    void loadTickets();
  });

  function viewerEmail(): string {
    return state.session?.viewer?.email || storedViewerEmail() || '';
  }

  function csrf(): string {
    return state.session?.csrf_token || '';
  }

  function basePollBody(): Record<string, unknown> {
    const admin = !!(state.session?.viewer?.is_admin || state.isAdminPortal);
    return {
      viewer_email: viewerEmail(),
      user_is_admin: admin,
      is_admin_portal: state.isAdminPortal && admin,
      can_manage_tickets: state.isAdminPortal && admin,
      current_language: state.session?.viewer?.language || 'nl',
      csrf_token: csrf(),
      open_ticket_id: state.selectedId,
      view: state.isAdminPortal ? 'overview' : 'my_tickets',
      page: state.page,
      search_query: state.search,
      status_filters: state.status ? [state.status] : [],
      status_filters_selected: state.status ? [state.status] : [],
      status_filter_active: !!state.status,
      last_signature: state.signature,
      format: 'json',
    };
  }

  function renderStatus(): void {
    statusBar.querySelectorAll('[data-dynamic]').forEach((n) => n.remove());
    const viewer = state.session?.viewer;
    statusBar.append(
      el(
        'span',
        { class: 'muted', 'data-dynamic': '1' },
        viewer?.email
          ? `${viewer.name || viewer.email}${viewer.is_admin ? ' · ICT' : ''}`
          : 'Geen viewer — zet een API-key of log in via PHP.'
      )
    );
    if (state.error) statusBar.append(el('span', { class: 'error', 'data-dynamic': '1' }, state.error));
    if (state.notice) statusBar.append(el('span', { class: 'success', 'data-dynamic': '1' }, state.notice));
    if (state.loading) statusBar.append(el('span', { class: 'muted', 'data-dynamic': '1' }, 'Laden…'));
  }

  function renderList(): void {
    listPanel.replaceChildren(el('h2', {}, 'Tickets'));
    if (!state.tickets.length) {
      listPanel.append(el('p', { class: 'muted' }, 'Geen tickets.'));
      return;
    }
    const list = el('div', { class: 'ticket-list' });
    for (const ticket of state.tickets) {
      const card = el('article', {
        class: `ticket-card${ticket.id === state.selectedId ? ' active' : ''}`,
        tabindex: '0',
        role: 'button',
      });
      card.append(el('div', { class: 'ticket-title' }, `#${ticket.id} ${ticket.title}`));
      const meta = el('div', { class: 'row' });
      const badge = el('span', { class: 'badge' }, ticket.status_label || ticket.status);
      badge.style.background = ticket.status_color || '#2563eb';
      meta.append(
        badge,
        el('span', { class: 'muted' }, ticket.category_label || ticket.category),
        el('span', { class: 'muted' }, ticket.assigned_label || 'Niet toegewezen')
      );
      card.append(meta);
      card.addEventListener('click', () => void selectTicket(ticket.id));
      card.addEventListener('keydown', (event) => {
        if (event.key === 'Enter' || event.key === ' ') {
          event.preventDefault();
          void selectTicket(ticket.id);
        }
      });
      list.append(card);
    }
    listPanel.append(list);

    const pager = el('div', { class: 'row' });
    const prev = el('button', { class: 'btn', type: 'button' }, 'Vorige') as HTMLButtonElement;
    const next = el('button', { class: 'btn', type: 'button' }, 'Volgende') as HTMLButtonElement;
    prev.disabled = state.page <= 1;
    next.disabled = state.page >= state.totalPages;
    prev.addEventListener('click', () => {
      state.page = Math.max(1, state.page - 1);
      void loadTickets();
    });
    next.addEventListener('click', () => {
      state.page += 1;
      void loadTickets();
    });
    pager.append(prev, el('span', { class: 'muted' }, `Pagina ${state.page} / ${state.totalPages}`), next);
    listPanel.append(pager);
  }

  function renderDetail(): void {
    detailPanel.replaceChildren(el('h2', {}, 'Ticket'));
    const ticket = state.tickets.find((t) => t.id === state.selectedId);
    if (!ticket) {
      detailPanel.append(el('p', { class: 'muted' }, 'Selecteer een ticket.'));
      return;
    }

    detailPanel.append(el('h3', {}, `#${ticket.id} ${ticket.title}`));
    detailPanel.append(
      el(
        'p',
        { class: 'muted' },
        `${ticket.status_label || ticket.status} · ${ticket.category_label || ticket.category} · ${
          ticket.requester_label || ticket.user_email || ''
        }`
      )
    );
    if (ticket.description) detailPanel.append(el('p', {}, ticket.description));

    const statusForm = el('div', { class: 'row' });
    const statusInput = el('select') as HTMLSelectElement;
    const statuses =
      state.session?.lookups?.statuses ||
      ['ingediend', 'in behandeling', 'afwachtende op gebruiker', 'afgehandeld'];
    for (const status of statuses) {
      statusInput.append(new Option(status, status, status === ticket.status, status === ticket.status));
    }
    const statusBtn = el('button', { class: 'btn btn-primary', type: 'button' }, 'Status wijzigen');
    statusBtn.addEventListener('click', async () => {
      try {
        state.loading = true;
        renderStatus();
        const result = await changeTicketStatus({
          ticket_id: ticket.id,
          status: statusInput.value,
          csrf_token: csrf(),
          viewer_email: viewerEmail(),
          is_admin_portal: state.isAdminPortal,
          user_is_admin: !!state.session?.viewer?.is_admin,
        });
        if (!result.success) throw new Error(String(result.error || 'status_failed'));
        state.notice = 'Status bijgewerkt.';
        state.signature = '';
        await loadTickets();
        await selectTicket(ticket.id);
      } catch (error) {
        state.error = error instanceof Error ? error.message : String(error);
      } finally {
        state.loading = false;
        renderStatus();
      }
    });
    statusForm.append(statusInput, statusBtn);
    detailPanel.append(statusForm);

    const messagesWrap = el('div', { class: 'messages' });
    for (const message of ticket.messages || []) {
      const box = el('article', { class: `message${message.is_ghost ? ' ghost' : ''}` });
      box.append(
        el(
          'div',
          { class: 'message-meta' },
          `${message.sender_name || message.sender_email} · ${message.created_at_label || message.created_at || ''}`
        )
      );
      box.append(el('div', {}, message.message_text || ''));
      messagesWrap.append(box);
    }
    detailPanel.append(messagesWrap);

    const reply = el('textarea', { placeholder: 'Antwoord…' }) as HTMLTextAreaElement;
    const replyBtn = el('button', { class: 'btn btn-primary', type: 'button' }, 'Verstuur');
    replyBtn.addEventListener('click', async () => {
      const text = reply.value.trim();
      if (!text) return;
      try {
        state.loading = true;
        renderStatus();
        const result = await addTicketMessage({
          ticket_id: ticket.id,
          message_text: text,
          sender_email: viewerEmail(),
          csrf_token: csrf(),
          is_admin_portal: state.isAdminPortal,
        });
        if (!result.success) throw new Error(String(result.error || 'message_failed'));
        reply.value = '';
        state.notice = 'Bericht geplaatst.';
        state.signature = '';
        await selectTicket(ticket.id);
        await loadTickets();
      } catch (error) {
        state.error = error instanceof Error ? error.message : String(error);
      } finally {
        state.loading = false;
        renderStatus();
      }
    });
    detailPanel.append(reply, replyBtn);
  }

  async function selectTicket(ticketId: number): Promise<void> {
    state.selectedId = ticketId;
    state.error = '';
    try {
      const thread = await fetchTicketThread(ticketId, basePollBody());
      if (thread.ticket) {
        const idx = state.tickets.findIndex((t) => t.id === ticketId);
        const merged = { ...thread.ticket, messages: thread.messages || thread.ticket.messages };
        if (idx >= 0) state.tickets[idx] = { ...state.tickets[idx], ...merged };
        else state.tickets.unshift(merged);
      } else if (thread.messages) {
        const idx = state.tickets.findIndex((t) => t.id === ticketId);
        if (idx >= 0) state.tickets[idx] = { ...state.tickets[idx], messages: thread.messages };
      }
    } catch (error) {
      state.error = error instanceof Error ? error.message : String(error);
    }
    renderList();
    renderDetail();
    renderStatus();
  }

  async function loadTickets(): Promise<void> {
    state.loading = true;
    state.error = '';
    renderStatus();
    try {
      const data: PollResponse = await pollTickets(basePollBody());
      if (data.unchanged) {
        state.notice = 'Geen wijzigingen.';
      } else {
        state.tickets = data.tickets || [];
        state.signature = data.signature || '';
        state.page = data.page || 1;
        state.totalPages = data.total_pages || 1;
        if (state.selectedId && !state.tickets.some((t) => t.id === state.selectedId)) {
          state.selectedId = state.tickets[0]?.id || 0;
        }
      }
    } catch (error) {
      state.error = error instanceof Error ? error.message : String(error);
    } finally {
      state.loading = false;
      renderList();
      renderDetail();
      renderStatus();
    }
  }

  async function boot(): Promise<void> {
    state.loading = true;
    renderStatus();
    try {
      const handoff = adoptLiveAsclepiusHandoff();
      if (handoff.apiKey) {
        apiKeyInput.value = handoff.apiKey;
        state.notice = 'Live Asclepius-sessie gekoppeld (TicketStore).';
      }
      state.session = await fetchSessionContext({ current_language: 'nl' });
      const statuses = state.session.lookups?.statuses || [];
      statusSelect.replaceChildren(new Option('Alle statussen', ''));
      for (const status of statuses) statusSelect.append(new Option(status, status));
    } catch (error) {
      state.error = error instanceof Error ? error.message : String(error);
    }
    await loadTickets();
  }

  void boot();
  window.setInterval(() => {
    void loadTickets();
  }, 20000);
}

export function mountNewTicketForm(root: HTMLElement): void {
  const form = el('form', { class: 'panel stack' });
  const title = el('input', { required: 'true', name: 'title' }) as HTMLInputElement;
  const category = el('select', { name: 'category', required: 'true' }) as HTMLSelectElement;
  const description = el('textarea', { name: 'description', required: 'true' }) as HTMLTextAreaElement;
  const email = el('input', { type: 'email', name: 'user_email', required: 'true' }) as HTMLInputElement;
  const submit = el('button', { class: 'btn btn-primary', type: 'submit' }, 'Ticket aanmaken');
  const feedback = el('p', { class: 'muted' }, '');

  form.append(
    el('h2', {}, 'Nieuw ticket'),
    labeled('Titel', title),
    labeled('Categorie', category),
    labeled('Beschrijving', description),
    labeled('Aanvrager e-mail', email),
    submit,
    feedback
  );
  root.replaceChildren(form);

  void fetchSessionContext({ current_language: 'nl' })
    .then((session) => {
      const cats = session.lookups?.categories || [];
      category.replaceChildren();
      for (const c of cats) category.append(new Option(c, c));
      if (session.viewer?.email) email.value = session.viewer.email;
      form.dataset.csrf = session.csrf_token || '';
    })
    .catch((error) => {
      feedback.className = 'error';
      feedback.textContent = error instanceof Error ? error.message : String(error);
    });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    feedback.className = 'muted';
    feedback.textContent = 'Bezig…';
    try {
      const result = await createTicket({
        title: title.value.trim(),
        category: category.value,
        description: description.value.trim(),
        user_email: email.value.trim(),
        csrf_token: form.dataset.csrf || '',
      });
      if (!result.success) {
        throw new Error((result.errors && result.errors.join(', ')) || result.error || 'create_failed');
      }
      feedback.className = 'success';
      feedback.textContent = `Ticket #${result.ticket_id} aangemaakt.`;
      title.value = '';
      description.value = '';
    } catch (error) {
      feedback.className = 'error';
      feedback.textContent = error instanceof Error ? error.message : String(error);
    }
  });
}

export async function mountApiPanel(
  root: HTMLElement,
  action: string,
  title: string,
  bodyBuilder?: () => Record<string, unknown>
): Promise<void> {
  const panel = el('div', { class: 'panel stack' });
  panel.append(el('h2', {}, title));
  const pre = el('pre', { class: 'muted' }, 'Laden…');
  const refresh = el('button', { class: 'btn', type: 'button' }, 'Vernieuwen');
  panel.append(refresh, pre);
  root.replaceChildren(panel);

  async function load(): Promise<void> {
    pre.textContent = 'Laden…';
    try {
      const data = await apiPost(action, bodyBuilder ? bodyBuilder() : { format: 'json' });
      pre.textContent = JSON.stringify(data, null, 2);
    } catch (error) {
      pre.textContent = error instanceof Error ? error.message : String(error);
    }
  }

  refresh.addEventListener('click', () => void load());
  await load();
}
