<?php

namespace Omnilab\Donki;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Model\Quantity;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\HttpSource;
use Omnilab\Source\Page;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NASA's DONKI, the Database Of Notifications, Knowledge, Information of
 * the Community Coordinated Modeling Center (NASA Goddard): space weather
 * events - solar flares, coronal mass ejections, geomagnetic storms,
 * interplanetary shocks, solar energetic particle events, magnetopause
 * crossings, radiation belt enhancements, high speed streams - one by its
 * activity id (entity()), or every event of some kinds in a period
 * (events(), outside the contract), each with its time, its note, the
 * instruments that saw it and the events it is linked to.
 *
 * Why not occurrences(): an occurrence has a place on the Earth (a Point),
 * and these happen on the Sun (a flare's source is a heliographic location,
 * S20W62), in the heliosphere (a CME, a shock at L1) or around the whole
 * Earth (a storm's Kp is planetary): none has a place the core reads.
 *
 * No key: since 30 September 2026 the CCMC serves DONKI's API at
 * ccmc.gsfc.nasa.gov/DONKI-API (api.nasa.gov/DONKI, which took the
 * DEMO_KEY, and kauai.ccmc.gsfc.nasa.gov answer 301 to a news page).
 */
final class DonkiSource extends HttpSource
{
    public const BASE_URI = 'https://ccmc.gsfc.nasa.gov/DONKI-API/get/';

    /** The kinds of event: DONKI's code => [the id's field, the time's field, the word]. */
    public const KINDS = [
        'FLR' => ['flrID', 'beginTime', 'solar flare'],
        'CME' => ['activityID', 'startTime', 'coronal mass ejection'],
        'GST' => ['gstID', 'startTime', 'geomagnetic storm'],
        'IPS' => ['activityID', 'eventTime', 'interplanetary shock'],
        'SEP' => ['sepID', 'eventTime', 'solar energetic particle event'],
        'MPC' => ['mpcID', 'eventTime', 'magnetopause crossing'],
        'RBE' => ['rbeID', 'eventTime', 'radiation belt enhancement'],
        'HSS' => ['hssID', 'eventTime', 'high speed stream'],
    ];

    public function __construct(
        HttpClientInterface $http,
        string $baseUri = self::BASE_URI,
        array $headers = [],
        float $throttle = 0.0,
    ) {
        parent::__construct($http, $baseUri, $headers, $throttle);
    }

    public function getName(): string
    {
        return 'donki';
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY];
    }

    /**
     * An event by its activity id (2026-10-04T06:13:00-FLR-001,
     * donki:..., an Identifier of the donki scheme): one call, its kind's
     * list on the day the id starts with. Null when that day has no such
     * event; another identifier is refused before any call.
     */
    public function entity(Identifier|string $id): ?Entity
    {
        $identifier = self::identify($id, Scheme::DONKI) ?? throw NotSupportedException::identifier('donki', (string) $id);
        [, $day, $kind] = preg_match('~^((\d{4}-\d{2}-\d{2})T[\d:]+)-([A-Z]+)-\d{3}$~', $identifier->value, $m) ? [$m[1], $m[2], $m[3]] : [null, null, null];
        if (!isset(self::KINDS[$kind])) {
            throw NotSupportedException::identifier('donki', $identifier->value, 'look up an event of this kind:');
        }
        foreach ($this->list($kind, $day, $day) as $event) {
            if (($event[self::KINDS[$kind][0]] ?? null) === $identifier->value) {
                return $this->toEntity($kind, $event);
            }
        }

        return null;
    }

    /**
     * The events of some kinds (DonkiSource::KINDS: FLR, CME, GST... all
     * when none is named) whose time falls in the period, the earliest
     * first, as one page (DONKI pages nothing; one call per kind). Outside
     * the contract: see the class.
     *
     * @param list<string> $kinds
     *
     * @return Page<Entity>
     */
    public function events(\DateTimeInterface $from, \DateTimeInterface $to, array $kinds = []): Page
    {
        $kinds = array_map('strtoupper', $kinds ?: array_keys(self::KINDS));
        foreach ($kinds as $kind) {
            if (!isset(self::KINDS[$kind])) {
                throw NotSupportedException::operation('donki', \sprintf('list events of the kind "%s" (it lists %s)', $kind, implode(', ', array_keys(self::KINDS))));
            }
        }
        $start = \DateTimeImmutable::createFromInterface($from)->setTimezone(new \DateTimeZone('UTC'));
        $end = \DateTimeImmutable::createFromInterface($to)->setTimezone(new \DateTimeZone('UTC'));
        if ($start > $end) {
            throw new \InvalidArgumentException(\sprintf('The period ends (%s) before it starts (%s).', $end->format('Y-m-d'), $start->format('Y-m-d')));
        }
        $items = [];
        foreach ($kinds as $kind) {
            foreach ($this->list($kind, $start->format('Y-m-d'), $end->format('Y-m-d')) as $event) {
                if (\is_array($event) && null !== ($entity = $this->toEntity($kind, $event))) {
                    $items[] = $entity;
                }
            }
        }
        usort($items, static fn (Entity $a, Entity $b) => [$a->time, (string) $a->id] <=> [$b->time, (string) $b->id]);

        return new Page($items, null, \count($items));
    }

    /** @return list<array<string, mixed>> */
    private function list(string $kind, string $from, string $to): array
    {
        $data = $this->getJson($kind, ['startDate' => $from, 'endDate' => $to]) ?? [];

        return array_values(array_filter($data, 'is_array'));
    }

    /** @param array<string, mixed> $event */
    private function toEntity(string $kind, array $event): ?Entity
    {
        [$idField, $timeField, $word] = self::KINDS[$kind];
        $id = Identifier::tryOf(Scheme::DONKI, (string) ($event[$idField] ?? ''));
        if (null === $id) {
            return null;
        }
        $time = self::time($event[$timeField] ?? null);
        $properties = [];
        $add = static function (string $key, string $label, mixed $value) use (&$properties): void {
            if (null !== $value && '' !== $value && [] !== $value) {
                $properties[] = new Property($key, $label, $value, 'donki');
            }
        };
        $add('instruments', 'Instruments', implode(', ', array_filter(array_map(static fn (mixed $i) => \is_array($i) ? self::text($i['displayName'] ?? null) : null, (array) ($event['instruments'] ?? [])))));
        $add('source_location', 'Source location (heliographic)', self::text($event['sourceLocation'] ?? null));
        $add('active_region', 'NOAA active region', is_numeric($event['activeRegionNum'] ?? null) ? (int) $event['activeRegionNum'] : null);
        $add('catalog', 'Catalogue', self::text($event['catalog'] ?? null));
        $label = ucfirst($word);
        if ('FLR' === $kind) {
            $class = self::text($event['classType'] ?? null);
            $add('flare_class', 'X-ray class (GOES)', $class);
            $add('begin_time', 'Begins (UTC)', self::time($event['beginTime'] ?? null)?->format('Y-m-d H:i'));
            $add('peak_time', 'Peaks (UTC)', self::time($event['peakTime'] ?? null)?->format('Y-m-d H:i'));
            $add('end_time', 'Ends (UTC)', self::time($event['endTime'] ?? null)?->format('Y-m-d H:i'));
            $label = 'Solar flare'.(null === $class ? '' : ' '.$class);
        }
        if ('CME' === $kind) {
            $analyses = array_values(array_filter((array) ($event['cmeAnalyses'] ?? []), 'is_array'));
            $best = array_values(array_filter($analyses, static fn (array $a) => true === ($a['isMostAccurate'] ?? null)))[0] ?? $analyses[0] ?? null;
            if (null !== $best) {
                $add('speed', 'Speed (most accurate analysis)', is_numeric($best['speed'] ?? null) ? new Quantity((float) $best['speed'], 'km/s') : null);
                $add('half_angle', 'Half angle', is_numeric($best['halfAngle'] ?? null) ? new Quantity((float) $best['halfAngle'], 'deg') : null);
                $add('latitude', 'Direction: latitude (heliographic)', is_numeric($best['latitude'] ?? null) ? new Quantity((float) $best['latitude'], 'deg') : null);
                $add('longitude', 'Direction: longitude (heliographic)', is_numeric($best['longitude'] ?? null) ? new Quantity((float) $best['longitude'], 'deg') : null);
                $add('cme_type', 'Type (SCORE scale: S, C, O, R, ER)', self::text($best['type'] ?? null));
                $add('time_21_5', 'At 21.5 solar radii (UTC)', self::time($best['time21_5'] ?? null)?->format('Y-m-d H:i'));
                $add('measurement', 'Measured by', self::text($best['measurementTechnique'] ?? null));
            }
            $add('analyses', 'Analyses', \count($analyses) ?: null);
        }
        if ('GST' === $kind) {
            $kp = array_values(array_filter(array_map(static fn (mixed $k) => \is_array($k) && is_numeric($k['kpIndex'] ?? null) ? (float) $k['kpIndex'] : null, (array) ($event['allKpIndex'] ?? [])), static fn (?float $v) => null !== $v));
            $add('kp_max', 'Highest Kp index', $kp ? max($kp) : null);
            $add('kp_readings', 'Kp readings', \count($kp) ?: null);
            if ($kp) {
                $label .= ', Kp '.rtrim(rtrim(\sprintf('%.2F', max($kp)), '0'), '.');
            }
        }
        if ('IPS' === $kind) {
            $add('location', 'Where it was seen', self::text($event['location'] ?? null));
        }
        $linked = [];
        foreach ((array) ($event['linkedEvents'] ?? []) as $link) {
            $linked[] = \is_array($link) ? Identifier::tryOf(Scheme::DONKI, (string) ($link['activityID'] ?? '')) : null;
        }
        $notifications = array_filter((array) ($event['sentNotifications'] ?? []), 'is_array');
        $add('notifications', 'Notifications sent', \count($notifications) ?: null);

        return new Entity(
            id: $id,
            label: $label.(null === $time ? '' : ', '.$time->format('Y-m-d H:i').' UTC'),
            domain: Domain::ASTRONOMY,
            kind: $word,
            description: self::text($event['note'] ?? null),
            properties: $properties,
            url: \is_string($event['link'] ?? null) ? $event['link'] : null,
            time: $time,
            source: 'donki',
            related: ($links = new Identifiers($linked))->all() ? ['linked' => $links] : [],
        );
    }
}
