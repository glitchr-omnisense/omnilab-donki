<?php

namespace Omnilab\Donki\Tests;

use Omnilab\Donki\DonkiSource;
use Omnilab\Donki\DonkiSourceFactory;
use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\ProviderException;
use Omnilab\Model\Domain;
use Omnilab\Model\Identifier;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\OccurrenceQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Fixtures recorded from ccmc.gsfc.nasa.gov/DONKI-API on 2026-10-16: every
 * kind of event from 2026-10-01 to 2026-10-14 (ten flares, 44 CMEs, two
 * storms, nine shocks, no SEP, two magnetopause crossings, two radiation
 * belt enhancements, four high speed streams), and the SEP events and
 * storms of 8 to 14 May 2024 (the Kp 9 storm of 10 May).
 */
final class DonkiSourceTest extends TestCase
{
    /** @var list<array{string, string, string}> kind, startDate, endDate */
    private array $calls = [];

    private function source(?MockResponse $response = null): DonkiSource
    {
        $http = new MockHttpClient(function (string $method, string $url) use ($response): MockResponse {
            $kind = basename((string) parse_url($url, \PHP_URL_PATH));
            parse_str((string) parse_url($url, \PHP_URL_QUERY), $query);
            self::assertStringStartsWith('https://ccmc.gsfc.nasa.gov/DONKI-API/get/', $url);
            $this->calls[] = [$kind, $query['startDate'], $query['endDate']];
            if ($response) {
                return $response;
            }
            $fixture = str_starts_with($query['startDate'], '2024-05') ? $kind.'-2024-05-08.json' : $kind.'-2026-10-01.json';

            return new MockResponse((string) file_get_contents(__DIR__.'/Fixtures/'.$fixture));
        });
        $source = (new DonkiSourceFactory($http))->create();
        self::assertInstanceOf(DonkiSource::class, $source);

        return $source;
    }

    public function testAFlareByItsActivityIdInOneCall(): void
    {
        $flare = $this->source()->entity('2026-10-08T15:29:00-FLR-001');

        self::assertSame([['FLR', '2026-10-08', '2026-10-08']], $this->calls, 'its kind, on the day its id starts with');
        self::assertSame('donki:2026-10-08T15:29:00-FLR-001', $flare->id->key());
        self::assertSame('Solar flare M6.7, 2026-10-08 15:29 UTC', $flare->label);
        self::assertSame('solar flare', $flare->kind);
        self::assertSame(Domain::ASTRONOMY, $flare->domain);
        self::assertSame('2026-10-08T15:29:00+00:00', $flare->time->format(\DATE_ATOM));
        self::assertNull($flare->place, 'on the Sun: no place on the Earth');
        self::assertSame('M6.7', $flare->value('flare_class'));
        self::assertNotNull($flare->value('peak_time'));
        self::assertStringStartsWith('https://ccmc.gsfc.nasa.gov/DONKI/view/FLR/', $flare->url);
        self::assertSame('donki', $flare->property('flare_class')->source);
    }

    public function testACmeAndItsMostAccurateAnalysis(): void
    {
        $cme = $this->source()->entity(Identifier::of(Scheme::DONKI, '2026-10-01T02:36:00-CME-001'));

        self::assertSame('coronal mass ejection', $cme->kind);
        self::assertEquals(new Quantity(476.0, 'km/s'), $cme->value('speed'), 'the analysis marked most accurate, not the first');
        self::assertEquals(new Quantity(10.0, 'deg'), $cme->value('half_angle'));
        self::assertSame('S', $cme->value('cme_type'));
        self::assertSame('SWPC_CAT', $cme->value('measurement'));
        self::assertStringContainsString('SOHO: LASCO/C2', $cme->value('instruments'));
        self::assertStringStartsWith('Faint CME observed to the NE', $cme->description);
    }

    public function testTheStormOfMay2024AndItsLinks(): void
    {
        $storm = $this->source()->entity('donki:2024-05-10T15:00:00-GST-001');

        self::assertSame([['GST', '2024-05-10', '2024-05-10']], $this->calls);
        self::assertSame(9.0, $storm->value('kp_max'));
        self::assertSame(13, $storm->value('kp_readings'));
        self::assertSame('Geomagnetic storm, Kp 9, 2024-05-10 15:00 UTC', $storm->label);
        self::assertNotEmpty($storm->related('linked')->all(), 'the CMEs and shocks it followed');
        foreach ($storm->related('linked') as $linked) {
            self::assertSame(Scheme::DONKI, $linked->scheme);
        }
    }

    public function testUnknownAndRefused(): void
    {
        self::assertNull($this->source()->entity('2026-10-08T15:29:00-FLR-009'));
        foreach (['wikidata:Q123', 'M6.7', Identifier::of(Scheme::USGS_EVENT, 'us6000u24c')] as $id) {
            try {
                $this->source()->entity($id);
                self::fail(\sprintf('%s refused', $id));
            } catch (NotSupportedException) {
            }
        }
        $this->expectException(NotSupportedException::class);
        $this->source()->entity('2026-10-08T15:29:00-XYZ-001');
    }

    public function testEveryEventOfTwoWeeksOutsideTheContract(): void
    {
        $page = $this->source()->events(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-14'));

        self::assertSame(['FLR', 'CME', 'GST', 'IPS', 'SEP', 'MPC', 'RBE', 'HSS'], array_column($this->calls, 0), 'one call per kind');
        self::assertSame(['2026-10-01', '2026-10-14'], [$this->calls[0][1], $this->calls[0][2]]);
        self::assertSame(10 + 44 + 2 + 9 + 0 + 2 + 2 + 4, $page->total);
        self::assertNull($page->next);
        $times = array_map(static fn ($e) => $e->time, $page->items);
        $sorted = $times;
        sort($sorted);
        self::assertEquals($sorted, $times, 'the earliest first');

        $this->calls = [];
        $flares = $this->source()->events(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-14'), ['flr']);
        self::assertSame([['FLR', '2026-10-01', '2026-10-14']], $this->calls);
        self::assertSame(['B3.2', 'B9.3', 'M1.0', 'M1.4', 'M1.8', 'C7.9', 'M6.7', 'M1.3', 'M1.2', 'M6.4'], array_map(static fn ($e) => $e->value('flare_class'), $flares->items));
    }

    public function testWhatIsRefusedBeforeAnyCall(): void
    {
        try {
            $this->source()->events(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-14'), ['XYZ']);
            self::fail('an unknown kind');
        } catch (NotSupportedException) {
        }
        try {
            $this->source()->events(new \DateTimeImmutable('2026-10-14'), new \DateTimeImmutable('2026-10-01'));
            self::fail('a period backwards');
        } catch (\InvalidArgumentException) {
        }
        self::assertSame([], $this->calls);
        $this->expectException(NotSupportedException::class);
        $this->source()->occurrences(new OccurrenceQuery());
    }

    public function testErrorsAndTheFactory(): void
    {
        try {
            $this->source(new MockResponse('API Error: startDate cannot be after endDate.', ['http_code' => 400]))->entity('2026-10-08T15:29:00-FLR-001');
            self::fail('a 400');
        } catch (ProviderException $e) {
            self::assertStringContainsString('startDate cannot be after endDate', $e->getMessage());
        }
        $factory = new DonkiSourceFactory(new MockHttpClient());
        self::assertSame('donki', $factory->getName());
        self::assertSame([Capability::ENTITY], $factory->create()->capabilities());
    }
}
