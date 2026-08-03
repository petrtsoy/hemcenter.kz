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

        foreach ($this->cfg['mis'] as $mis) {
            $base = rtrim($mis['base_url'] ?? '', '/');
            if (!$base) continue;

            $headers = ['Accept: application/json'];
            $auth    = $mis['auth'] ?? [];
            $mode    = strtolower($auth['mode'] ?? 'none');

            if ($mode === 'jwt' && !empty($auth['secret'])) {
                $jwt = $this->getToken($base, $auth['secret']);
                if ($jwt) {
                    $headers[] = 'Authorization: Bearer ' . $jwt;
                }
            } elseif ($mode === 'apikey' && !empty($auth['api_key'])) {
                $headers[] = 'X-API-Key: ' . $auth['api_key'];
            }

            $url  = $base . '/' . $meta['action'] . '?' . http_build_query(['hash' => $hash]);
            $res  = $this->curl('GET', $url, null, $headers);
            $data = json_decode((string)($res['body'] ?? ''), true);

            if ($res['status'] === 200 && !empty($data['ok'])) {
                $found   = true;
                $docHtml = (string)($data['data']['html'] ?? '');
                break;
            }
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

    private function getToken(string $base, string $secret): ?string
    {
        $ch = curl_init(rtrim($base, '/') . '/issuetoken');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['secret' => $secret]),
            CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $resp = curl_exec($ch);
        curl_close($ch);

        $data = json_decode((string)$resp, true);
        return (!empty($data['ok']) && !empty($data['data']['token']))
            ? $data['data']['token']
            : null;
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
        curl_close($ch);

        return ['status' => $status, 'body' => $resp ?: ''];
    }
}
