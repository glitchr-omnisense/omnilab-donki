# omnilab/donki

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

## Verified, and not

Against the real service on 2026-10-15 and 2026-10-16: the M6.7 flare of 8 October 2026 by its
id (one call, 0.8 s), a CME (476 km/s, the most accurate of its analyses), the Kp 9 storm of 10
May 2024 (six linked events), an SEP event of 9 May 2024; every event from 1 to 14 October 2026
(73: ten flares, 44 CMEs, two storms, nine shocks, two magnetopause crossings, two radiation belt
enhancements, four high speed streams; 2.1 s); May 2024's 181 flares, 21 of class X; a period
backwards (400) and an unknown kind (404); `api.nasa.gov/DONKI` with `DEMO_KEY` and the old CCMC
address answering 301. The fixtures in `Tests/Fixtures/` are its answers.

Not verified: a rate limit (none published, none met); periods of more than a month in one call.
