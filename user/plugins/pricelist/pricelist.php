<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

class PricelistPlugin extends Plugin
{
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
        $cities = [
            'oskemen'   => 1,
            'karaganda' => 2,
            'astana'    => 3,
        ];

        if (!$city || !array_key_exists($city, $cities)) {
            return 'Нет информации';
        }

        $cityId = $cities[$city];
        $baseUrl = ($cityId === 1)
            ? 'https://hems.kz/webapi/pricelist?cityid=1'
            : 'https://his.kz/webapi/pricelist?cityid=' . $cityId;

        // Добавляем язык, если указан
        $url = $baseUrl;
        if ($lang) {
            $url .= '&lang=' . urlencode($lang);
        }

        // --- Вариант с cURL (как в твоём WP-сниппете) ---
        $c = curl_init($url);
        curl_setopt($c, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($c, CURLOPT_TIMEOUT, 10);
        $r = curl_exec($c);
        $http_code = curl_getinfo($c, CURLINFO_HTTP_CODE);
        curl_close($c);

        if (empty($r) || $http_code !== 200) {
            return 'Нет информации';
        }

        $data = json_decode($r, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data) || empty($data['content'])) {
            return 'Нет информации';
        }

        // Возвращаем HTML для вставки в страницу (поэтому |raw в Twig)
        // Добавляем комментарий для отладки
        $debug = '<!-- Pricelist URL: ' . htmlspecialchars($url) . ' | Lang: ' . htmlspecialchars($lang ?? 'null') . ' -->';
        return $debug . "\n" . (string) $data['content'];
    }
}
