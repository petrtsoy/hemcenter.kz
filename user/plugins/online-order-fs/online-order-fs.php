<?php
/**
 * Online Order FS Plugin for Grav CMS
 *
 * Плагин онлайн-записи на консультацию с интеграцией:
 * - МИС (Medical Information System) через website.php API
 * - Платежный шлюз Halyk Bank (epay.kkb.kz)
 * - Многоязычность (ru, kk, en)
 *
 * Основные функции:
 * - API эндпоинты для фронтенда (/api/onlineorder)
 * - Выбор типа консультации, врача, времени
 * - Проверка ИИН пациента и поиск в базе МИС
 * - Бронирование временных слотов
 * - Сохранение заказов в файловом хранилище
 * - Инициализация платежа через Halyk Bank
 * - Обработка callback от банка (paid/failed)
 * - Отправка подтвержденных заказов в МИС
 * - Страницы результата оплаты (/payment/success, /payment/failed)
 *
 * Требует:
 * - Тема с Bootstrap 4 (или адаптация CSS)
 * - Frontend JS: user/themes/THEME/js/onlineorder.js
 * - МИС API доступен через website.php
 * - Настроенный терминал Halyk Bank
 *
 * @package    Grav\Plugin
 * @author     Your Name
 * @license    MIT
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Yaml;

class OnlineOrderFsPlugin extends Plugin
{
    private $cfg;

    /**
     * Получить текущий язык с fallback на язык по умолчанию из конфигурации
     */
    private function getCurrentLanguage(): string
    {
        $lang = $this->grav['language'];
        if ($lang && $lang->enabled()) {
            $active = $lang->getActive();
            if ($active) {
                return $active;
            }
            // Fallback на язык по умолчанию из конфигурации
            $default = $lang->getDefault();
            if ($default) {
                return $default;
            }
        }
        // Крайний fallback если мультиязычность выключена
        return 'en';
    }

    /**
     * Получить URL с языковым префиксом
     */
    private function getLanguageUrl(string $path, ?string $language = null): string
    {
        $lang = $language ?? $this->getCurrentLanguage();
        $config = $this->grav['config'];

        // Проверяем, нужно ли включать дефолтный язык в URL
        $includeDefaultLang = $config->get('system.languages.include_default_lang', false);
        $defaultLang = $this->grav['language']->getDefault() ?: 'kk';

        // Если include_default_lang=true, всегда добавляем префикс
        // Если false, не добавляем префикс только для дефолтного языка
        if (!$includeDefaultLang && $lang === $defaultLang) {
            return $path;
        }

        // Добавляем языковой префикс
        return "/{$lang}" . $path;
    }

    public static function getSubscribedEvents(): array
    {
        // Всегда подписываемся на инициализацию плагинов
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onPagesInitialized'   => ['onPagesInitialized', 0],
            'onTwigTemplatePaths'  => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables'  => ['onTwigSiteVariables', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        // Если открыта админка — обычно не грузим фронтовые части
        if ($this->isAdmin()) {
            return;
        }

        // Если плагин выключен — выходим
        if (!$this->config->get('plugins.online-order-fs.enabled')) {
            return;
        }

        // Включаем нужные события рендеринга
        $this->enable([
            'onPagesInitialized'  => ['onPagesInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
            'onTwigSiteVariables' => ['onTwigSiteVariables', 0],
        ]);

        $this->cfg = $this->config->get('plugins.online-order-fs');
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onTwigSiteVariables(): void
    {
        $this->cfg = $this->config->get('plugins.online-order-fs');
        if (!$this->cfg['enabled']) {
            return;
        }

        // Передаём конфигурацию плагина в Twig
        $this->grav['twig']->twig_vars['online_order_config'] = $this->cfg;

        // Передаём данные о результате платежа в Twig
        if (isset($_SESSION['payment_result'])) {
            $this->grav['twig']->twig_vars['payment_result'] = $_SESSION['payment_result'];
        }

        $assets = $this->grav['assets'];

        // подключаем собственный JS плагина
        $assets->addJs('plugin://online-order-fs/assets/js/onlineorder.js', [
            'group'    => 'bottom',
            'loading'  => 'defer',
            'priority' => 10,
        ]);

        // подключаем внешний платёжный скрипт Homebank
        if (!empty($this->cfg['payment']['enqueue_script'])) {
            $url = isset($this->cfg['payment']['script_url']) ? $this->cfg['payment']['script_url'] : 'https://epay.homebank.kz/payform/payment-api.js';
            $assets->addJs($url, ['group' => 'bottom', 'loading' => 'defer']);
        }

        $lang = $this->getCurrentLanguage();
        $defaultLang = $this->grav['language']->getDefault() ?: 'en';
        $locator = $this->grav['locator'];

        $path = $locator->findResource("plugin://online-order-fs/languages/{$lang}.yaml", true, true)
            ?: $locator->findResource("plugin://online-order-fs/languages/{$defaultLang}.yaml", true, true);

        $dict = [];
        if ($path && is_file($path)) {
            $raw = file_get_contents($path);
            $dict = $raw !== false ? Yaml::parse($raw) : [];
        }
        
        $i18n  = $dict['PLUGIN_ONLINE_ORDER_FS'] ?? [];
        $json  = json_encode($i18n, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $assets->addInlineJs("window.OO_I18N = {$json};", [
            'group'    => 'bottom',
            'priority' => 5,
        ]);

        $assets->addCss('plugin://online-order-fs/assets/css/onlineorder.css');
    }

    public function onPagesInitialized()
    {
        $path = $this->grav['uri']->path();
        $path = rtrim($this->grav['uri']->path(), '/');

        // Убираем языковой префикс, если есть (например /kk/api/onlineorder -> /api/onlineorder)
        $lang = $this->grav['language'];
        if ($lang->enabled()) {
            $active = $lang->getActive();
            $supported = $lang->getLanguages();
            foreach ($supported as $code) {
                if (strpos($path, "/{$code}/") === 0) {
                    $path = substr($path, strlen("/{$code}"));
                    break;
                }
            }
        }

        $apiPath = rtrim($this->cfg['routes']['api'] ?? '/api/onlineorder', '/');
        $callbackUrl = rtrim($this->cfg['routes']['callback']?? '/api/onlineorder/callback', '/');
        $failUrl = rtrim($this->cfg['routes']['fail'] ?? '/api/onlineorder/fail', '/');
        $paidUrl = rtrim($this->cfg['routes']['paid'] ?? '/api/onlineorder/paid', '/');


        if ($path === $apiPath) {
            $this->handleApi();
            return;
        }

        if ($path === $callbackUrl) {
            $raw = file_get_contents('php://input') ?: '';
            $in  = !empty($_POST) ? $_POST : json_decode($raw, true);
            if (!is_array($in)) { $in = []; }
            $this->apiPaymentCallback($in);
            return;
        }

        if ($path === $paidUrl) {             // backLink (браузер клиента)
            $this->apiPaymentConfirm($_GET);     // покажем пользователю «Успех»
            return;
        }

        if ($path === $failUrl) {                // failureBackLink (браузер клиента)
            $this->apiPaymentFailed($_GET);      // покажем «Оплата не прошла»
            return;
        }
    }

//    use Symfony\Component\HttpFoundation\Response;

    private function renderTemplate(string $name): void
    {
        $twig = $this->grav['twig'];

        // внутренняя «вставка»: online-order/paid.html.twig или fail.html.twig
        $inner = $name . '.html.twig';

        $html = $twig->processTemplate('layout.html.twig', [
            'session' => $_SESSION['order'] ?? [],
            'inner'   => $inner,
        ]);
        echo $html;
        exit;
        // $response = new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
        // $response->send();
        // $this->grav->close($response);
    }


    // private function renderTemplate($name)
    // {
    //     $twig   = $this->grav['twig'];
    //     $output = $twig->processTemplate($name . ".html.twig", [
    //         'session' => isset($_SESSION['order']) ? $_SESSION['order'] : [],
    //     ]);
    //     echo $output;
    //     exit;
    // }

    private function json($payload, $status = 200)
    {
        $out = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo $out;
        exit;
    }

    private function handleApi()
    {
        // CORS
        $cors = isset($this->cfg['cors']) ? $this->cfg['cors'] : [];
        if (!empty($cors['enabled'])) {
            header('Access-Control-Allow-Origin: ' . (isset($cors['allow_origin']) ? $cors['allow_origin'] : '*'));
            header('Access-Control-Allow-Methods: ' . (isset($cors['allow_methods']) ? $cors['allow_methods'] : 'GET,POST,OPTIONS'));
            header('Access-Control-Allow-Headers: ' . (isset($cors['allow_headers']) ? $cors['allow_headers'] : 'Content-Type,X-Ts,X-Sign,Authorization'));
            header('Access-Control-Max-Age: 86400');
            if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
                $this->json(['ok' => true]);
                return;
            }
        }

        if (!$this->verifyHmac()) {
            $this->json(['error' => 'bad_signature'], 401);
            return;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) {
            $raw = '';
        }

        $in = !empty($_POST) ? $_POST : json_decode($raw, true);
        if (!is_array($in)) {
            $in = [];
        }

        $action = isset($in['action']) ? $in['action'] : (isset($_GET['action']) ? $_GET['action'] : null);
        if (!$action) {
            $this->json(['error' => 'action_required'], 400);
            return;
        }

        if ($action === 'selecttype') {
            $this->apiSelectType($in);
        } elseif ($action === 'getconsulttype') {
            $this->apiGetConsultType($in);
        } elseif ($action === 'getdoctors') {
            $this->apiGetDoctors($in);
        } elseif ($action === 'checkdoctor') {
            $this->apiCheckDoctor($in);
        } elseif ($action === 'getdoctorname') {
            $this->apiGetDoctorName($in);
        } elseif ($action === 'getdoctorschedule') {
            $this->apiGetDoctorSchedule($in);
        } elseif ($action === 'checkiin') {
            $this->apiCheckIin($in);
        } elseif ($action === 'reserve') {
            $this->apiReserve($in);
        } elseif ($action === 'saveorder') {
            $this->apiSaveOrder($in);
        } elseif ($action === 'status') {
            $this->apiStatus($in);
        } elseif ($action === 'initpayment') {
            $this->apiInitPayment($in);
        // } elseif ($action === 'paid') {
        //     $this->apiPaymentConfirm($in);
        // } elseif ($action === 'fail') {
        //     $this->apiPaymentFailed($in);
        } else {
            $this->json(['error' => 'unknown_action', 'action' => $action], 400);
        }
    }

    private function getToken(): ?string
    {
        $auth = $this->cfg['auth'] ?? [];
        $secret = $auth['secret'] ?? null;

        $url = $this->cfg['mis']['base_url'];
        if (!$secret || !$url) return null;
        
        $ch = curl_init($url . '/issuetoken');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
        ]);

        $resp = curl_exec($ch);
        
        $data = json_decode($resp, true);
        curl_close($ch);

        if (!empty($data['ok']) && !empty($data['data']['token'])) {
            return $data['data']['token'];
        }

        return null;
    }

    private function verifyHmac()
    {
        $secret = (string)($this->cfg['security']['secret'] ?? '');
        if ($secret === '') {
            return true;
        }

        $ts   = isset($_SERVER['HTTP_X_TS']) ? $_SERVER['HTTP_X_TS'] : null;
        $sign = isset($_SERVER['HTTP_X_SIGN']) ? $_SERVER['HTTP_X_SIGN'] : null;
        if (!$ts || !$sign || abs(time() - (int) $ts) > 300) {
            return false;
        }

        $raw = file_get_contents('php://input');
        if ($raw === false) {
            $raw = '';
        }

        $calc = hash_hmac('sha256', $raw . $ts, $secret);
        if (function_exists('hash_equals')) {
            return hash_equals($calc, strtolower($sign));
        }
        return strtolower($sign) === $calc;
    }

    // Определяем язык из Referer или текущего URI
    private function detectLang(): string
    {
        // 1. Пытаемся из HTTP_REFERER (когда делается AJAX запрос)
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref) {
            $path = parse_url($ref, PHP_URL_PATH) ?? '';
            if (preg_match('~/([a-z]{2})(/|$)~i', $path, $m)) {
                return strtolower($m[1]);
            }
        }

        // 2. Пытаемся из текущего URI (когда пользователь на странице)
        $uri = $this->grav['uri'];
        if ($uri) {
            $path = $uri->path();
            if (preg_match('~^/([a-z]{2})(/|$)~i', $path, $m)) {
                return strtolower($m[1]);
            }
        }

        // 3. Fallback на активный язык из Grav
        $lang = $this->grav['language'];
        if ($lang && $lang->enabled()) {
            $active = $lang->getActive();
            if ($active) {
                return $active;
            }
        }

        // 4. Крайний fallback
        return 'kk';
    }

    // ЕДИНЫЙ вызов MIS c нормализацией и моментальным выводом JSON
    private function mis(string $endpoint, array $query = [], string $method = 'GET', $body = null, array $extraHeaders = []): void
    {
        $mis  = $this->cfg['mis'] ?? [];
        $base = rtrim($mis['base_url'] ?? '', '/');
        if ($base === '') {
            $this->json(['ok'=>false, 'error' => 'MIS base_url not configured'], 500);
        }

        $auth = $this->cfg['auth'] ?? [];
        $mode = strtolower($auth['mode'] ?? 'none');
        if ($mode === 'jwt' && !empty($auth['secret'])) {
            $jwt = $this->getToken();
            if ($jwt) {
                $extraHeaders[] = 'Authorization: Bearer ' . $jwt;
            }
        } elseif ($mode === 'apikey' && !empty($auth['api_key'])) {
            $extraHeaders[] = 'X-API-Key: ' . $auth['api_key'];
        }

        $lang = $this->detectLang();
        $query['lang'] = $query['lang'] ?? $lang;

        // URL: website.php?act={endpoint}&params...
        // Endpoint передается как параметр 'act' согласно требованиям website.php
        $query['act'] = $endpoint;
        $url = $base;
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);

        // Заголовки
        $headers = array_merge([
            'Accept: application/json, text/html;q=0.8',
            'X-OO-Lang: ' . $lang,
        ], $extraHeaders);


        $res = $this->curl($method, $url, $body, $headers);
        $status = (int)($res['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            $this->json([
                'ok' => false,
                'error' => [
                    'code' => 'UPSTREAM',
                    'status' => $status,
                    'message' => $res['body'] ?? 'Request failed'
                ]
            ], 200);
        }

        $rawBody = (string)($res['body'] ?? '');
        $decoded = null;

        // JSON (без лишних украшательств; BOM/пробелы можно добавить при желании)
        if ($rawBody !== '' && ($rawBody[0] === '{' || $rawBody[0] === '[')) {
            $decoded = json_decode($rawBody, true);
        }

        // Если не JSON → HTML или сырец
        if (!is_array($decoded)) {
            if (preg_match('~</?(select|option|label|div|span|ul|li|form|table|thead|tbody|tr|td|p|h[1-6])\b~i', $rawBody)) {
                $this->json(['ok'=>true, 'html'=>$rawBody], 200);
            }
            $this->json(['ok'=>true, 'data'=>['raw'=>$rawBody]], 200);
        }

        // ===== НОРМАЛИЗАЦИЯ: разворачиваем все уровни {ok,data} и {data} =====
        $data = $decoded;
        while (is_array($data) && isset($data['ok'], $data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }
        while (is_array($data) && isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        // ===== ВЫВОД (плоский, без data→data) =====

        // 1) Если прислали готовую разметку именно в "html" (не делаем info→html!)
        if (isset($data['html']) && is_string($data['html']) && trim($data['html']) !== '') {
            $this->json([
                'ok' => true,
                'html' => $data['html']
            ], 200);
        }

        // 2) Список - возвращаем items на верхнем уровне для совместимости с фронтендом
        if (isset($data['items']) && is_array($data['items'])) {
            $this->json([
                'ok' => true,
                'items' => $data['items'],
                'data' => $data  // для обратной совместимости
            ], 200);
        }

        // 3) Чистый массив (список без ключа "items") - тоже поднимаем на верхний уровень
        if (isset($data[0]) && is_array($data[0])) {
            $this->json([
                'ok' => true,
                'items' => $data,
                'data' => $data  // для обратной совместимости
            ], 200);
        }

        // 5) Фоллбек: что-то нестандартное — отдадим как есть
        $this->json(['ok'=>true, 'data'=>$data], 200);
    }

    // Вызов MIS без автоматического вывода JSON (возвращает массив результата)
    private function misSilent(string $endpoint, array $query = [], string $method = 'GET', $body = null, array $extraHeaders = []): ?array
    {
        $mis  = $this->cfg['mis'] ?? [];
        $base = rtrim($mis['base_url'] ?? '', '/');
        if ($base === '') {
            return ['ok' => false, 'error' => 'MIS base_url not configured'];
        }

        $auth = $this->cfg['auth'] ?? [];
        $mode = strtolower($auth['mode'] ?? 'none');
        if ($mode === 'jwt' && !empty($auth['secret'])) {
            $jwt = $this->getToken();
            if ($jwt) {
                $extraHeaders[] = 'Authorization: Bearer ' . $jwt;
            }
        } elseif ($mode === 'apikey' && !empty($auth['api_key'])) {
            $extraHeaders[] = 'X-API-Key: ' . $auth['api_key'];
        }

        $lang = $this->detectLang();
        $query['lang'] = $query['lang'] ?? $lang;
        $query['act'] = $endpoint;
        $url = $base;
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);

        $headers = array_merge([
            'Accept: application/json, text/html;q=0.8',
            'X-OO-Lang: ' . $lang,
        ], $extraHeaders);

        $res = $this->curl($method, $url, $body, $headers);
        $status = (int)($res['status'] ?? 0);
        if ($status < 200 || $status >= 300) {
            return [
                'ok' => false,
                'error' => [
                    'code' => 'UPSTREAM',
                    'status' => $status,
                    'message' => $res['body'] ?? 'Request failed'
                ]
            ];
        }

        $rawBody = (string)($res['body'] ?? '');
        if ($rawBody !== '' && ($rawBody[0] === '{' || $rawBody[0] === '[')) {
            $decoded = json_decode($rawBody, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['ok' => true, 'data' => ['raw' => $rawBody]];
    }


    // ============ FILE STORAGE in /tmp ============

    private function dataDir()
    {
        return __DIR__ . '/orders';
    }

    private function lockDir()
    {
        $dir = sys_get_temp_dir() . '/online-order-fs/locks';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function filePath($id)
    {
        return $this->dataDir() . '/' . preg_replace('~[^a-zA-Z0-9_-]~', '_', $id) . '.json';
    }

    private function fileStore($id, $row)
    {
        $path = $this->filePath($id);
        $tmp = $path . '.tmp';
        $row['updated_at'] = date('c');
        if (empty($row['created_at'])) {
            $row['created_at'] = date('c');
        }

        $json = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (file_put_contents($tmp, $json, LOCK_EX) === false) {
            return false;
        }

        return @rename($tmp, $path);
    }

    private function fileLoad($id)
    {
        $path = $this->filePath($id);
        if (!is_file($path)) {
            return null;
        }

        $raw = file_get_contents($path);
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    private function gcOldDrafts($maxAgeSeconds = 172800)
    {
        foreach (glob($this->dataDir() . '/*.json') as $f) {
            $mtime = @filemtime($f);
            if ($mtime === false) {
                $mtime = 0;
            }

            if (time() - $mtime > $maxAgeSeconds) {
                @unlink($f);
            }

        }
    }

    // ============ SLOT LOCKS ============

    private function lockName($doctor, $time)
    {
        $key = $doctor . '_' . preg_replace('~[^0-9T:\- ]~', '_', $time);
        return $this->lockDir() . '/slot_' . $key . '.lock';
    }

    private function acquireSlot($doctor, $time, $ttlSeconds = 600)
    {
        $lock = $this->lockName($doctor, $time);
        if (is_file($lock) && (time() - filemtime($lock) > $ttlSeconds)) {
            @unlink($lock);
        }
        if (is_file($lock)) {
            return false;
        }

        return (bool) @file_put_contents($lock, (string) time());
    }

    private function releaseSlot($doctor, $time)
    {
        $lock = $this->lockName($doctor, $time);
        if (is_file($lock)) {
            @unlink($lock);
        }

    }

    // ============ API ACTIONS ============
    private function apiGetConsultType($in)
    {
        $this->mis('getconsulttype');
    }

    private function apiSelectType($in)
    {
        $id = '';
        if (isset($in['id'])) {
            $id = trim((string) $in['id']);
        } elseif (isset($in['ctype'])) {
            $id = trim((string) $in['ctype']);
        } elseif (isset($_GET['id'])) {
            $id = trim((string) $_GET['id']);
        } elseif (isset($_GET['ctype'])) {
            $id = trim((string) $_GET['ctype']);
        }

        if ($id === '') {
            $this->json(['ok' => false, 'error' => ['code' => 'CTYPE_REQUIRED', 'message' => 'id/ctype required']], 400);
            return;
        }
        
        $this->mis('checktype', ['id' => $id]);
    }

    private function apiGetDoctors($in)
    {
        $ctype = isset($_SESSION['order']['ctype']) ? $_SESSION['order']['ctype'] : (isset($in['ctype']) ? $in['ctype'] : null);
        if (!$ctype) {
            $this->json(['ok' => false, 'error' => ['code' => 'CTYPE_REQUIRED', 'message' => 'ctype required']], 400);
            return;
        }
        $this->mis('getdoctors',  ['type' => (string) $ctype]);
    }

    private function apiCheckDoctor($in)
    {
        $id = isset($in['id']) ? (int) $in['id'] : 0;
        if (!$id) {
            $this->json(['ok' => false, 'error' => ['code' => 'CTYPE_REQUIRED', 'message' => 'id required']], 400);
            return;
        }

        $this->mis('checkdoctor',  ['id' => (int) $id]);
    }

    private function apiGetDoctorSchedule($in)
    {
        $doc  = isset($in['doc']) ? (int) $in['doc'] : 0;
        $type = isset($_SESSION['order']['ctype']) ? $_SESSION['order']['ctype'] : ($in['type'] ?? null);

        if (!$doc) {
            $this->json(['error' => 'doc_required'], 400);
            return;
        }

        $this->mis('getdoctorschedule',  ['doc' => (int) $doc, 'type' => $type]);
    }

    private function apiCheckIin($in)
    {
        $iin = isset($in['iin']) ? trim((string) $in['iin']) : (isset($_GET['iin']) ? trim((string) $_GET['iin']) : '');
        if ($iin === '' && empty($in['no_iin'])) {$this->json(['error' => 'iin_required'], 400);return;}

        $this->mis('checkiin', ['iin' => $iin]);
    }

    private function apiReserve($in)
    {
        $doctor = isset($in['doctor']) ? (int) $in['doctor'] : null;
        $time   = isset($in['time']) ? $in['time'] : null;
        if (!$doctor || !$time) {
            $this->json(['error' => 'doctor_time_required'], 400);
            return;
        }

        $parts = explode(';', $time);
        $start = trim(isset($parts[0]) ? $parts[0] : '');
        if (!$start) {
            $this->json(['error' => 'bad_time_format'], 400);
            return;
        }

        $ttl = (int) $this->cfg['slots']['lock_ttl'];
        if (!$ttl) {
            $ttl = 600;
        }

        if (!$this->acquireSlot($doctor, $start, $ttl)) {
            $this->json(['result' => 0, 'error' => 'slot_busy'], 409);
            return;
        }

        $_SESSION['order']['doctor'] = $doctor;
        $_SESSION['order']['time']   = $time;
        $this->json(['result' => 1]);
    }

    private function apiSaveOrder($in)
    {
        //$this->log(json_encode($in, JSON_UNESCAPED_UNICODE)); die;

        // Определяем язык используя существующую функцию detectLang()
        $currentLang = $this->detectLang();

        $_SESSION['order'] = $in['order'];
        $_SESSION['order']['language'] = $currentLang;  // добавляем язык в сессию

        $t      = explode(';', isset($_SESSION['order']['slot']) ? $_SESSION['order']['slot'] : '');
        $start  = isset($t[0]) ? $t[0] : (isset($_SESSION['order']['slot']) ? $_SESSION['order']['slot'] : '');
        $finish = (isset($t[0]) && isset($t[1])) ? date('H:i', strtotime($t[0] . ' +' . (int) $t[1] . ' minutes')) : null;

        $id  = isset($_SESSION['order']['saved_id']) ? $_SESSION['order']['saved_id'] : $this->genUniqueId();

        $row = [
            'uniqueID'      => $id,
            'ctype'         => isset($_SESSION['order']['ctype']) ? $_SESSION['order']['ctype'] : null,
            'doctor'        => isset($_SESSION['order']['doctor']) ? $_SESSION['order']['doctor'] : null,
            'time'          => $start,
            'finish'        => $finish,
            'amount'        => isset($_SESSION['order']['amount']) ? $_SESSION['order']['amount'] : null,
            'iin'           => isset($_SESSION['order']['iin']) ? $_SESSION['order']['iin'] : null,
            'pid'           => isset($_SESSION['order']['pid']) ? $_SESSION['order']['pid'] : null,
            'fullname'      => isset($_SESSION['order']['fio']) ? $_SESSION['order']['fio'] : null,
            'bdate'         => isset($_SESSION['order']['birthdate']) ? $_SESSION['order']['birthdate'] : null,
            'gender'        => isset($_SESSION['order']['gender']) ? $_SESSION['order']['gender'] : null,
            'email'         => isset($_SESSION['order']['email']) ? $_SESSION['order']['email'] : null,
            'phone'         => isset($_SESSION['order']['phone']) ? $_SESSION['order']['phone'] : null,
            'language'      => $currentLang,  // сохраняем язык для редиректа
            'session'       => session_id(),
            'paid'          => 0,
            'transactionId' => null,
        ];

        //$this->log(json_encode($row, JSON_UNESCAPED_UNICODE)); die;

        if (!$this->fileStore($id, $row)) {
            $this->log('alert!');
            $this->json(['result' => 0, 'error' => 'store_error'], 500);
            return;
        }
        $_SESSION['order']['saved_id'] = $id;
        $this->gcOldDrafts();
        $this->json(['result' => 1, 'id' => $id]);
    }

    private function apiInitPayment($in)
    {
        $orderId = $in['orderId'] ?? $in['id'] ?? null;

        if (!$orderId) {
            $this->json(['ok'=>false, 'error'=>'order_id_required'], 400);
            return;
        }

        // грузим драфт; если надо — частично обновляем из $in['order']
        $draft = $this->fileLoad($orderId);
        if (!$draft || !is_array($draft)) {
            $this->json(['ok'=>false, 'error'=>'order_not_found'], 404);
            return;
        }

        // merge c входящими полями (необязательный шаг)
        if (isset($in['order']) && is_array($in['order'])) {
            $ord = $in['order'];
            $draft['amount'] = isset($ord['amount']) ? (float)$ord['amount'] : ($draft['amount'] ?? 0);
            $draft['email']  = $ord['email']  ?? ($ord['patient']['email']  ?? ($draft['email'] ?? null));
            $draft['lname']  = $ord['lname']  ?? ($ord['patient']['lname']  ?? ($draft['lname'] ?? null));
            $draft['gname']  = $ord['gname']  ?? ($ord['patient']['gname']  ?? ($draft['gname'] ?? null));
            // при необходимости добавь другие поля
        }

        // базовые проверки
        $amount = (float)($draft['amount'] ?? 0);
        $email = $draft['email'] ?? null;
        if ($amount <= 0) {
            $this->json(['ok'=>false, 'error'=>'amount_required'], 400);
            return;
        }

        // токен от Halyk (OAuth)
        $tokenResp = $this->apiHpToken([
            'invoiceID' => $orderId,
            'amount'    => $amount,
        ] + $in);

        if (!is_array($tokenResp) || ($tokenResp['result'] ?? 0) != 1) {
            $this->json(['ok'=>false, 'error'=>'token_failed', 'status'=>$tokenResp['status'] ?? null], 502);
            return;
        }
        $auth = json_decode($tokenResp['body'] ?? '{}', true);

        // ссылки из конфига
        $paidUrl    = $this->buildWebsiteUrl($this->cfg['routes']['paid'] ?? '/api/onlineorder/paid');
        $failUrl    = $this->buildWebsiteUrl($this->cfg['routes']['fail'] ?? '/api/onlineorder/fail');
        $callbackUrl = $this->buildWebsiteUrl($this->cfg['routes']['callback'] ?? '/api/onlineorder/callback');

        // настройки Halyk из YAML
        $terminal   = $this->cfg['payment']['terminal'];
        $secretKey  = $this->cfg['payment']['key'];

        // контрольная строка, которую вернут на postLink
        $secret_hash = hash('sha256', $orderId . $amount . $terminal . $secretKey);

        // имя для выписки
        $statementName = $email ?: trim(($draft['lname'] ?? '') . ' ' . ($draft['gname'] ?? ''));

        $lang = $this->detectLang();

        $payment = [
            'invoiceId'       => $orderId,
            'invoiceIdAlt'    => $orderId,
            'backLink'        => $paidUrl,
            'failureBackLink' => $failUrl,
            'postLink'        => $callbackUrl,
            'failurePostLink' => $callbackUrl,
            'language'        => ($lang == 'eng' ? 'ENG' : ($lang == 'kk' ? 'kaz' : 'rus')),
            'description'     => 'Оплата консультации',
            'accountId'       => $email,
            'terminal'        => $terminal,
            'amount'          => $amount,
            'name'            => $statementName,
            'currency'        => 'KZT',
            'data'            => json_encode(['statement'=>['name'=>$statementName,'invoiceID'=>$orderId]], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'cardSave'        => true,
            'secret_hash'     => $secret_hash,
            'auth' => $auth,
        ];

        $this->json(['ok' => true, 'data' => $payment]);
    }

    private function apiPaymentConfirm($in = [])
    {
        // 1) Определяем ID заказа (согласно документации используется invoiceId)
        // Halyk Bank TEST может не передавать параметры в backLink, поэтому проверяем сессию
        $id = $in['invoiceId'] ?? $in['invoiceID'] ?? $in['order_id'] ?? ($_SESSION['order']['saved_id'] ?? null);

        if (!$id) {
            $this->renderTemplate('fail');
            return;
        }

        // 2) Грузим драфт
        $row = $this->fileLoad($id);
        if (!$row) {
            $this->renderTemplate('fail');
            return;
        }

        // 3) ВАЖНО: Проверяем, что платёж действительно прошёл (callback от банка должен был установить paid=1)
        //    Если paid != 1, значит callback либо не пришёл, либо пришёл для неправильного invoiceId
        //    В этом случае делаем проверку статуса через API Halyk Bank
        if (empty($row['paid']) || $row['paid'] != 1) {
            // Проверяем статус через API банка
            $statusData = $this->checkPaymentStatus($id);

            if ($statusData) {
                // Разбираем ответ согласно документации:
                // resultCode: "100" = SUCCESS, "101" = unsuccessful, "102" = not found
                // statusName: "CHARGE" = успешная оплата, "FAILED" = ошибка и т.д.
                $resultCode = (string)($statusData['resultCode'] ?? '');
                $statusName = strtoupper($statusData['transaction']['statusName'] ?? '');

                // Согласно документации Halyk Bank:
                // resultCode=100 + statusName=(AUTH|CHARGE) = успешная оплата
                // AUTH = авторизация прошла (деньги заблокированы), CHARGE = деньги списаны
                if ($resultCode === '100' && ($statusName === 'CHARGE' || $statusName === 'AUTH')) {
                    // Оплата успешно прошла! Обновляем статус заказа
                    $row['paid'] = 1;
                    $row['paid_at'] = date('Y-m-d H:i:s');
                    $row['payment_status'] = $statusName; // сохраняем статус (AUTH или CHARGE)
                    $row['payment_api_check'] = true; // отмечаем что оплата подтверждена через API
                    $this->fileStore($id, $row);
                    // Продолжаем обработку как успешную оплату (не делаем return)
                } else {
                    // Оплата действительно не прошла
                    $_SESSION['order'] = $row;
                    $_SESSION['order']['payment'] = [
                        'success' => false,
                        'reason'  => 'Оплата не подтверждена банком (код: ' . $resultCode . ', статус: ' . $statusName . ')',
                    ];
                    $this->renderTemplate('fail');
                    return;
                }
            } else {
                // Не удалось проверить статус через API
                $_SESSION['order'] = $row;
                $_SESSION['order']['payment'] = [
                    'success' => false,
                    'reason'  => 'Оплата не подтверждена банком (не удалось проверить статус)',
                ];
                $this->renderTemplate('fail');
                return;
            }
        }

        // 4) Отправляем заказ в МИС (делаем это ДО редиректа, чтобы убедиться в успехе)
        $payload = $row;
        $misResult = $this->misSilent('saveorder', [], 'POST', json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            ['Content-Type: application/json; charset=utf-8']);

        // Если МИС вернул ошибку, показываем страницу ошибки
        if (!$misResult || (isset($misResult['ok']) && !$misResult['ok'])) {
            $_SESSION['payment_result'] = [
                'success' => false,
                'reason'  => 'Ошибка при сохранении заказа в систему',
            ];
            // Используем язык из заказа (если сохранён) или текущий
            $lang = $row['language'] ?? $this->getCurrentLanguage();
            $failUrl = $this->getLanguageUrl('/payment/failed', $lang);
            header("Location: {$failUrl}");
            exit;
        }

        // 5) Сохраняем результат платежа в сессию для отображения на странице
        $_SESSION['payment_result'] = [
            'success'      => true,
            'invoiceId'    => $in['invoiceId'] ?? $in['invoiceID'] ?? $id,
            'reference'    => $in['reference'] ?? null,
            'approvalCode' => $in['approvalCode'] ?? null,
            'cardMask'     => $in['cardMask'] ?? null,
            'dateTime'     => $in['dateTime'] ?? null,
        ];

        // 6) Очищаем локальные следы
        @unlink($this->filePath($id));
        unset($_SESSION['order']);

        // 7) Перенаправляем на чистую страницу результата с сохранённым языком пользователя
        $lang = $row['language'] ?? $this->getCurrentLanguage();
        $successUrl = $this->getLanguageUrl('/payment/success', $lang);
        header("Location: {$successUrl}");
        exit;
    }


    private function apiPaymentFailed($in = [])
    {
        // Согласно документации используется invoiceId
        $id = $in['invoiceId'] ?? $in['invoiceID'] ?? $in['order_id'] ?? ($_SESSION['order']['saved_id'] ?? null);

        // БЕЗОПАСНОСТЬ: Если нет ID заказа, значит это не валидная попытка оплаты
        // Перенаправляем на главную страницу
        if (!$id) {
            header('Location: /');
            exit;
        }

        // Сохраняем данные о неудачном платеже для шаблона
        $row = $this->fileLoad($id);
        if (!$row) {
            // Заказ не найден - перенаправляем на главную
            header('Location: /');
            exit;
        }

        // Сохраняем результат платежа в сессию
        $_SESSION['payment_result'] = [
            'success'    => false,
            'invoiceId'  => $id,
            'reason'     => $in['reason'] ?? 'Оплата отменена',
            'reasonCode' => $in['reasonCode'] ?? null,
            'code'       => $in['code'] ?? 'error',
        ];

        // Если был зарезервирован слот — освободим
        if (!empty($row['doctor']) && !empty($row['time'])) {
            $this->releaseSlot((int)$row['doctor'], (string)$row['time']);
        }

        // Очищаем файл заказа
        @unlink($this->filePath($id));

        // Перенаправляем на чистую страницу результата с сохранённым языком пользователя
        $lang = $row['language'] ?? $this->getCurrentLanguage();
        $failUrl = $this->getLanguageUrl('/payment/failed', $lang);
        header("Location: {$failUrl}");
        exit;
    }


    private function apiStatus($in)
    {
        $id = isset($in['id']) ? $in['id'] : (isset($_SESSION['order']['saved_id']) ? $_SESSION['order']['saved_id'] : null);
        if (!$id) {
            $this->json(['error' => 'id_required'], 400);
            return;
        }
        $row = $this->fileLoad($id);
        if (!$row) {
            $this->json(['result' => 0, 'error' => 'not_found'], 404);
            return;
        }
        $this->json(['result' => 1, 'data' => $row]);
    }

    // getting auth token from halyk
    private function apiHpToken($in)
    {
        $payConfig = $this->cfg['payment'] ?? [];
        $authUrl = isset($payConfig['auth_url']) ? $payConfig['auth_url'] : '';
        $clientId = isset($payConfig['client_id']) ? $payConfig['client_id'] : '';
        $clientSecret = isset($payConfig['client_secret']) ? $payConfig['client_secret'] : '';
        $terminal = isset($payConfig['terminal']) ? $payConfig['terminal'] : '';
        $key = isset($payConfig['key']) ? $payConfig['key'] : null;

        $invoiceID = isset($in['invoiceID']) ? $in['invoiceID'] : null;
        $amount = isset($in['amount']) ? $in['amount'] : null;
        
        $secret_hash = hash('sha256', $invoiceID . $amount . $clientId . $key);

        if (!$authUrl || !$clientId || !$clientSecret || !$terminal || !$key) {
            return ['result' => 0, 'status' => 'payment_not_configured'];
        }

        $body = http_build_query([
            'grant_type' => 'client_credentials',
            'scope' => 'webapi usermanagement email_send verification statement statistics payment',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'invoiceID' => $invoiceID,
            'amount' => $amount,
            'currency' => 'KZT',
            'terminal' => $terminal,
            'secret_hash' => $secret_hash,
        ]);

        $res = $this->curl('POST', $authUrl, $body, ['Content-Type: application/x-www-form-urlencoded']);
        if ($res['status'] >= 200 && $res['status'] < 300) {
            return ['result' => 1, 'body' => $res['body']];
        }
        
        return ['result' => 0, 'status' => $res['status'], 'body' => $res['body']];
    }

    /**
     * Проверка статуса платежа через API Halyk Bank
     * Используется когда callback не пришёл или пришёл для неправильного invoiceId
     * API: GET https://test-epay-api.epayment.kz/check-status/payment/transaction/:invoiceid
     */
    private function checkPaymentStatus($invoiceId)
    {
        $cfg = $this->cfg['payment'] ?? [];
        $statusBaseUrl = $cfg['status_url'] ?? '';
        $authUrl = $cfg['auth_url'] ?? '';
        $clientId = $cfg['client_id'] ?? '';
        $clientSecret = $cfg['client_secret'] ?? '';
        $terminal = $cfg['terminal'] ?? '';

        if (!$statusBaseUrl || !$authUrl || !$clientId || !$clientSecret || !$terminal) {
            return null;
        }

        // Получаем OAuth токен
        $tokenResp = $this->apiHpToken(['invoiceID' => $invoiceId, 'amount' => 1]);
        if (!is_array($tokenResp) || ($tokenResp['result'] ?? 0) != 1) {
            return null;
        }

        $authData = json_decode($tokenResp['body'] ?? '{}', true);
        $token = $authData['access_token'] ?? '';

        if (!$token) {
            return null;
        }

        // Формируем URL согласно документации: GET /check-status/payment/transaction/:invoiceid
        $statusUrl = rtrim($statusBaseUrl, '/') . '/' . $invoiceId;

        // Делаем GET запрос с токеном в заголовке
        $headers = [
            'Authorization: Bearer ' . $token
        ];

        $res = $this->curl('GET', $statusUrl, null, $headers);

        if ($res['status'] >= 200 && $res['status'] < 300) {
            $data = json_decode($res['body'], true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    private function apiPaymentCallback($in)
    {
        // Согласно документации Halyk используется invoiceId (не invoiceID)
        $id = $in['invoiceId'] ?? $in['invoiceID'] ?? $in['order_id'] ?? null;
        if (!$id) {
            $this->json(['result' => 0, 'error' => 'id_required'], 400);
            return;
        }

        $row = $this->fileLoad($id);
        if (!$row) {
            $this->json(['result' => 0, 'error' => 'not_found'], 404);
            return;
        }

        // Согласно документации Halyk: code: "ok" для успеха, code: "error" для ошибки
        $code = strtolower($in['code'] ?? '');
        $status = strtolower($in['status'] ?? '');

        // Проверяем code (приоритет) или status (для обратной совместимости)
        $paid = ($code === 'ok' || in_array($status, ['paid', 'success', 'completed', 'authorized'], true)) ? 1 : 0;

        $row['paid'] = $paid;
        $row['transactionId'] = $in['id'] ?? $in['transactionId'] ?? $in['payment_id'] ?? null;

        // Сохраняем дополнительные поля от Halyk (согласно документации)
        $row['payment'] = [
            'code'         => $in['code'] ?? null,
            'reason'       => $in['reason'] ?? null,
            'reasonCode'   => $in['reasonCode'] ?? null,
            'approvalCode' => $in['approvalCode'] ?? null,
            'reference'    => $in['reference'] ?? null,
            'cardMask'     => $in['cardMask'] ?? null,
            'cardType'     => $in['cardType'] ?? null,
            'dateTime'     => $in['dateTime'] ?? null,
            'raw'          => $in, // полный ответ для отладки
        ];

        $this->fileStore($id, $row);

        $mis = $this->cfg['mis'];
        if (!empty($mis['callback_forward'])) {
            $url = rtrim(isset($mis['base_url']) ? $mis['base_url'] : '', '/') . '/www/payment_callback';
            $this->curl('POST', $url, json_encode([
                'order_id'      => $id,
                'paid'          => $paid,
                'transactionId' => $row['transactionId'],
                'amount'        => isset($row['amount']) ? $row['amount'] : null,
            ]), ['Content-Type: application/json; charset=utf-8']);
        }

        if ($paid && !empty($row['doctor']) && !empty($row['time'])) {
            $this->releaseSlot((int) $row['doctor'], (string) $row['time']);
        }

        $this->json(['result' => 1]);
    }

    private function buildWebsiteUrl($action, $id = null, $query = [])
    {
        $base = rtrim($this->grav['uri']->rootUrl(true), '/');
        $url  = $base . '/' . ltrim($action, '/');
        if ($id !== null && $id !== '') {
            $url .= '/' . rawurlencode((string) $id);
        }
        if (!empty($query)) {
            $qs = http_build_query($query);
            $url .= (str_contains($url, '?') ? '&' : '?') . $qs;
        }
        return $url;
    }

    private function log(string $out)
    {
        try {
            $grav    = \Grav\Common\Grav::instance();
            $locator = $grav['locator'];

            // 1) основное место: user/logs/online-order-fs/api.log
            $logsBase = rtrim($locator->findResource('log://', true, true), '/');
            $dir = $logsBase . '/online-order-fs';
            
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $log = $dir . '/api.log';

            // 2) если не получилось — уходим в /tmp
            if (!is_dir($dir) || (is_dir($dir) && !is_writable($dir))) {
                $dir = sys_get_temp_dir() . '/online-order-fs-logs';
                if (!is_dir($dir)) {
                    @mkdir($dir, 0777, true);
                }
                $log = $dir . '/api.log';
            }

            $line = '[' . date('Y-m-d H:i:s') . '] ' . $out . "\n";

            @file_put_contents($log, $line, FILE_APPEND | LOCK_EX);
        } catch (\Throwable $e) {
            // Ошибка записи в лог
        }
    }

    private function curl($method, $url, $body = null, $headers = [], $timeout = 15)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CUSTOMREQUEST  => strtoupper($method),
            CURLOPT_HTTPHEADER     => $headers,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $resp   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err    = curl_error($ch);
        curl_close($ch);

        $result = [
            'status' => $status,
            'body'   => ($resp ? $resp : ''),
            'error'  => ($err ? $err : null),
        ];

        // -------- LOGGING  --------
        $bodyStr = is_string($resp) ? $resp : json_encode($resp);
        $len     = ($bodyStr !== null) ? strlen($bodyStr) : 0;
        //$snippet = $len ? mb_substr($bodyStr, 0, 200, 'UTF-8') : '';
        $snippet = $len ? $bodyStr : '';

        $line = strtoupper($method) . ' ' . $url . "\n"
        . 'HEADERS: [' . implode('; ', (array) $headers) . ']' . "\n"
        . 'STATUS:' . $status . "\n"
        . ($err ? ' ERROR: ' . $err . "\n" : '')
        //. ($resp ? 'RESPONSE: ' . $resp . "\n" : '')
        . 'LEN: ' . $len . "\n"
            . ($snippet ? 'RESPONCE BODY: ' . str_replace(["\r", "\n"], ' ', $snippet) . "\n" : '')
            . '-----------------------' . "\n";

        $this->log($line);
        // --------------------------------------------------------------

        return $result;
    }

    private function genUniqueId()
    {
        // Halyk Bank требует числовой invoiceId от 6 до 15 цифр
        // Генерируем уникальное число: timestamp (10 цифр) + 5 случайных цифр = 15 цифр
        $timestamp = time(); // текущее время в секундах (10 цифр)

        if (function_exists('random_int')) {
            $random = random_int(10000, 99999); // 5 случайных цифр
        } else {
            $random = mt_rand(10000, 99999);
        }

        return $timestamp . $random; // например: 173272345678901 (15 цифр)
    }
}
