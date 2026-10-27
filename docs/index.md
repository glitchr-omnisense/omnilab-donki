# omnilab/donki

NASA's DONKI (the Space Weather Database Of Notifications, Knowledge, Information, at the CCMC:
`https://ccmc.gsfc.nasa.gov/DONKI-API/get/`) for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab):
space weather events - solar flares, coronal mass ejections, geomagnetic storms, interplanetary
shocks, solar energetic particle events, magnetopause crossings, radiation belt enhancements,
high speed streams - by activity id, as entities; outside the contract, every event of a period.
No key.

## Installation

```sh
composer require omnilab/donki
```

```php
use Omnilab\Donki\DonkiSourceFactory;

$donki = (new DonkiSourceFactory($httpClient))->create([
    'mailto' => 'support@glitchr.io',  // optional, in the User-Agent
    'throttle' => 0,                   // optional: seconds between two calls
]);
```

With the Symfony bundle, the factory is registered as `donki` when the bundle knows it; otherwise
register `DonkiSourceFactory` as a service (it is autoconfigured as a `SourceFactoryInterface`).

## Credentials

None: DONKI's API at the CCMC takes no key since its move on 30 September 2026 (the former
`api.nasa.gov/DONKI` with its `DEMO_KEY` answers 301).

## Options

The factory's options, with the defaults it applies (`DonkiSourceFactory`):

| Option | Type | Default | Required | Effect |
|---|---|---|---|---|
| `mailto` | `?string` | null | no | a contact address, sent in the User-Agent |
| `user_agent` | `?string` | null | no | the whole User-Agent, in place of the one made from `mailto` |
| `throttle` | `float` | `0` | no | seconds left between two calls: the CCMC publishes no rate |
| `base_uri` | `string` | `https://ccmc.gsfc.nasa.gov/DONKI-API/get/` | no | DONKI's API (`<KIND>?startDate=&endDate=` is joined to it) |

## Capabilities

`capabilities()` answers `entity`; an operation it does not answer throws `NotSupportedException` before any call.

| Capability | Supported | |
|---|---|---|
| `entity` | yes | an activity id (`2026-10-08T15:29:00-FLR-001`, read by its shape), `donki:...`; one call, the list of its kind on its day; a kind not read and another base's identifier refused before any call |
| `search` | no | DONKI has no search by text |
| `series` | no |  |
| `products` | no |  |
| `occurrences` | no | an event of space weather has no place on the Earth: `events()`, outside the contract, lists a period's |
| `datasets` | no |  |
| `jobs` | no |  |
| `open` | no |  |
| `structure` | no |  |
| `cross_references` | no | none: the events it links are `related['linked']` |
| `forecast` | no |  |
| `historical` | no |  |
| `area` | no |  |
| `station` | no |  |
| `cloud_cover` | no |  |

Outside the contract, on `Omnilab\Donki\DonkiSource`: `events(\DateTimeInterface $from,
\DateTimeInterface $to, array $kinds = []): Page` - the events of the kinds named
(`DonkiSource::KINDS`: `FLR`, `CME`, `GST`, `IPS`, `SEP`, `MPC`, `RBE`, `HSS`; all by default)
whose time falls in the period, the earliest first, as one page with its `total` (one call per
kind).

## Which API

DONKI's public API at `https://ccmc.gsfc.nasa.gov/DONKI-API/get/<KIND>?startDate=&endDate=`
(since 30 September 2026; the old `kauai.ccmc.gsfc.nasa.gov/DONKI/WS/get/` and
`api.nasa.gov/DONKI/` answer 301 to a news page - checked on 2026-10-15). One list per kind of
event, JSON, no key, no paging. Kinds read: `FLR`, `CME`, `GST`, `IPS`, `SEP`, `MPC`, `RBE`,
`HSS`.

| Method | Reads | Calls |
|---|---|---|
| `entity()` | an activity id (`2026-10-08T15:29:00-FLR-001`, read by its shape), `donki:...`, an `Identifier` of the `donki` scheme | `<KIND>?startDate=<its day>&endDate=<its day>`, the event whose id it is |
| `events()` | outside the contract: a period (whole days, UTC), kinds (all by default) | one list per kind, joined, the earliest first; one `Page`, its `total` the count |

An id of a kind not read (`...-XYZ-001`) and another base's identifier are refused before any
call; an id no event of its day has is `null`. DONKI's errors (`API Error: startDate cannot be
after endDate.`, a 400; an unknown kind, a 404) map as any service's; `events()` refuses an unknown
kind and a period backwards before any call.

## What an entity holds

`id` in the `donki` scheme, `label` (`Solar flare M6.7, 2026-10-08 15:29 UTC`, `Geomagnetic storm,
Kp 9, 2024-05-10 15:00 UTC`), `domain` astronomy, `kind` (`solar flare`, `coronal mass ejection`,
`geomagnetic storm`, `interplanetary shock`, `solar energetic particle event`, `magnetopause
crossing`, `radiation belt enhancement`, `high speed stream`), `time` (a flare's begin, a CME's
or a storm's start, the others' event time; UTC), no `place`, `description` the analysts' note,
`url` the event's DONKI page, `related['linked']` the events DONKI links it to (a storm to its
CMEs and shocks), and:

| Key | Kinds | Value |
|---|---|---|
| `instruments` | all | text: `SOHO: LASCO/C2, ...` |
| `source_location`, `active_region` | FLR, CME | text `S20W62`; NOAA number |
| `flare_class`, `begin_time`, `peak_time`, `end_time` | FLR | text: `M6.7`; UTC |
| `speed`, `half_angle`, `latitude`, `longitude`, `cme_type`, `time_21_5`, `measurement`, `analyses` | CME (the analysis marked most accurate, else the first) | Quantity km/s, deg; text; number |
| `kp_max`, `kp_readings` | GST | number |
| `location` | IPS | text: `Earth`, `STEREO A` |
| `catalog`, `notifications` | all that have them | text; number |

## What DONKI does not give here

The notifications and reports, the WSA-ENLIL simulations of a CME (`enlilList`) and its other
analyses, the CME analyses endpoint, the forecasts and scoreboards, and the events' own pages'
details beyond the lists.

## Example

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Donki\DonkiSourceFactory;
use Symfony\Component\HttpClient\HttpClient;

$donki = (new DonkiSourceFactory(HttpClient::create()))->create(['mailto' => 'you@example.org']);

$storm = $donki->entity('2024-05-10T15:00:00-GST-001');
echo $storm->label, ': Kp max ', $storm->value('kp_max'), ', linked to ', count($storm->related('linked')), " events\n";

$week = $donki->events(new DateTimeImmutable('2026-10-19'), new DateTimeImmutable('2026-10-25'), ['FLR', 'GST']);
printf("%d flares and storms from 2026-10-19 to 10-25; the first three:\n", $week->total);
foreach (array_slice($week->items, 0, 3) as $event) {
    echo '  ', $event->id->value, '  ', $event->label, "\n";
}
```

```
Geomagnetic storm, Kp 9, 2024-05-10 15:00 UTC: Kp max 9, linked to 6 events
2 flares and storms from 2026-10-19 to 10-25; the first three:
  2026-10-19T08:54:00-FLR-001  Solar flare M1.6, 2026-10-19 08:54 UTC
  2026-10-23T03:43:00-FLR-001  Solar flare C1.3, 2026-10-23 03:43 UTC
```

(as answered on 2026-10-27: three calls - the storm's day, then the flares and the storms of the
week)

```yaml
# config/packages/omnilab.yaml
omnilab:
    sources:
        donki: { factory: donki, options: { mailto: '%env(OMNILAB_MAILTO)%' } }
```

`events()` is a method of `DonkiSource`, not of the interface the bundle injects: check the
instance before calling it.

## Errors

Every call goes through `HttpSource`: a 404 or a 410 (and a 204) is null - an empty page for a list -; a 429 is a `RateLimitedException` (`retryAfter` from `Retry-After`); a 5xx, a time-out or a host that does not answer an `UnavailableException`; any other 4xx a `ProviderException` with its `status` and what the service said; an answer that is not JSON a `ProviderException`, a web page answered for JSON an `UnavailableException`.

Before any call: an identifier of another scheme, or an activity id of a kind not read
(`NotSupportedException`); in `events()`, an unknown kind (`NotSupportedException`) and a period
that ends before it starts (`\InvalidArgumentException`). DONKI's own errors (`API Error:
startDate cannot be after endDate.`, a 400; an unknown kind, a 404) map as above. An id no event
of its day has is null.

## Verified, and not

Against the real service on 2026-10-15 and 2026-10-16: the M6.7 flare of 8 October 2026 by its
id (one call, 0.8 s), a CME (476 km/s, the most accurate of its analyses), the Kp 9 storm of 10
May 2024 (six linked events), an SEP event of 9 May 2024; every event from 1 to 14 October 2026
(73: ten flares, 44 CMEs, two storms, nine shocks, two magnetopause crossings, two radiation belt
enhancements, four high speed streams; 2.1 s); May 2024's 181 flares, 21 of class X; a period
backwards (400) and an unknown kind (404); `api.nasa.gov/DONKI` with `DEMO_KEY` and the old CCMC
address answering 301. The fixtures in `Tests/Fixtures/` are its answers.

Again on 2026-10-27: the Kp 9 storm by its id (six linked events) and the flares and storms of
19 to 25 October 2026 (the example above).

Not verified: a rate limit (none published, none met); periods of more than a month in one call.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by NASA or its Community Coordinated Modeling Center. DONKI's records are NASA's and its
partners'; the CCMC's publications policy asks that work using its services acknowledge the CCMC,
and DONKI and the CCMC be credited as the source, as the README's
[Terms of use](https://github.com/glitchr-omnisense/omnilab-donki#terms-of-use) says. Whoever runs
the package is responsible for complying with them and for any use made of what it returns: see
the README's [Responsibility](https://github.com/glitchr-omnisense/omnilab-donki#responsibility)
section.
