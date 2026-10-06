<?php

namespace App\Support\Kunjungan;

/** BR-14: pemeriksaan regex ringan di controller (pengurai penuh ada di job). */
class DeteksiBotCepat
{
    private const POLA = '/bot|crawl|spider|slurp|preview|whatsapp|telegram|facebookexternalhit|facebot|slackbot|discord|twitterbot|linkedinbot|skypeuripreview|curl|wget|python-requests|python-urllib|go-http-client|okhttp|java\/|libwww|httpclient|headlesschrome|phantomjs|lighthouse|pingdom|uptimerobot|monitor/i';

    public static function apakahBot(?string $userAgent): bool
    {
        if ($userAgent === null || trim($userAgent) === '') {
            return true;
        }

        return preg_match(self::POLA, $userAgent) === 1;
    }
}
