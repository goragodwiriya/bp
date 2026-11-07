/**
 * modules/bp/admin.js
 *
 * Loaded automatically by index.php scanning modules/; no central file needs editing,
 * and always before js/main.js, so it catches the router:initialized event.
 *
 * This file should hold only three things: routes, data-on-load handlers and global formatters.
 * Everything else (tables, forms, autocomplete, modals) the framework does via data-*.
 */

EventManager.on('router:initialized', () => {
  bpSyncSetup();
  bpInstallSetup();

  // This app's home page IS the bp dashboard, so the module takes over '/'.
  // RouterManager.routes is a Map and this runs after js/main.js has registered
  // its own routes, so re-registering the same path replaces it. Doing it here
  // rather than editing js/main.js keeps that file identical to adminframework.
  RouterManager.register('/', {
    template: 'bp/dashboard.html',
    title: '{LNG_Today}',
    requireAuth: true
  });
  RouterManager.register('/bp-family', {
    template: 'bp/family.html',
    title: '{LNG_a family member}',
    menuPath: '/bp-family',
    requireAuth: true
  });
  RouterManager.register('/bp-profile', {
    template: 'bp/profile.html',
    title: '{LNG_a family member}',
    menuPath: '/bp-family',
    requireAuth: true
  });
  RouterManager.register('/bp-record', {
    template: 'bp/record.html',
    title: '{LNG_Record} {LNG_Blood Pressure}',
    menuPath: '/',
    requireAuth: true
  });
  RouterManager.register('/bp-history', {
    template: 'bp/history.html',
    title: '{LNG_History} {LNG_Blood Pressure}',
    menuPath: '/',
    requireAuth: true
  });
  RouterManager.register('/bp-report', {
    template: 'bp/report.html',
    title: '{LNG_Report} {LNG_Blood Pressure}',
    menuPath: '/',
    requireAuth: true
  });
  RouterManager.register('/bp-categories', {
    template: 'bp/categories.html',
    title: '{LNG_Tag}',
    menuPath: '/bp-categories?type=tag',
    requireAuth: true
  });
  RouterManager.register('/bp-care', {
    template: 'bp/care.html',
    title: '{LNG_Health volunteer mode}',
    menuPath: '/bp-care',
    requireAuth: true
  });
  RouterManager.register('/bp-groups', {
    template: 'bp/groups.html',
    title: '{LNG_Groups}',
    menuPath: '/bp-care',
    requireAuth: true
  });
  RouterManager.register('/bp-group', {
    template: 'bp/group.html',
    title: '{LNG_Groups}',
    menuPath: '/bp-care',
    requireAuth: true
  });
  RouterManager.register('/bp-visit', {
    template: 'bp/visit.html',
    title: '{LNG_Visit round}',
    menuPath: '/bp-care',
    requireAuth: true
  });
  RouterManager.register('/bp-about', {
    template: 'bp/about.html',
    title: '{LNG_How to use}',
    menuPath: '/bp-about',
    requireAuth: true
  });
});

/* ==================================================================
 * Dashboard  (route '/')
 * ================================================================== */

/** Thresholds from the server, fetched once and reused for the live verdict */
let bpThresholds = null;

/**
 * Dashboard: pick who to record next, and make the numbers mean something
 * before the user presses Save.
 *
 * @param {HTMLElement} element
 * @param {Object} context
 */
function bpDashboardLoaded(element, context) {
  const form = element.querySelector('form[data-form="bpQuick"]');
  if (!form) {
    return;
  }

  // Pre-select the person the server says is next (nobody recorded today, or
  // the first favourite). One tap less on the phone, every single day.
  const wanted = context && context.data ? context.data.default_id : 0;
  const radios = form.querySelectorAll('input[name="family_id"]');
  let picked = null;
  radios.forEach(r => {
    if (!picked && String(r.value) === String(wanted)) {
      picked = r;
    }
  });
  if (!picked && radios.length === 1) {
    picked = radios[0];
  }
  if (picked) {
    picked.checked = true;
  }
  bpQuickSyncLink(form);
  bpUpdatePendingBadge();
}

/**
 * Keep the "More fields" link pointing at whoever is selected, so switching to
 * the full form does not lose the choice already made.
 *
 * @param {HTMLFormElement} form
 */
function bpQuickSyncLink(form) {
  const link = document.getElementById('bpQuickFull');
  if (!link) {
    return;
  }
  const chosen = form.querySelector('input[name="family_id"]:checked');
  link.href = chosen ? '/bp-record?family_id=' + encodeURIComponent(chosen.value) : '/bp-record';
}

/**
 * Show what the numbers being typed actually mean.
 *
 * The rule mirrors Calculator::bpColor() exactly, including the strict > and <
 * comparisons - 140/90 is orange and 141/90 is red. Thresholds come from the
 * server rather than being hard-coded here, because they are configurable.
 *
 * @param {HTMLFormElement} form
 */
async function bpQuickVerdict(form) {
  const box = document.getElementById('bpQuickVerdict');
  if (!box) {
    return;
  }
  const sys = parseInt(form.querySelector('[name="sys1"]')?.value || '0', 10) || 0;
  const dia = parseInt(form.querySelector('[name="dia1"]')?.value || '0', 10) || 0;
  if (sys <= 0 && dia <= 0) {
    box.hidden = true;
    return;
  }

  if (!bpThresholds) {
    try {
      const res = await ApiService.get('api/bp/home/thresholds');
      bpThresholds = res?.data?.data || res?.data || null;
    } catch (e) {
      bpThresholds = null;
    }
  }
  if (!bpThresholds) {
    box.hidden = true;
    return;
  }
  const t = bpThresholds;

  let color = 'green';
  let label = 'Normal';
  if ((sys > 0 && sys > t.sys_hight) || (dia > 0 && dia > t.dia_hight)) {
    color = 'red';
    label = 'High blood pressure';
  } else if ((sys > 0 && sys > t.sys_max) || (dia > 0 && dia > t.dia_max)) {
    color = 'orange';
    label = 'Pre-high blood pressure';
  } else if ((sys > 0 && sys < t.sys_min) || (dia > 0 && dia < t.dia_min)) {
    color = 'blue';
    label = 'Low blood pressure';
  }

  const referral = (sys > 0 && sys >= t.referral_sys) || (dia > 0 && dia >= t.referral_dia);
  box.className = 'bp-quick-verdict bp-' + color;
  box.textContent = Now.translate(label)
    + (referral ? ' — ' + Now.translate('Refer to a hospital immediately') : '');
  box.hidden = false;
}

/* ==================================================================
 * Install as an app
 * ================================================================== */

/** The deferred beforeinstallprompt event, kept until the user asks to install */
let bpInstallEvent = null;

/**
 * Wire up "Install app".
 *
 * Chromium fires beforeinstallprompt and lets the page show the prompt later.
 * iOS Safari fires nothing at all and has no API, so the only thing that can be
 * done there is telling the user where the button is - which is why the two
 * paths below look so different.
 */
function bpInstallSetup() {
  if (bpInstallSetup.done) {
    return;
  }
  bpInstallSetup.done = true;

  window.addEventListener('beforeinstallprompt', event => {
    // Stop the browser's own mini-infobar so the button is the single way in
    event.preventDefault();
    bpInstallEvent = event;
    bpInstallToggle(true);
  });

  window.addEventListener('appinstalled', () => {
    bpInstallEvent = null;
    bpInstallToggle(false);
    try {
      localStorage.setItem('bp_installed', '1');
    } catch (e) {
      // A browser with storage disabled just gets the button again next time
    }
  });

  document.addEventListener('click', async event => {
    const btn = event.target.closest ? event.target.closest('#bpInstall') : null;
    if (btn) {
      if (!bpInstallEvent) {
        return;
      }
      bpInstallEvent.prompt();
      const choice = await bpInstallEvent.userChoice;
      if (choice && choice.outcome === 'accepted') {
        bpInstallToggle(false);
      }
      // The event can only be used once
      bpInstallEvent = null;
      return;
    }
    if (event.target.closest && event.target.closest('#bpIosDismiss')) {
      const box = document.getElementById('bpIosInstall');
      if (box) {
        box.hidden = true;
      }
      try {
        localStorage.setItem('bp_ios_hint', '1');
      } catch (e) {
        // ignore
      }
    }
  });

  EventManager.on('router:afterEach', () => bpInstallRefresh());
  bpInstallRefresh();
}

/**
 * Show or hide the install button.
 *
 * @param {boolean} show
 */
function bpInstallToggle(show) {
  const btn = document.getElementById('bpInstall');
  if (btn) {
    btn.hidden = !show;
  }
}

/**
 * Decide what to offer on this device, after each navigation.
 *
 * Nothing is offered when the app is already running standalone - the user
 * installed it, so an install button would be nonsense.
 */
function bpInstallRefresh() {
  const standalone = window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true;
  if (standalone) {
    bpInstallToggle(false);
    return;
  }

  if (bpInstallEvent) {
    bpInstallToggle(true);
    return;
  }

  // iOS: no beforeinstallprompt exists, so show the manual instructions once
  const iOS = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  let hinted = false;
  try {
    hinted = localStorage.getItem('bp_ios_hint') === '1';
  } catch (e) {
    hinted = false;
  }
  const box = document.getElementById('bpIosInstall');
  if (box) {
    box.hidden = !(iOS && !hinted);
  }
}

/**
 * Read the person id from the current query string.
 *
 * @returns {string}
 */
function bpCurrentPersonId() {
  try {
    return new URLSearchParams(window.location.search).get('id') || '';
  } catch (e) {
    return '';
  }
}

/**
 * History page: point the Record and Report buttons at the person being viewed.
 *
 * The buttons sit outside the table, so they are wired from the current query string.
 *
 * @param {HTMLElement} element
 * @param {Object} context
 */
function bpHistoryLoaded(element, context) {
  const id = bpCurrentPersonId();
  if (!id) {
    return;
  }
  const record = document.getElementById('bpHistoryRecord');
  const report = document.getElementById('bpHistoryReport');
  if (record) {
    record.setAttribute('href', '/bp-record?family_id=' + encodeURIComponent(id));
  }
  if (report) {
    report.setAttribute('href', '/bp-report?id=' + encodeURIComponent(id));
  }
}

/**
 * Report page: append the id to each chart URL and trigger the load.
 *
 * GraphComponent does not pass the page's query params to data-url
 * (loadData() uses instance.options.url as-is), so the URL is built here.
 * The template leaves data-url unset so no request is wasted without an id.
 *
 * @param {HTMLElement} element
 * @param {Object} context
 */
function bpReportLoaded(element, context) {
  const id = bpCurrentPersonId();
  if (!id || typeof GraphComponent === 'undefined') {
    return;
  }

  const params = new URLSearchParams(window.location.search);
  const extra = [];
  ['from', 'to', 'tag'].forEach(key => {
    const value = params.get(key);
    if (value) {
      extra.push(key + '=' + encodeURIComponent(value));
    }
  });

  element.querySelectorAll('[data-component="graph"][data-graph-kind]').forEach(el => {
    const kind = el.getAttribute('data-graph-kind');
    let url = 'api/bp/report/graph?id=' + encodeURIComponent(id);
    if (kind === 'weight') {
      url += '&type=weight';
    }
    if (extra.length) {
      url += '&' + extra.join('&');
    }
    el.setAttribute('data-url', url);
    const instance = GraphComponent.getInstance(el);
    if (instance) {
      instance.options.url = url;
      GraphComponent.loadData(instance, url);
    }
  });
}

/* =====================================================================
 * Offline capture layer
 *
 * Idea: while offline, store what the user typed in IndexedDB through SyncManager
 * and let it flush once back online (SyncManager binds the online and
 * visibilitychange events during init).
 *
 * Every entry carries a client_uuid generated on the device; the server has
 * UNIQUE (member_id, client_uuid) keeps a replayed push down to one row,
 * ===================================================================== */

/** Outbox store name; must match the key in SyncManager.config.endpoints */
const BP_OUTBOX = 'bp_outbox';

/**
 * Generate a v4 UUID.
 *
 * crypto.randomUUID() exists only in a secure context (https or localhost).
 * Opened over plain http on a LAN it is missing, so a fallback is mandatory.
 *
 * @returns {string}
 */
function bpUuid() {
  if (window.crypto && typeof window.crypto.randomUUID === 'function') {
    return window.crypto.randomUUID();
  }
  const bytes = new Uint8Array(16);
  if (window.crypto && window.crypto.getRandomValues) {
    window.crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 16; i++) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }
  bytes[6] = (bytes[6] & 0x0f) | 0x40;
  bytes[8] = (bytes[8] & 0x3f) | 0x80;
  const hex = [...bytes].map((b) => b.toString(16).padStart(2, '0')).join('');
  return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}

/**
 * Configure the outbox once at application start.
 *
 * SyncManager.init() is NOT called again: Now.init() already did at boot,
 * and init() has no re-entry guard, so calling it twice stacks online/offline listeners.
 */
async function bpSyncSetup() {
  if (typeof SyncManager === 'undefined' || bpSyncSetup.done) {
    return;
  }
  bpSyncSetup.done = true;

  Object.assign(SyncManager.config.endpoints, {[BP_OUTBOX]: 'api/bp/sync/push'});

  try {
    await SyncManager.enable();
  } catch (e) {
    console.warn('[bp] Could not enable SyncManager', e);
    return;
  }

  EventManager.on('sync:operation:success', (context) => {
    // EventManager wraps the payload: what SyncManager emitted is at context.data.
    // Reading context.response instead just yields undefined, and the optional
    // chaining hides that - the warning below would never appear and a rejected
    // record would vanish silently, which is exactly what it exists to prevent
    const response = context?.data?.response;
    const rejected = response?.data?.data?.rejected || response?.data?.rejected;
    if (Array.isArray(rejected) && rejected.length) {
      // The server accepted the request but rejected the data; resending never helps,
      // so tell the user instead of dropping it silently
      NotificationManager.error(
        Now.translate('Some offline records could not be saved') + ': ' +
        rejected.map((r) => r.reason).join(', ')
      );
    }
    bpUpdatePendingBadge();
  });

  ['sync:operation:added', 'sync:completed', 'sync:operation:failed'].forEach((event) => {
    EventManager.on(event, bpUpdatePendingBadge);
  });

  window.addEventListener('online', bpUpdatePendingBadge);
  window.addEventListener('offline', bpUpdatePendingBadge);

  bpUpdatePendingBadge();
}

/**
 * Update the badge showing how many records are waiting to sync.
 */
async function bpUpdatePendingBadge() {
  const badge = document.getElementById('bpPendingBadge');
  if (!badge || typeof SyncManager === 'undefined') {
    return;
  }
  let count = 0;
  try {
    count = await SyncManager.getPendingCount();
  } catch (e) {
    count = 0;
  }
  badge.textContent = String(count);
  badge.hidden = count === 0;
  badge.title = navigator.onLine
    ? Now.translate('Waiting to sync')
    : Now.translate('Saved on this device, will sync when back online');
}

/**
 * Intercept the blood pressure form's submit at DOCUMENT level.
 *
 * It was first bound through the form's data-on-load, which does not work:
 * data-on-load on a data-form only runs AFTER the form data loads successfully;
 * offline, data-load-api fails and the hook never fires - the one situation
 * which is exactly the situation it is needed in.
 *
 * Binding on document during capture always runs, whether or not the form loaded,
 * and it runs before FormManager's own handler.
 *
 * @param {SubmitEvent} event
 */
async function bpOfflineSubmit(event) {
  const form = event.target;
  // Both the full record form and the dashboard's quick form post the same
  // payload to the same endpoint, so one handler covers both
  if (!form || typeof form.matches !== 'function'
    || !form.matches('form[data-form="bpRecord"], form[data-form="bpQuick"]')) {
    return;
  }
  if (navigator.onLine) {
    // Online: let FormManager do its normal work
    return;
  }

  event.preventDefault();
  event.stopImmediatePropagation();

  if (typeof form.checkValidity === 'function' && !form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const data = {};
  new FormData(form).forEach((value, key) => {
    data[key] = value;
  });
  data.entity = 'record';
  data.client_uuid = bpUuid();

  try {
    await bpSyncSetup();
    await SyncManager.addPendingOperation({
      storeName: BP_OUTBOX,
      method: 'add',
      data: data,
      priority: 'high'
    });
    NotificationManager.success(
      Now.translate('Saved on this device, will sync when back online')
    );
    bpUpdatePendingBadge();
    if (form.matches('form[data-form="bpQuick"]')) {
      // The quick form lives on the dashboard; stay here and clear it for the
      // next person rather than navigating away mid-round
      bpQuickReset(form);
    } else if (data.family_id) {
      RouterManager.navigate('/bp-history?id=' + encodeURIComponent(data.family_id));
    }
  } catch (e) {
    NotificationManager.error(Now.translate('Could not save on this device') + ': ' + e.message);
  }
}

document.addEventListener('submit', bpOfflineSubmit, true);

// The dashboard is rendered by ApiComponent after the page loads, so its inputs
// do not exist when this file runs - everything is delegated from the document
document.addEventListener('input', event => {
  const form = event.target.closest ? event.target.closest('form[data-form="bpQuick"]') : null;
  if (form && event.target.matches('[name="sys1"], [name="dia1"]')) {
    bpQuickVerdict(form);
  }
});

document.addEventListener('change', event => {
  const form = event.target.closest ? event.target.closest('form[data-form="bpQuick"]') : null;
  if (form && event.target.matches('[name="family_id"]')) {
    bpQuickSyncLink(form);
  }
});

// After a successful online save the dashboard must show the new numbers,
// so reload the component that drew it instead of leaving stale data on screen.
// FormManager emits form:submitted (not form:success) and the payload carries
// only formId, which is the value of the form's data-form attribute.
EventManager.on('form:submitted', context => {
  // EventManager hands listeners a context object, not the payload itself -
  // what emit() was called with is at context.data
  if (!context || !context.data || context.data.formId !== 'bpQuick') {
    return;
  }
  const form = document.querySelector('form[data-form="bpQuick"]');
  if (form) {
    bpQuickReset(form);
  }
  bpDashboardReload();
});

/**
 * Clear the quick form for the next reading, keeping nothing that could be
 * saved twice by accident.
 *
 * @param {HTMLFormElement} form
 */
function bpQuickReset(form) {
  ['sys1', 'dia1', 'pulse1'].forEach(name => {
    const input = form.querySelector('[name="' + name + '"]');
    if (input) {
      input.value = '';
    }
  });
  const box = document.getElementById('bpQuickVerdict');
  if (box) {
    box.hidden = true;
  }
}

/**
 * Redraw the dashboard from the server.
 *
 * @returns {void}
 */
function bpDashboardReload() {
  const element = document.querySelector('[data-component="api"][data-endpoint="api/bp/home"]');
  if (element && typeof ApiComponent !== 'undefined' && ApiComponent.refresh) {
    ApiComponent.refresh(element);
  }
}


/* =====================================================================
 * Health volunteer mode
 * ===================================================================== */

/**
 * Health volunteer dashboard - the CSV export button.
 *
 * A plain <a href> cannot download it because the endpoint needs an
 * so it goes through window.http and a Blob is built here.
 *
 * @param {HTMLElement} element
 * @param {Object} context
 * @returns {Function}
 */
function bpCareLoaded(element, context) {
  bpSyncSetup();

  const button = element.querySelector('#bpCareExport');
  if (!button) {
    return () => {};
  }

  const onClick = async () => {
    button.disabled = true;
    try {
      const groupId = new URLSearchParams(window.location.search).get('group_id') || '';
      const url = 'api/bp/care/export' + (groupId ? '?group_id=' + encodeURIComponent(groupId) : '');
      const response = await window.http.get(url);
      const payload = response?.data?.data || response?.data;
      if (!payload || !payload.content) {
        throw new Error(Now.translate('No records'));
      }
      const blob = new Blob([payload.content], {type: 'text/csv;charset=utf-8'});
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = payload.filename || 'bp-care.csv';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(link.href);
      NotificationManager.success(Now.translate('Export') + ': ' + payload.rows);
    } catch (e) {
      NotificationManager.error(e.message);
    } finally {
      button.disabled = false;
    }
  };

  button.addEventListener('click', onClick);
  return () => button.removeEventListener('click', onClick);
}

/**
 * Visit page: collect every row and post them as one rows[] payload.
 *
 * data-form is not used because FormManager does not support a nested rows[] payload.
 * Rows with no pressure values are not sent (nobody was home).
 *
 * @param {HTMLElement} element
 * @param {Object} context
 * @returns {Function}
 */
function bpVisitLoaded(element, context) {
  bpSyncSetup();

  const saveButton = element.querySelector('#bpVisitSave');
  const finishButton = element.querySelector('#bpVisitFinish');
  if (!saveButton) {
    return () => {};
  }

  const params = new URLSearchParams(window.location.search);
  const groupId = params.get('group_id') || '';
  const visitDate = params.get('date') || '';

  /** Collect the table's values into a rows[] payload */
  const collectRows = () => {
    const rows = [];
    element.querySelectorAll('tr[data-family-id]').forEach((tr) => {
      const value = (role) => {
        const input = tr.querySelector('[data-role="' + role + '"]');
        return input && input.value !== '' ? input.value : '';
      };
      const sys = value('sys');
      const dia = value('dia');
      const pulse = value('pulse');
      if (sys === '' && dia === '' && pulse === '') {
        return;
      }
      const weightInput = tr.querySelector('[data-role="weight"]');
      rows.push({
        family_id: tr.getAttribute('data-family-id'),
        sys: sys || 0,
        dia: dia || 0,
        pulse: pulse || 0,
        weight: value('weight') || 0,
        height: weightInput ? (weightInput.getAttribute('data-height') || 0) : 0
      });
    });
    return rows;
  };

  const onSave = async () => {
    const rows = collectRows();
    if (!rows.length) {
      NotificationManager.info(Now.translate('Nothing to save'));
      return;
    }
    const tag = element.querySelector('[name="tag"]');
    saveButton.disabled = true;
    try {
      const payloadBody = {
        group_id: groupId,
        tag: tag ? tag.value : '',
        rows: rows
      };
      // Only send date when it has a value; an empty string overrides the server default
      if (visitDate) {
        payloadBody.date = visitDate;
      }
      const response = await window.http.post('api/bp/visit/save', payloadBody);
      const payload = response?.data;
      if (payload && payload.success) {
        NotificationManager.success(payload.message || Now.translate('Saved successfully'));
        RouterManager.reload ? RouterManager.reload() : window.location.reload();
      } else {
        NotificationManager.error((payload && payload.message) || Now.translate('Unable to complete the transaction'));
      }
    } catch (e) {
      NotificationManager.error(e.message);
    } finally {
      saveButton.disabled = false;
    }
  };

  const onFinish = async () => {
    const visitId = context?.data?.visit?.id || context?.visit?.id;
    if (!visitId) {
      return;
    }
    try {
      await window.http.post('api/bp/visit/finish', {visit_id: visitId});
      NotificationManager.success(Now.translate('Saved successfully'));
      RouterManager.navigate('/bp-care');
    } catch (e) {
      NotificationManager.error(e.message);
    }
  };

  saveButton.addEventListener('click', onSave);
  if (finishButton) {
    finishButton.addEventListener('click', onFinish);
  }

  return () => {
    saveButton.removeEventListener('click', onSave);
    if (finishButton) {
      finishButton.removeEventListener('click', onFinish);
    }
  };
}
