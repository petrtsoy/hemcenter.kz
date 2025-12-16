/********************************************************
 * КОНФИГ / СОСТОЯНИЕ ПРИЛОЖЕНИЯ (persist + globals)
 ********************************************************/
window.OO_API = window.OO_API || '/api/onlineorder';
window.OO_AUTH = window.OO_AUTH || { mode: 'jwt', token: '', api_key: '' };

function ooMigrateLegacyState() {
  try {
    const st = ooLoadState();                // читаем новый формат (oo_state)
    const hasNew = !!(st && st.order && Object.keys(st.order).length);

    // распознаем старый формат: плоские ключи в localStorage
    const legacyKeys = ['ctype','doctor','slot','slotText','doctorName','iin','noIin','fio','pid','complaints','gender','birthdate','phone','email','consent'];
    const legacy = {};
    let hasLegacy = false;
    legacyKeys.forEach(k => {
      const v = localStorage.getItem(k);
      if (v != null) {         // ключ существует (даже пустая строка важна)
        legacy[k] = v === 'true' ? true : (v === 'false' ? false : v);
        hasLegacy = true;
      }
    });

    if (!hasNew && hasLegacy) {
      // Перекладываем старые ключи в новый контейнер
      window._ooOrder = { ...(window._ooOrder || {}), ...legacy };
      // Дополнительно: если есть slotText/doctorName — они тоже поедут
      ooSaveState();           // сохранит в oo_state
      // (по желанию) почистить старые ключи:
      // legacyKeys.forEach(k => localStorage.removeItem(k));
    }
  } catch {}
}


function ooSaveState() {
    try {
        const s = {
            step: window._ooStep || 1,
            order: window._ooOrder || {}
        };
        localStorage.setItem('oo_state', JSON.stringify(s));
    } catch { }
}

function ooLoadState() {
    try {
        const raw = localStorage.getItem('oo_state');
        if (!raw) return null;
        return JSON.parse(raw);
    } catch { return null; }
}

/**
 * Полная очистка всех данных заказа из localStorage
 * Используется для "Начать заново"
 */
function ooResetAll() {
    try {
        // Удаляем новый формат
        localStorage.removeItem('oo_state');
        localStorage.removeItem('oo_order_id');

        // Удаляем старый формат (legacy keys)
        const legacyKeys = ['ctype','doctor','slot','slotText','doctorName','iin','noIin','fio','pid',
                           'complaints','gender','birthdate','phone','email','consent'];
        legacyKeys.forEach(k => localStorage.removeItem(k));

        // Очищаем глобальные переменные
        window._ooStep = 1;
        window._ooOrder = {};

        console.log('Order data reset successfully');
        return true;
    } catch (e) {
        console.error('Failed to reset order data:', e);
        return false;
    }
}

// Экспортируем функцию глобально для использования в кнопках
window.ooResetAll = ooResetAll;

window.addEventListener('beforeunload', ooSaveState);

function ooSnapshotCurrentStep() {
    const step = String(window._ooStep || '1');
    const order = (window._ooOrder = window._ooOrder || {});

    if (step === '1') {
        const ctype = qs('#oo-ctype');
        const amount = qs('#oo-amount');
        order.ctype = ctype ? String(ctype.value || '').trim() : '';
        order.amount = amount ? String(amount.value || '').trim() : '';
    }

    if (step === '2') {
        // врач и слот
        const docSel = qs('#doctor');
        const slotSel = qs('#oo-slot');
        order.doctor = docSel ? String(docSel.value || '').trim() : '';
        order.slot = slotSel ? String(slotSel.value || '').trim() : '';
        order.slotText = slotSel.options[slotSel.selectedIndex]?.text?.trim() || '';
    }

    if (step === '3') {
        // IIN/FIO/PID/согласие (даже если часть полей спрятана – снимем то, что есть)
        const iin = qs('#oo-iin');
        const noIin = qs('#oo-no-iin');
        const fio = qs('#oo-fio');
        const pid = qs('#oo-pid');
        const complaints = qs('#oo-complaints');
        const consent = qs('#oo-consent');
        const gender = document.querySelector('[name="oo-gender"]:checked');
        const birthdate = qs('#oo-birthdate');
        const phone = qs('#oo-phone');
        const email = qs('#oo-email');

        const pidValue = pid ? String(pid.value || '').trim() : '';
        console.log('ooSnapshotCurrentStep - capturing pid from field:', pidValue);

        order.iin = iin ? String(iin.value || '').trim() : '';
        order.noIin = !!(noIin && noIin.checked);
        order.fio = fio ? String(fio.value || '').trim() : '';
        order.pid = pidValue;
        order.complaints = complaints ? String(complaints.value || '').trim() : '';
        order.gender       = gender ? String(gender.value || '').trim() : '';
        order.birthdate = birthdate ? String(birthdate.value || '').trim() : '';
        order.phone     = phone ? String(phone.value || '').trim() : '';
        order.email     = email ? String(email.value || '').trim() : '';
        order.consent = !!(consent && consent.checked);

        console.log('ooSnapshotCurrentStep - saved to order.pid:', order.pid);
    }
}

function ooEnablePayBtn() {
    const payBtn = qs('#oo-pay');
    if (!payBtn) return;
    payBtn.disabled = false;
    payBtn.classList.remove('disabled');
    payBtn.removeAttribute('aria-disabled');
}

function showSpinner(targetSelector) {
    const spinner = `
      <div class="text-muted" style="text-align: left;">
         <div class="spinner-border spinner-border-sm" role="status" aria-label="Loading..."></div>
      </div>`;
    const selEl = qs(targetSelector);
    if (selEl) selEl.innerHTML = spinner;
}

function showDiv(text) {
    return '<div style="display: block;">' + text + '</div>';
}

function showInfoIcon() {
    return '<i class="fa fa-info-circle"></i>';
}

function qs(s, r = document) {
    return r.querySelector(s);
}

function t(path, fallback = path) {
    const src = (window.OO_I18N || {});
    return path.split('.').reduce((o, k) => (o && o[k] != null ? o[k] : undefined), src) ?? fallback;
}

function show(step) {
    document.querySelectorAll('#oo .step').forEach(el => el.style.display = 'none');
    const panel = qs(`#oo .step[data-step="${step}"]`);
    if (panel) {
        panel.style.display = '';
    }
}

/********************************
 * 3) API-ОБЁРТКА
 ********************************/

async function ooApi(payload) {
    const body = JSON.stringify(payload);

    try {
        const res = await fetch(OO_API, {
            method: 'POST',
            headers: (() => {
                const h = { 'Content-Type': 'application/json' };
                if (window.OO_AUTH) {
                    if (OO_AUTH.mode === 'jwt' && OO_AUTH.token) {
                        h['Authorization'] = 'Bearer ' + OO_AUTH.token;
                    } else if (OO_AUTH.mode === 'apikey' && OO_AUTH.api_key) {
                        h['X-API-Key'] = OO_AUTH.api_key;
                    }
                }
                return h;
            })(),
            body
        });

        const txt = await res.text();
        let parsed = null;
        try { parsed = JSON.parse(txt); } catch (e) { }

        if (parsed && typeof parsed === 'object' && ('ok' in parsed)) {
            if (parsed.status == null) parsed.status = res.status;
            return parsed;
        }

        if (!res.ok) {
            return {
                ok: false,
                status: res.status,
                error: parsed && typeof parsed === 'object' ? parsed : { raw: txt }
            };
        }

        if (parsed && typeof parsed === 'object') {
            return { ok: true, status: res.status, data: parsed };
        }

        if (typeof txt === 'string' && /<\/?(select|option|label|div|span|ul|li|form|table|thead|tbody|tr|td|p|h[1-6])\b/i.test(txt)) {
            return { ok: true, status: res.status, html: txt };
        }

        return { ok: true, status: res.status, data: { raw: txt } };
    } catch (err) {
        console.error('ooApi error:', err);
        return { ok: false, status: 0, error: { message: err.message || String(err) } };
    }
}

/***********************************************
 * 4) РЕНДЕР ИНФО-ПАНЕЛЕЙ / МОДАЛКИ
 ***********************************************/

function wireInfoModal() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-oo-info]');
        if (!btn) return;

        const root = btn.closest('.oo-ctype-info') || document;

        const srcTitle = root.querySelector('[data-info-title]');
        const htmlTitle = srcTitle ? srcTitle.innerHTML : '';

        const src = root.querySelector('[data-info-content]');
        const html = src ? src.innerHTML : '';

        const title = document.getElementById('oo-info-title');
        if (title) title.innerHTML = htmlTitle;

        const body = document.getElementById('oo-info-body');
        if (body) body.innerHTML = html;

        if (window.jQuery && typeof jQuery.fn.modal === 'function') {
            jQuery('#oo-info-modal').modal('show');
        } else {
            document.getElementById('oo-info-modal').classList.add('show');
            document.getElementById('oo-info-modal').style.display = 'block';
        }

        e.preventDefault();
    });
}

function renderConsultInfo(row) {
    const box = qs('#oo-ctype-info');
    const amount = qs('#oo-amount');
    if (!box || !amount) return;

    if (!row || (!row.name && !row.info && !row.amount)) {
        box.innerHTML = showDiv('нет данных');
        return;
    }

    amount.value = row.amount;

    const fmtKzt = (n) => (isFinite(n) ? Number(n).toLocaleString('ru-KZ') + ' ₸' : '');

    const price = row.amount
        ? `<i class="fa fa-credit-card"></i>
         ${t('BUTTONS.PRICE')}&nbsp;${fmtKzt(row.amount)}`
        : '';

    const infoIcon = row.info
        ? `<a href="#" data-oo-info aria-label="${t('BUTTONS.MORE_DETAILS')}" title="${t('BUTTONS.MORE_DETAILS')}"
            style="display:inline-flex;align-items:center;">` + showInfoIcon() + `&nbsp;${t('BUTTONS.MORE_DETAILS')}</a>`
        : '';

    box.innerHTML = `
      <div class="oo-ctype-info">
         <div data-info-title class="d-none">${row.name || ''}</div>
         <div data-info-content class="d-none">${row.info || ''}</div>
         <div class="d-flex align-items-center">
            ${infoIcon}
         </div>
         <div class="d-flex align-items-center">
            ${price}
         </div>
      </div>
   `;
}

async function renderSummary() {
    const box = qs('#oo-summary');
    if (!box) return;

    const ord = getSavedOrder();

    let ctypeName = '';
    let ctypeAmount = state.amount || null;

    if (ord.ctype) {
        try {
            const r = await ooApi({ action: 'selecttype', id: String(ord.ctype), ctype: String(ord.ctype) });
            const row = (r?.data?.item || r?.data || {});
            ctypeName = row.name || '';
            ctypeAmount = (row.amount ?? row.price ?? ctypeAmount);
        } catch {}
    }

    const fmtKzt = (n) => (isFinite(n) ? Number(n).toLocaleString('ru-KZ') + ' ₸' : '-');

    // Плоская таблица подтверждения
    const rows = [
        { k: t('LABELS.CONSULT_TYPE', 'Тип консультации'), v: ctypeName || ord.ctype || '-' },
        { k: t('LABELS.DOCTOR', 'Врач'), v: ord.doctorName || ord.doctor || '-' },
        { k: t('LABELS.TIMESLOT', 'Дата и время'), v: ord.slotText || ord.slot || '-' },
        { k: t('LABELS.IIN', 'ИИН'), v: ord.noIin ? t('LABELS.NO_IIN', 'Нет ИИН') : (ord.iin || '-') },
        { k: t('LABELS.FULLNAME', 'ФИО'), v: (ord.patient?.fio || ord.fio || '-') },
        { k: t('LABELS.PHONE', 'Телефон'), v: ord.phone || '-' },
        { k: t('LABELS.EMAIL', 'Email'), v: ord.email || '-' },
        { k: t('LABELS.GENDER', 'Пол'), v: ord.gender === 'M' ? t('LABELS.GENDER_M','Мужской') : (ord.gender === 'F' ? t('LABELS.GENDER_F','Женский') : '-') },
        { k: t('LABELS.BIRTHDATE', 'Дата рождения'), v: ord.birthdate || '-' },
        { k: t('LABELS.COMPLAINTS', 'Жалобы'), v: ord.complaints || '-' },
        { k: t('LABELS.PRICE', 'Стоимость'), v: ctypeAmount != null ? fmtKzt(ctypeAmount) : '-' },
    ];

    box.innerHTML = `
        <table class="table table-sm mb-0">
        <tbody>
            ${rows.map(r => `
                <tr><th style="width:40%">${r.k}</th><td>${(r.v + '').replace(/\n/g,'<br>')}</td></tr>
            `).join('')}
        </tbody>
        </table>`;
}

async function startPayment() {
    const msg = qs('#oo-msg');
    const payBtn = qs('#oo-pay');

    try {
        const ord = getSavedOrder();
        if (!ord.ctype) {
            goStep(1);
            return;
        }
        if (!ord.doctor || !ord.slot) {
            goStep(2);
            return;
        }

        // for double clicks
        if (payBtn) { payBtn.disabled = true; payBtn.classList.add('disabled'); }

        showSpinner('#oo-msg');

        const s = await ooApi({ action: 'saveorder', order: ord });
        if (!s || !s.ok) {
            if (msg) msg.textContent = t('MESSAGES.SAVE_FAILED', 'Не удалось сохранить заказ');
            if (payBtn) { payBtn.disabled = false; payBtn.classList.remove('disabled'); }
            return;
        }

        // Отдаём на сервер всё, что собрали
        const r = await ooApi({ action: 'initpayment', orderId: s.data?.id });

        const po = r.data

        const required = ['invoiceId', 'amount', 'currency', 'terminal', 'auth'];
        for (const k of required) {
            if (po[k] == null || (k === 'amount' && Number.isNaN(Number(po.amount)))) {
                throw new Error('Отсутствует поле: ' + k);
            }
        }
        if (!po.auth.access_token) {
            throw new Error('Нет auth.access_token');
        }

        if (typeof halyk !== 'undefined' && typeof halyk.showPaymentWidget === 'function') {
            halyk.pay(po, function (res) {
                if (!res || !res.success) {
                    if (msg) msg.textContent = 'Оплата не завершена или отменена.';
                    ooEnablePayBtn();
                }
            });
            return;
        }

        if (msg) msg.textContent = t('MESSAGES.PAYMENT_FAILED', 'Не удалось инициировать оплату');
        if (payBtn) { payBtn.disabled = false; payBtn.classList.remove('disabled'); }
    } catch (e) {
        if (msg) msg.textContent = t('MESSAGES.PAYMENT_FAILED', 'Не удалось инициировать оплату');
        if (payBtn) { payBtn.disabled = false; payBtn.classList.remove('disabled'); }
    }
}

/***********************************************
 * 5) ТИПЫ КОНСУЛЬТАЦИЙ (fetch + загрузка)
 ***********************************************/

const state = { ctype: null, doctor: null, amount: null };

async function fetchConsultInfo(ctypeId) {
    showSpinner('#oo-ctype-info');

    if (!ctypeId) {
        renderConsultInfo(null);
        return;
    }

    const r = await ooApi({ action: 'selecttype', id: String(ctypeId), ctype: String(ctypeId) });
    if (!r.ok || !r.data) {
        renderConsultInfo(null);
        return;
    }

    let row = null;
    const d = r.data;
    if (d.name || d.amount || d.info) {
        row = d;
    } else if (d.item) {
        row = d.item;
    } else if (d.result === 1 && d.data) {
        row = d.data;
    } else if (typeof d.raw === 'string') {
        try {
            row = JSON.parse(d.raw);
        } catch { }
    }

    renderConsultInfo(row);
    state.amount = row && (row.amount ?? row.price) || null;
}

async function loadConsultTypes() {
    const wrap = qs('#oo-ctype-wrap');
    const msg = qs('#oo-msg');

    if (!wrap) {
        return;
    }

    showSpinner('#oo-ctype-wrap');

    console.log('Loading consult types from API...');
    const r = await ooApi({ action: 'getconsulttype' });
    console.log('API response:', r);
    if (!r.ok && r.error) {
        console.error('API Error details:', r.error);
    }

    let ok = (r && r.ok);
    let html = null;
    let items = null;
    let d = r ? r : null;

    if (d) {
        if (typeof d.html === 'string' && d.html.trim()) html = d.html;
        if (Array.isArray(d.items)) items = d.items;

        if (!html && d.data && typeof d.data.html === 'string' && d.data.html.trim()) html = d.data.html;
        if (!html && d.data && typeof d.data.info === 'string' && d.data.info.trim()) html = d.data.info;

        if (!items && d.data && Array.isArray(d.data.items)) items = d.data.items;
        if (!items && Array.isArray(d.data) && d.data.length && typeof d.data[0] === 'object') items = d.data;
        if (!items && Array.isArray(d) && d.length && typeof d[0] === 'object') items = d;

        // Убрана проверка d.result === 1 (старый формат), теперь проверяем только наличие данных
        ok = ok && (!!html || !!items);
    }

    if (!ok) {
        console.error('Failed to load consult types:', r);
        wrap.innerHTML = showDiv(t('MESSAGES.LOAD_TYPES_ERROR', 'ошибка загрузки типов'));
        if (msg) msg.textContent = t('MESSAGES.LOAD_TYPES_FAILED', 'Не удалось загрузить типы консультаций');
        renderConsultInfo(null);
        return;
    }

    if (html) {
        wrap.innerHTML = html;
    } else if (Array.isArray(items)) {
        const opts = items.map(it => `<option value="${it.id ?? it.value}">${it.name ?? it.text ?? it.title}</option>`).join('');
        wrap.innerHTML = `<select id="oo-ctype" class="form-control">${opts}</select>`;
    } else {
        console.warn('No consult types data:', r);
        wrap.innerHTML = showDiv(t('MESSAGES.NO_DATA', 'нет данных'));
        renderConsultInfo(null);
        return;
    }

    const sel = qs('#oo-ctype');
    if (!sel) { 
        renderConsultInfo(null);
        return;
    }

    sel.addEventListener('change', () => {
        const id = (sel.value || '').trim();
        state.ctype = id;
        if (!id) { 
            renderConsultInfo(null);
            return;
        }
        fetchConsultInfo(id);
    });

    const saved = getSavedOrder();

    // есть ли такой option?
    const canUseSaved = saved.ctype && Array.from(sel.options).some(
        o => String(o.value || '').trim() === String(saved.ctype).trim()
    );

    // если было сохранённое — ставим его, иначе первый непустой
    let initial = '';
    for (let i = 0; i < sel.options.length; i++) {
        const v = (sel.options[i].value || '').trim();
        if (v) { initial = v; break; }
    }

    sel.value = canUseSaved ? saved.ctype : (sel.value || initial);

    state.ctype = sel.value || null;
    fetchConsultInfo(sel.value);
}

/***********************************************
 * 6) ВРАЧИ И РАСПИСАНИЕ
 ***********************************************/
function bindDoctorChange() {
    const docSel = qs('select#doctor'); //findDoctorSelect();
    if (!docSel) {
        return;
    }
    docSel.addEventListener('change', async () => {
        const id = (docSel.value || '').trim();
        if (!id) {
            const docInfoEl = qs('#oo-doctor-info');
            if (docInfoEl) docInfoEl.innerHTML = '';

            const schedEl = qs('#oo-schedule-wrap');
            if (schedEl) schedEl.innerHTML = '';
            return;
        }
        await loadDoctorInfoAndSchedule(id);
    });
}

async function loadDoctorInfoAndSchedule(doctorId) {
    showSpinner('#oo-doctor-info');
    showSpinner('#oo-schedule-wrap');
    state.doctor = doctorId;

    let r = await ooApi({ action: 'checkdoctor', id: doctorId });

    var infoIcon = (r.ok && r.data && r.data.infoHtml && r.data.doctorName)
        ? `<a href="#" data-oo-info aria-label="${t('BUTTONS.MORE_DETAILS')}" title="${t('BUTTONS.MORE_DETAILS')}"
            style="display:inline-flex;align-items:center;">` + showInfoIcon() + `&nbsp;${t('BUTTONS.MORE_DETAILS')}</a>`
        : '';

    const infoHtml = r?.data?.infoHtml || '';
    const docName = r?.data?.doctorName || '';

    const titleEl = qs('[data-info-title]');
    const bodyEl = qs('[data-info-content]');
    const iconEl = qs('#oo-doctor-info');

    if (titleEl) titleEl.innerHTML = docName;
    if (bodyEl) bodyEl.innerHTML = infoHtml;
    if (iconEl) iconEl.innerHTML = infoIcon;

    r = await ooApi({ action: 'getdoctorschedule', doc: doctorId, type: state.ctype });

    const schedEl = qs('#oo-schedule-wrap');
    if (schedEl) {
        schedEl.innerHTML = (r.ok && r.html) ? r.html : showDiv('недоступно');
    }
}

/***********************************************
 * 7) НАВИГАЦИЯ ПО ШАГАМ И ВАЛИДАЦИЯ
 ***********************************************/

function getCurrentCtype() {
    const sel = qs('#oo-ctype');
    return sel && sel.value ? String(sel.value).trim() : '';
}

// РОУТЕР ШАГОВ: грузим нужные данные перед показом
async function navigateTo(step, {save=false} = {}) {
    const s = String(step);
    const required = getStepFromSaved();
    if (Number(s) > Number(required)) {
        await navigateTo(String(required));
        return;
    }

    if (save) {
        ooSaveState();             // и только сейчас сохранить
    }
    await prepareState(s);
    show(s);                    
    validateStep(s);

    window._ooStep = Number(s);
    
    if (!window._ooNoPush) {
        try { history.pushState({ step: s }, '', '#step' + s); } catch (e) { }
    }
}

function getStepFromSaved() {
    const ord = getSavedOrder() || {};
    const has = (v) => typeof v === 'string' ? v.trim() !== '' : !!v;

    const hasCtype  = has(ord.ctype);
    const hasDoctor = has(ord.doctor);
    const hasSlot   = has(ord.slot);

    const fioOk     = !!(ord.fio && ord.fio.trim().split(/\s+/).filter(Boolean).length >= 2);
    const iinOk     = /^\d{12}$/.test(String(ord.iin||'').trim());
    const noIinOk   = !!ord.noIin;
    const phoneOk   = !!(ord.phone && /^\+?\d[\d\s\-()]{6,}$/.test(ord.phone.trim()));
    const emailOk   = !!(ord.email && /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i.test(ord.email.trim()));
    const consentOk = !!ord.consent;
    
    const haveIinPath = (fioOk || /^\d+$/.test(String(ord.pid||''))) && iinOk;
    const noIinPath   = fioOk && noIinOk;

    const patientOk = (haveIinPath || noIinPath) && phoneOk && emailOk && consentOk;

    if (!hasCtype) return 1;
    if (!hasDoctor || !hasSlot) return 2;
    if (!patientOk) return 3;
    return 4;
}


function validateStep(s) {
    try {
        if (s === '1') {
            validateStep1(true);
        }
        if (s === '2') {
            validateStep2(true);
        }
        if (s === '3') {
            validateStep3(false);
        }
    } finally {}
}

function validateStep1(show) {
    const ctype = qs('#oo-ctype');
    const msg = qs('#oo-msg');
    const hasType = !!(ctype && String(ctype.value || '').trim());

    if (show && msg && !hasType) {
        msg.textContent = `${t('MESSAGES.NO_TYPE_SELECTED')}`;
    } else if (msg) {
        msg.textContent = '';
    }
    state.ctype = getCurrentCtype();
}

function validateStep2(show) {
    const btn = qs('#oo-next-2');
    const msg = qs('#oo-msg');

    const docSel = qs('select#doctor');
    const timeSlotSel = qs('#oo-slot');
    const hasDoc = !!(docSel && String(docSel.value || '').trim());
    const hasSlot = !!(timeSlotSel && String(timeSlotSel.value || '').trim());

    if (btn) {
        btn.disabled = !(hasDoc && hasSlot);
    }

    if (show && msg && (!hasDoc || !hasSlot)) {
        msg.textContent = `${t('MESSAGES.NO_DOCTOR_SELECTED')}`;
    } else if (msg) {
        msg.textContent = '';
    }
}

function validateStep3(show) {
    function isIIN(v) {
        return /^\d{12}$/.test(String(v).trim());
    }

    function isFIO(v) {
        const parts = String(v).trim().split(/\s+/).filter(Boolean);
        return parts.length >= 2 && parts.every(p => p.length >= 2);
    }

    function isPid(v) {
        if (!/^\d+$/.test(v)) return false;
        const num = Number(v);
        return Number.isFinite(num);
    }

    function isPhone(v) {
        const s = String(v).trim();
        // Разрешаем любые международные форматы, начиная с + и минимум 7 цифр
        const re = /^\+?\d[\d\s\-()]{6,}$/;
        return re.test(s);
    }
    
    function isEmail(v) {
        const s = String(v).trim();
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/i;
        return re.test(s);
    }
    
    function isBirthdate(v) {
        if (!v) return true;
        const d = new Date(v + 'T00:00:00');
        if (Number.isNaN(d.getTime())) return false;
        const today = new Date();
        return d <= today;
    }

  
    const iin = qs('#oo-iin');
    const noIin = qs('#oo-no-iin');
    const fio = qs('#oo-fio');
    const pid = qs('#oo-pid');
    const birthdate = qs('#oo-birthdate');
    const phone = qs('#oo-phone');
    const email = qs('#oo-email');

    const consent = qs('#oo-consent');

    const next = qs('#oo-next-3');
    
    const fioOk = fio ? isFIO(fio.value) : false;
    const pidOk = pid ? isPid(pid.value) : false;
    const iinOk = iin ? isIIN(iin.value) : false;
    const phoneOk = phone ? isPhone(phone.value) : false;
    const emailOk = email ? isEmail(email.value) : false;
    const birthOk = birthdate ? isBirthdate(birthdate.value) : true;

    const haveIinPath = (fioOk || pidOk) && iinOk;
    const noIinPath = fioOk && !!(noIin && noIin.checked);
    const consentOk = consent ? !!consent.checked : false;

    const logicOk = (haveIinPath || noIinPath) && consentOk && phoneOk && emailOk && birthOk;

    if (show) {
        const
            iinErr = qs('#oo-iin-err'),
            fioErr = qs('#oo-fio-err'),
            cErr = qs('#oo-consent-err'),
            phErr  = qs('#oo-phone-err'),
            emErr  = qs('#oo-email-err'),
            bdErr  = qs('#oo-birthdate-err');
        const iinActive = !(noIin && noIin.checked);
        const iinHasVal = iin ? String(iin.value).trim().length > 0 : false;
        if (iinErr) iinErr.style.display = (iinActive && iinHasVal && !iinOk) ? '' : 'none';
        if (fioErr) fioErr.style.display = (fio && !fioOk && fio.value.trim().length > 0) ? '' : 'none';
        if (phErr)  phErr.style.display  = (phone && phone.value.trim().length > 0 && !phoneOk) ? '' : 'none';
        if (emErr)  emErr.style.display  = (email && email.value.trim().length > 0 && !emailOk) ? '' : 'none';
        if (bdErr)  bdErr.style.display  = (birthdate && birthdate.value && !birthOk) ? '' : 'none';
        if (cErr) cErr.style.display = (consent && !consent.checked) ? '' : 'none';
    }

    if (next) next.disabled = !logicOk;
    return logicOk;
}

function getSavedOrder() {
    try {
        const st = ooLoadState();   
        const lsOrder = (st && st.order) ? st.order : {};
        const memOrder = (window._ooOrder && typeof window._ooOrder === 'object') ? window._ooOrder : {};
        return { ...lsOrder, ...memOrder };
    } catch {
        return (window._ooOrder && typeof window._ooOrder === 'object') ? window._ooOrder : {};
    }
}

async function prepareState(s) {
    try {
        if (s === '1') {
            await loadConsultTypes();
        }

        if (s === '2') {
            const r = await ooApi({ action: 'getdoctors', ctype: state.ctype });
            if (r.ok && r.html) {
                qs('#oo-doctors-wrap').innerHTML = r.html;

                const saved = getSavedOrder();

                const docSel = qs('select#doctor');
                bindDoctorChange();

                // 1) восстановить врача, если он есть в списке
                if (docSel) {
                    const savedDoctor = saved.doctor || state.doctor || '';
                    const hasSavedDoctor = savedDoctor && Array.from(docSel.options)
                    .some(o => String(o.value || '').trim() === String(savedDoctor));

                    if (hasSavedDoctor) docSel.value = savedDoctor;

                    // всегда держим в состоянии и в заказе
                    state.doctor = docSel.value || null;
                    window._ooOrder = window._ooOrder || {};
                    window._ooOrder.doctor = state.doctor || '';
                    ooSaveState();

                    // 2) подгружаем инфо + расписание и только потом восстановим слот
                    if (docSel.value) {
                        await loadDoctorInfoAndSchedule(docSel.value);

                        // после подгрузки расписания появится #oo-slot — восстановим его
                        const slotSel = qs('#oo-slot');
                        const savedSlot = saved.slot || '';
                        const hasSavedSlot = slotSel && savedSlot && Array.from(slotSel.options)
                        .some(o => String(o.value || '').trim() === String(savedSlot));

                        if (slotSel) {
                            if (hasSavedSlot) slotSel.value = savedSlot;

                            // подписка на изменения слота с автосейвом
                            slotSel.addEventListener('change', () => {
                                window._ooOrder = window._ooOrder || {};
                                window._ooOrder.slot = String(slotSel.value || '').trim();
                                window._ooOrder.slotText = slotSel.options[slotSel.selectedIndex]?.text?.trim() || '';
                                ooSaveState();
                                validateStep2(false);
                            });
                            if (slotSel.value) {
                                window._ooOrder.slotText = slotSel.options[slotSel.selectedIndex]?.text?.trim() || '';
                                ooSaveState();
                            }
                        }
                    }
                    docSel.addEventListener('change', async () => {
                        window._ooOrder = window._ooOrder || {};
                        window._ooOrder.doctor = String(docSel.value || '').trim();
                        window._ooOrder.doctorName = docSel.options[docSel.selectedIndex]?.text?.trim() || '';
                        // сбрасываем слот при смене врача
                        window._ooOrder.slot = '';
                        window._ooOrder.slotText = '';
                        state.doctor = window._ooOrder.doctor || null;
                        ooSaveState();

                        await loadDoctorInfoAndSchedule(docSel.value);

                        const slotSel2 = qs('#oo-slot');
                        if (slotSel2) {
                            slotSel2.addEventListener('change', () => {
                                window._ooOrder = window._ooOrder || {};
                                window._ooOrder.slot = String(slotSel2.value || '').trim();
                                window._ooOrder.slotText = slotSel2.options[slotSel2.selectedIndex]?.text?.trim() || '';
                                ooSaveState();
                                validateStep2(false);
                            });
                        }
                        validateStep2(false);
                    });

                    // сразу проставим doctorName для восстановленного врача
                    window._ooOrder = window._ooOrder || {};
                    window._ooOrder.doctorName = docSel.options[docSel.selectedIndex]?.text?.trim() || '';
                    ooSaveState();
                } else {
                    const msg = qs('#oo-msg');
                    if (msg) msg.textContent = 'Не удалось загрузить список врачей';
                    return;
                }
            }
        }

        if (s === '3') {
            const saved = getSavedOrder() || {};

            const iin = qs('#oo-iin');
            const noIin = qs('#oo-no-iin');
            const fio = qs('#oo-fio');
            const consent = qs('#oo-consent');
            const pid        = qs('#oo-pid');
            const genderM = qs('#oo-gender-m');
            const genderF = qs('#oo-gender-f');
            const birthdate = qs('#oo-birthdate');
            const phone = qs('#oo-phone');
            const email = qs('#oo-email');
            const complaints = qs('#oo-complaints');

            if (noIin) noIin.checked = !!saved.noIin;
            if (iin) {
                iin.value   = saved.noIin ? '' : (saved.iin || '');
                iin.disabled = !!saved.noIin;
            }
            if (fio)        fio.value        = saved.fio || '';
            if (pid)        pid.value        = saved.pid || '';
            if (complaints) complaints.value = saved.complaints || '';
            if (saved.gender === 'M' && genderM) genderM.checked = true;
            if (saved.gender === 'F' && genderF) genderF.checked = true;
            if (birthdate) birthdate.value = saved.birthdate || '';
            if (phone)     phone.value     = saved.phone || '';
            if (email)     email.value     = saved.email || '';
            if (consent)    consent.checked  = !!saved.consent;

            const inputs = [iin, noIin, fio, consent, genderM, genderF, birthdate, phone, email].filter(Boolean);
            inputs.forEach(el => {
                ['input', 'change', 'blur'].forEach(ev => el.addEventListener(ev, () => validateStep3(false)));
            });

            if (noIin) {
                noIin.addEventListener('change', () => {
                    if (iin) iin.disabled = !!noIin.checked;
                    validateStep3(false);
                });
                if (!window._ooIinWired) {
                    try { wireIinCheck(); }
                    finally { window._ooIinWired = true; }
                }
            }
        }

        if (s === '4') {
            await renderSummary();
            const payBtn = qs('#oo-pay');
            if (payBtn && !payBtn._wired) {
                payBtn.addEventListener('click', startPayment);
                payBtn._wired = true;
                ooEnablePayBtn();
            }
        }
    } finally { }
}

/***********************************************
 * IIN / АВТОПОДСТАНОВКА
 ***********************************************/

function wireIinCheck() {
    const iin = qs('#oo-iin');
    const noIin = qs('#oo-no-iin');
    const fio = qs('#oo-fio-block');
    const birth = qs('#oo-birthday-block');
    const gender = qs('#oo-gender-block');
    const pid = qs('#oo-pid');
    
    if (!iin || !fio) return;

    async function maybeCheck() {
        if (noIin && noIin.checked) {
            fio.style.display = '';
            birth.style.display = '';
            gender.style.display = '';
            pid.value = '';
            iin.value = '';
            return;
        }
        const val = (iin.value || '').trim();
        if (!val || (val.length !== 12)) {
            fio.style.display = '';
            birth.style.display = '';
            gender.style.display = '';
            pid.value = '';  // Очищаем pid при изменении/очистке ИИН
            return;
        }
        const resp = await ooApi({ action: 'checkiin', iin: val });
        console.log('checkiin response:', resp);
        if (resp && resp.ok && resp.data && resp.data.patient) {
            console.log('Patient FOUND, pid:', resp.data.pid);
            fio.style.display = 'none';
            birth.style.display = 'none';
            gender.style.display = 'none';
            window._ooOrder = window._ooOrder || {};
            window._ooOrder.patient = resp.data.patient;
            window._ooOrder.pid = String(resp.data.pid || '');  // сохраняем pid в состояние
            pid.value = resp.data.pid;
            ooSaveState();
            console.log('Patient saved - pid.value:', pid.value, 'state.pid:', window._ooOrder.pid);
        } else {
            console.log('Patient NOT FOUND - clearing pid');
            // Пациент не найден - очищаем данные
            fio.style.display = '';
            birth.style.display = '';
            gender.style.display = '';
            pid.value = '';
            window._ooOrder = window._ooOrder || {};
            window._ooOrder.pid = '';  // очищаем pid в состоянии
            delete window._ooOrder.patient;
            ooSaveState();
            console.log('After clearing - pid.value:', pid.value, 'state.pid:', window._ooOrder.pid);
        }
    }
    // Сбрасываем pid сразу при начале ввода ИИН
    iin.addEventListener('input', () => {
        const val = (iin.value || '').trim();
        console.log('IIN input event:', val, 'length:', val.length, 'current pid:', pid.value);
        // Если длина не 12 цифр - сразу сбрасываем pid
        if (val.length !== 12) {
            console.log('Resetting pid because length != 12');
            pid.value = '';
            window._ooOrder = window._ooOrder || {};
            window._ooOrder.pid = '';  // очищаем pid в состоянии
            delete window._ooOrder.patient;
            ooSaveState();  // сохраняем изменения
            console.log('After reset - pid.value:', pid.value, 'state.pid:', window._ooOrder.pid);
        }
    });

    iin.addEventListener('change', maybeCheck);
    iin.addEventListener('blur', maybeCheck);
    if (noIin) noIin.addEventListener('change', maybeCheck);
    maybeCheck();
}

/***********************************************
 * 9) СИСТЕМНЫЕ ОБРАБОТЧИКИ / СТАРТ
 ***********************************************/
async function goStep(targetStep) {
    const curr = Number(window._ooStep || 1);
    const target = Number(targetStep);

    if (target < curr) {
        // назад — без pushState
        window._ooNoPush = true;
        try {
            await navigateTo(String(target));
            try { history.replaceState({ step: String(target) }, '', '#step' + target); } catch (e) {}
        } finally {
            window._ooNoPush = false;
        }
        return;
    }

    ooSnapshotCurrentStep();
    ooSaveState();

    await navigateTo(String(target));
}

function wireButtons() {
    document.addEventListener('click', async (e) => {
        const btn = e.target.closest('.mov-btn');
        if (!btn) return;
        e.preventDefault();

        const step = btn.dataset.step;
        if (!step) return;

        showSpinner('#oo-msg');
        await goStep(step);
        const msg = qs('#oo-msg');
        if (msg) msg.innerHTML = ''

    });
}

document.addEventListener('DOMContentLoaded', async () => {
    const root = document.querySelector('#oo');
    if (!root) return;

    try {
        const savedState = ooLoadState();
        if (savedState && savedState.order) {
            window._ooOrder = savedState.order;
            window._ooStep  = savedState.step || 1;
        }

        ooMigrateLegacyState();  
        await loadConsultTypes();

        const forcedStep = getStepFromSaved();
        await navigateTo(String(forcedStep));
    } catch (e) {
        await navigateTo('1');
    }

    wireInfoModal();
    wireButtons();
});


window.addEventListener('pageshow', function () {
    ooEnablePayBtn();
    const msg = qs('#oo-msg');
    if (msg) { msg.innerHTML = ''; }  
});

document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') ooEnablePayBtn();
});

window.addEventListener('popstate', async function (ev) {
    // 1) приоритет: состояние из history, 2) hash из URL, 3) сохранённое состояние
    let s = (ev && ev.state && ev.state.step) ? String(ev.state.step) : '';
    if (!s && location.hash && /^#step\d+$/.test(location.hash)) {
        s = location.hash.replace('#step', '');
    }
    if (!s) {
        const st = ooLoadState();
        s = String(st?.step || 1);
    }
    // 2) навигация без добавления нового узла в history
    window._ooNoPush = true;
    try { await navigateTo(s); } finally { window._ooNoPush = false; }
});