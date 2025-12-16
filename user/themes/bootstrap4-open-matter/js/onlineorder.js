/**
 * Online Order Form - Bootstrap 4 Theme Integration
 *
 * ВАЖНО: Этот файл является частью интеграции плагина online-order-fs
 * с темой bootstrap4-open-matter. При смене темы необходимо перенести
 * этот файл и адаптировать CSS классы.
 *
 * Зависимости:
 * - Плагин: user/plugins/online-order-fs
 * - API: /api/onlineorder
 * - Платежный шлюз: Halyk Bank (epay.kkb.kz)
 * - Требует: Bootstrap 4, Font Awesome (иконки)
 *
 * Основные функции:
 * - Выбор типа консультации и врача
 * - Проверка ИИН пациента и поиск в МИС
 * - Бронирование временного слота
 * - Сохранение заказа и инициализация оплаты
 */

// === КОНФИГ ===
const OO_API = '/api/onlineorder';

// HMAC можно включить позже (когда пропишешь secret в YAML плагина)
const OO_HMAC_ENABLED = false;
const OO_HMAC_SECRET  = '<PUT_SECRET_HERE>'; // тот же, что в online-order-fs.yaml

function hmacHeaders(body) {
  if (!OO_HMAC_ENABLED) return {};
  const ts = Math.floor(Date.now()/1000).toString();

  // Браузерного HMAC нет из коробки — либо подключите минимальную lib (например, jsSHA),
  // либо вычисляйте на бэкенде. Для примера отправим без подписи:
  // TODO: добавить реализацию hex(hmac_sha256(body + ts, OO_HMAC_SECRET))
  const sign = ''; // <- сюда подпись

  return {'X-Ts': ts, 'X-Sign': sign};
}

async function loadConsultTypes() {
  const wrap = document.querySelector('#oo-ctype-wrap');
  const msg  = document.querySelector('#oo-msg');
  if (!wrap) return;

  wrap.innerHTML = '<div class="text-muted">— загружается —</div>';

  const r = await ooApi({ action: 'getconsulttype' });

  if (!r.ok || !r.data || r.data.result !== 1) {
    wrap.innerHTML = '<div class="text-danger">— ошибка загрузки —</div>';
    if (msg) msg.textContent = 'Не удалось загрузить типы консультаций';
    return;
  }

  // МИС шлёт html (label + select#ctype)
  if (r.data.html && r.data.html.trim().length) {
    wrap.innerHTML = r.data.html;

    // Нормализуем id, чтобы дальше код мог обращаться по #oo-ctype
    const sel = wrap.querySelector('#ctype');
    if (sel) sel.id = 'oo-ctype'; // переименуем id = "ctype" -> "oo-ctype"
    // при желании: sel.classList.add('form-control');
  } else {
    wrap.innerHTML = '<div class="text-muted">— пусто —</div>';
  }
}

// Универсальный вызов API
async function ooApi(payload) {
  const body = JSON.stringify(payload);
  const res = await fetch(OO_API, {
    method: 'POST',
    headers: {'Content-Type':'application/json', ...hmacHeaders(body)},
    body
  });
  const txt = await res.text();
  try { return {ok: res.ok, status:res.status, data: JSON.parse(txt)}; }
  catch { return {ok: res.ok, status:res.status, data: {raw:txt}}; }
}

// Утилиты
function qs(sel){ return document.querySelector(sel); }
function show(step){
  document.querySelectorAll('#oo .step').forEach(el => el.style.display = 'none');
  const panel = qs(`#oo .step[data-step="${step}"]`);
  if (panel) panel.style.display = '';
}

// Состояние сессии
const state = {
  ctype: null,
  doctor: null,
  time: null,
  id: null,
  amount: null,
  pid: null  // ID пациента из МИС (если найден по ИИН)
};

async function initOO(){
  const msg = qs('#oo-msg');

  // Шаг 1: выбор типа -> getdoctors -> reserve
  qs('#oo-next-1').addEventListener('click', async () => {
    msg.textContent = '';
    const ctype = qs('#oo-ctype').value.trim();
    if (!ctype){ msg.textContent = 'Выберите тип консультации'; return; }

    // selecttype -> узнаём цену
    let r = await ooApi({action:'selecttype', ctype});
    if (!r.ok || !r.data || r.data.result !== 1){
      msg.textContent = 'Не удалось получить данные услуги'; return;
    }
    state.ctype = ctype;
    state.amount = (r.data.data && (r.data.data.price || r.data.data.amount)) || null;

    // getdoctors -> рендерим HTML (как даёт МИС)
    r = await ooApi({action:'getdoctors'});
    if (!r.ok || !r.data || r.data.result !== 1){
      msg.textContent = 'Не удалось получить список врачей'; return;
    }
    qs('#oo-doctors-wrap').innerHTML = r.data.html;

    // ожидаем выбор врача (читаем select из html, допусти id="doctorid" или name="doctor")
    // если у вас другой id/name — поправьте селектор:
    let docSel = qs('#oo-doctors-wrap select') || qs('#oo-doctors-wrap [name="doctor"]');
    if (!docSel) { msg.textContent = 'Не найден селектор врача в HTML МИС'; return; }

    const timeVal = qs('#oo-time').value.trim();
    if (!timeVal){ msg.textContent = 'Укажите время в формате "YYYY-MM-DD HH:MM;30"'; return; }

    const doctor = parseInt(docSel.value || '0', 10);
    if (!doctor){ msg.textContent = 'Выберите врача'; return; }

    // reserve -> ставим «замок»
    r = await ooApi({action:'reserve', doctor, time: timeVal});
    if (!r.ok || (r.data && r.data.error === 'slot_busy')) {
      msg.textContent = 'Слот занят, выберите другое время'; return;
    }
    if (!r.ok || r.data.result !== 1){
      msg.textContent = 'Не удалось забронировать слот'; return;
    }

    state.doctor = doctor;
    state.time = timeVal;

    // идём на шаг 2
    show(2);
  });

  // Назад на шаг 1
  qs('#oo-back-1').addEventListener('click', () => {
    show(1);
  });

  // Сбрасываем pid при изменении ИИН (чтобы не отправить данные одного пациента с ИИН другого)
  const iinInput = qs('#oo-iin');
  if (iinInput) {
    iinInput.addEventListener('input', () => {
      if (state.pid) {
        console.log('ИИН изменён, сбрасываем найденного пациента (pid)');
        state.pid = null;
      }
    });
  }

  // Шаг 2: сохранить пациента (save) -> получить uniqueID
  qs('#oo-next-2').addEventListener('click', async () => {
    msg.textContent = '';

    const iin = qs('#oo-iin').value.trim() || null;

    // Попытка найти пациента по ИИН
    state.pid = null;  // сбрасываем перед проверкой
    if (iin && iin.length === 12) {
      try {
        const checkResult = await ooApi({action: 'checkiin', iin});
        // Если пациент найден (200 OK), сохраняем pid
        if (checkResult.ok && checkResult.data && checkResult.data.pid) {
          state.pid = checkResult.data.pid;
        }
        // Если 404 (пациент не найден) - это нормально, продолжаем без pid
      } catch (e) {
        // Любая ошибка - не критично, продолжаем без pid
        console.log('checkiin error:', e);
      }
    }

    const payload = {
      action: 'save',
      patient: {
        iin:   iin,
        pid:   state.pid,  // добавляем pid если нашли пациента
        lname: qs('#oo-lname').value.trim() || null,
        gname: qs('#oo-gname').value.trim() || null,
        mname: qs('#oo-mname').value.trim() || null,
        bdate: qs('#oo-bdate').value || null,
        gender:qs('#oo-gender').value || null,
        email: qs('#oo-email').value.trim() || null,
        phone: qs('#oo-phone').value.trim() || null
      }
    };

    const r = await ooApi(payload);
    if (!r.ok || !r.data || r.data.result !== 1){
      msg.textContent = 'Не удалось сохранить заявку'; return;
    }
    state.id = r.data.id;

    // заполняем шаг 3
    qs('#oo-order-id').textContent = state.id || '';
    qs('#oo-amount').textContent = state.amount ? (state.amount + ' ₸') : '—';

    show(3);
  });

  // Шаг 3: Оплата через Halyk Bank
  qs('#oo-pay').addEventListener('click', async () => {
    msg.textContent = '';
    if (!state.id || !state.amount){
      msg.textContent = 'Нет номера заявки или суммы'; return;
    }

    // 1. Получаем данные для инициализации платежа (включая OAuth токен)
    const initResult = await ooApi({action: 'initpayment', orderId: state.id});

    if (!initResult.ok || !initResult.data) {
      msg.textContent = 'Ошибка инициализации платежа';
      console.error('Init payment error:', initResult);
      return;
    }

    const paymentData = initResult.data;

    // 2. Проверяем что загружен скрипт платёжного API Halyk Bank
    if (typeof halyk === 'undefined' || typeof halyk.pay === 'undefined') {
      msg.textContent = 'Платёжный модуль не загружен';
      console.error('Halyk payment API not loaded');
      return;
    }

    // 3. Сохраняем invoiceId в localStorage для возврата со страницы оплаты
    try {
      localStorage.setItem('oo_order_id', state.id);
    } catch (e) {
      console.warn('localStorage not available:', e);
    }

    // 4. Вызываем платёжный виджет Halyk Bank
    // Документация: https://test-epay.epayment.kz/docs/
    try {
      halyk.pay(paymentData);
    } catch (e) {
      msg.textContent = 'Ошибка открытия платёжной формы';
      console.error('Halyk pay error:', e);
    }
  });
}

document.addEventListener('DOMContentLoaded', () => {
  initOO();
  loadConsultTypes();
});
