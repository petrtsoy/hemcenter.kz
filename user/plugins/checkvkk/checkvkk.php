<?php
/**
 * CheckVKK Plugin for Grav CMS
 *
 * Обрабатывает URL /checkvkk/{hash} и /checklist/{hash} — проверка по QR-коду
 * заключения ВКК и листа нетрудоспособности соответственно.
 * Обращается к МИС API (website.php?action=checkvkk|checklist) и отображает результат.
 */

namespace Grav\Plugin;

use Grav\Common\Plugin;

class CheckvkkPlugin extends Plugin
{
    /**
     * Маршрут → action МИС-API и подписи на странице.
     * Ключ совпадает с первым сегментом URL из QR-кода в PDF.
     */
    private const ROUTES = [
        'checkvkk' => [
            'action'   => 'checkvkk',
            'title'    => 'Заключение врачебно-консультационной комиссии',
            'notfound' => 'По данному QR-коду заключение ВКК не найдено или недоступно.',
        ],
        'checklist' => [
            'action'   => 'checklist',
            'title'    => 'Лист нетрудоспособности',
            'notfound' => 'По данному QR-коду лист нетрудоспособности не найден или недоступен.',
        ],
    ];

    /** Токен МИС живёт 12 часов; кешируем с запасом, чтобы не отдать протухший. */
    private const TOKEN_TTL = 39600; // 11 часов

    private array $cfg = [];

    public static function getSubscribedEvents(): array
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
        ];
    }

    public function onPluginsInitialized(): void
    {
        if ($this->isAdmin()) {
            return;
        }

        if (!$this->config->get('plugins.checkvkk.enabled')) {
            return;
        }

        $this->cfg = $this->config->get('plugins.checkvkk');

        $this->enable([
            'onPagesInitialized'  => ['onPagesInitialized', 0],
            'onTwigTemplatePaths' => ['onTwigTemplatePaths', 0],
        ]);
    }

    public function onTwigTemplatePaths(): void
    {
        $this->grav['twig']->twig_paths[] = __DIR__ . '/templates';
    }

    public function onPagesInitialized(): void
    {
        $path = rtrim($this->grav['uri']->path(), '/');

        // Убираем языковой префикс (/kk/checkvkk/... → /checkvkk/...)
        $lang = $this->grav['language'];
        if ($lang->enabled()) {
            foreach ($lang->getLanguages() as $code) {
                if (strpos($path, "/{$code}/") === 0 || $path === "/{$code}") {
                    $path = substr($path, strlen("/{$code}"));
                    break;
                }
            }
        }

        if (preg_match('~^/(checkvkk|checklist)/([a-zA-Z0-9]{13,14})$~', $path, $m)) {
            $this->handleCheck($m[1], $m[2]);
        }
    }

    private function handleCheck(string $route, string $hash): void
    {
        $meta    = self::ROUTES[$route];
        $docHtml = '';
        $found   = false;

        foreach (($this->cfg['mis'] ?? []) as $mis) {
            $html = $this->queryInstance($mis, $meta['action'], $hash);
            if ($html !== null) {
                $found   = true;
                $docHtml = $html;
                break;
            }
        }

        // Обращения к медицинским данным фиксируются (приказ МЗ РК №79, п.32).
        $this->log('info', sprintf(
            'checkdoc route=%s hash=%s result=%s ip=%s',
            $route,
            $hash,
            $found ? 'found' : 'notfound',
            $_SERVER['REMOTE_ADDR'] ?? '-'
        ));

        if (!headers_sent()) {
            // Страницы проверки — по одноразовой ссылке с бланка, в индексе им не место.
            header('X-Robots-Tag: noindex, nofollow', true);
            header('Cache-Control: no-store, max-age=0', true);
        }

        $twig = $this->grav['twig'];
        $twig->init();
        $html = $twig->processTemplate('checkvkk.html.twig', [
            'found'    => $found,
            'vkk_html' => $docHtml,
            'title'    => $meta['title'],
            'notfound' => $meta['notfound'],
        ]);
        echo $html;
        exit;
    }

    /**
     * Опрашивает один инстанс МИС. Возвращает готовый HTML или null, если
     * документа там нет либо инстанс недоступен.
     */
    private function queryInstance(array $mis, string $action, string $hash): ?string
    {
        $base = rtrim($mis['base_url'] ?? '', '/');
        if (!$base) {
            return null;
        }

        $auth = $mis['auth'] ?? [];
        $mode = strtolower($auth['mode'] ?? 'none');
        $url  = $base . '/' . $action . '?' . http_build_query(['hash' => $hash]);

        // Первая попытка — с токеном из кеша; вторая (только на 401) — со свежим:
        // так переживаем ротацию секрета и протухший токен без ручного сброса кеша.
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $headers = ['Accept: application/json'];

            if ($mode === 'jwt' && !empty($auth['secret'])) {
                $jwt = $this->token($base, (string)$auth['secret'], $attempt > 0);
                if ($jwt === null) {
                    $this->log('warning', "checkdoc: не получен токен от {$base}");
                    return null;
                }
                $headers[] = 'Authorization: Bearer ' . $jwt;
            } elseif ($mode === 'apikey' && !empty($auth['api_key'])) {
                $headers[] = 'X-API-Key: ' . $auth['api_key'];
            }

            $res  = $this->curl('GET', $url, null, $headers);
            $data = json_decode((string)($res['body'] ?? ''), true);

            if ($res['status'] === 200 && !empty($data['ok'])) {
                return (string)($data['data']['html'] ?? '');
            }

            // 401 на первой попытке — токен мог протухнуть или сменился секрет.
            if ($res['status'] === 401 && $mode === 'jwt' && $attempt === 0) {
                continue;
            }

            // 404 — документа в этом инстансе нет, это штатно и в лог не идёт.
            if ($res['status'] !== 404) {
                $this->log('warning', sprintf(
                    'checkdoc: %s ответил %d%s',
                    $base,
                    $res['status'],
                    !empty($res['error']) ? ' (' . $res['error'] . ')' : ''
                ));
            }
            return null;
        }

        return null;
    }

    /** JWT из кеша Grav; $force — выбросить кешированный и запросить заново. */
    private function token(string $base, string $secret, bool $force = false): ?string
    {
        $key   = 'checkvkk-jwt-' . md5($base . '|' . $secret);
        $cache = $this->grav['cache'];

        if (!$force) {
            $cached = $cache->fetch($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $jwt = $this->issueToken($base, $secret);
        if ($jwt !== null) {
            $cache->save($key, $jwt, self::TOKEN_TTL);
        }
        return $jwt;
    }

    private function issueToken(string $base, string $secret): ?string
    {
        $res = $this->curl(
            'POST',
            rtrim($base, '/') . '/issuetoken',
            http_build_query(['secret' => $secret]),
            ['Content-Type: application/x-www-form-urlencoded'],
            5
        );

        $data = json_decode((string)($res['body'] ?? ''), true);
        if (!empty($data['ok']) && !empty($data['data']['token'])) {
            return (string)$data['data']['token'];
        }

        $this->log('warning', sprintf(
            'checkdoc: issuetoken на %s вернул %d%s',
            $base,
            $res['status'],
            !empty($data['error']['code']) ? ' / ' . $data['error']['code'] : ''
        ));
        return null;
    }

    private function curl(string $method, string $url, $body, array $headers, int $timeout = 10): array
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
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);

        return ['status' => $status, 'body' => $resp ?: '', 'error' => $error];
    }

    /** Пишет в logs/grav.log. Раньше сбои молча выглядели как «не найдено». */
    private function log(string $level, string $message): void
    {
        if (isset($this->grav['log'])) {
            $this->grav['log']->{$level}($message);
        }
    }
}
