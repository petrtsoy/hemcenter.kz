<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class PricelistPlugin extends Plugin
{
    /**
     * Город страницы филиала -> инстанс МИС и его cityid.
     *
     * Обращаться надо прямо к инстансу. Старые адреса his.kz и hems.kz только
     * редиректят: his.kz отдаёт 302 на central.his.kz с сохранением пути,
     * а hems.kz — 302 на корень east.his.kz, теряя /webapi/pricelist,
     * и дальше выкидывает на страницу логина.
     */
    private const ENDPOINTS = [
        'oskemen'   => ['host' => 'https://east.his.kz',    'cityid' => 1],
        'karaganda' => ['host' => 'https://central.his.kz', 'cityid' => 2],
        'astana'    => ['host' => 'https://central.his.kz', 'cityid' => 3],
    ];

    public static function getSubscribedEvents(): array
    {
        return [
            'onTwigExtensions' => ['onTwigExtensions', 0], // добавим Twig-функцию
        ];
    }

    public function onTwigExtensions(): void
    {
        $twig = $this->grav['twig']->twig();

        $twig->addFunction(new \Twig\TwigFunction('pricelist_html', function (?string $city, ?string $lang = null): string {
            return $this->fetchPricelistHtml($city, $lang);
        }));
    }

    private function fetchPricelistHtml(?string $city, ?string $lang = null): string
    {
        if (!$city || !isset(self::ENDPOINTS[$city])) {
            $this->log('неизвестный город: ' . var_export($city, true));

            return 'Нет информации';
        }

        $endpoint = self::ENDPOINTS[$city];
        $baseUrl  = $endpoint['host'] . '/webapi/pricelist?cityid=' . $endpoint['cityid'];

        $html = $lang ? $this->request($baseUrl . '&lang=' . urlencode($lang)) : null;

        // Запасной заход без языка. Астана (cityid=3) на central.his.kz отвечает
        // 200, но HTML-страницей с ошибкой на lang=kk и lang=en. Прайс на русском
        // лучше, чем «Нет информации»: цены в нём всё равно цифрами.
        if ($html === null) {
            $html = $this->request($baseUrl);
        }

        return $html ?? 'Нет информации';
    }

    /**
     * @return string|null HTML прайса либо null, если ответ непригоден
     */
    private function request(string $url): ?string
    {
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($c, CURLOPT_MAXREDIRS, 3);
        curl_setopt($c, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($c, CURLOPT_TIMEOUT, 10);
        $r         = curl_exec($c);
        $http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        $curl_err  = curl_error($c);
        curl_close($c);

        if ($r === false || $r === '' || $http_code !== 200) {
            $this->log(sprintf('%s -> HTTP %s%s', $url, $http_code, $curl_err ? ', curl: ' . $curl_err : ''));

            return null;
        }

        $data = json_decode((string) $r, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data['content'])) {
            // Инстанс отвечает 200 с HTML-страницей ошибки вместо JSON —
            // в теле есть код обращения для поиска в логах МИС.
            $this->log(sprintf('%s -> не JSON или пустой content: %s', $url, trim(substr(strip_tags((string) $r), 0, 200))));

            return null;
        }

        return (string) $data['content'];
    }

    private function log(string $message): void
    {
        $this->grav['log']->warning('pricelist: ' . $message);
    }
}
