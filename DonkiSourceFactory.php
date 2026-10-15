<?php

namespace Omnilab\Donki;

use Omnilab\Config;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;
use Symfony\Component\HttpClient\HttpClient;

/**
 * NASA's DONKI: space weather events (solar flares, coronal mass
 * ejections, geomagnetic storms...) by activity id or by period - no key
 * needed.
 *
 *   options:
 *     mailto: 'support@glitchr.io'     # in the User-Agent
 *     throttle: 0                      # seconds between two calls: the CCMC publishes no rate
 *     base_uri: 'https://ccmc.gsfc.nasa.gov/DONKI-API/get/'
 */
final class DonkiSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults([
            'omnilab.factory_name' => 'donki',
            'omnilab.required_options' => [],
            'throttle' => 0.0,
            'base_uri' => DonkiSource::BASE_URI,
        ]);
    }

    protected function build(Config $c): SourceInterface
    {
        return new DonkiSource(
            $this->http ?? HttpClient::create(),
            (string) $c->get('base_uri', DonkiSource::BASE_URI),
            ['User-Agent' => self::userAgent($c)],
            (float) $c->get('throttle'),
        );
    }
}
