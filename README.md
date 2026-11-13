# omnilab/donki

NASA's DONKI - the Database Of Notifications, Knowledge, Information of the Community
Coordinated Modeling Center (NASA Goddard) - for [omnisense/omnilab](https://github.com/glitchr-omnisense/omnilab):
space weather events - solar flares (GOES class, begin, peak and end, source, active region),
coronal mass ejections (speed, half angle, direction and type of the most accurate analysis),
geomagnetic storms (the highest Kp), interplanetary shocks, solar energetic particle events,
magnetopause crossings, radiation belt enhancements, high speed streams - one by its activity
id, or every event of some kinds in a period (outside the contract), each with its time, its
note, the instruments that saw it and the events it is linked to. No key.

```php
$donki = (new DonkiSourceFactory($http))->create(['mailto' => 'support@glitchr.io']);
$flare = $donki->entity('2026-10-08T15:29:00-FLR-001');
$flare->label;                              // Solar flare M6.7, 2026-10-08 15:29 UTC
$storm = $donki->entity('2024-05-10T15:00:00-GST-001');
$storm->value('kp_max');                    // 9.0
$storm->related('linked')->keys();          // the CMEs and shocks that led to it (donki:...)
$donki->events(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-10-14'));       // 73 events
$donki->events(new \DateTimeImmutable('2024-05-01'), new \DateTimeImmutable('2024-05-31'), ['FLR']); // 181 flares, 21 of class X
```

```yaml
omnilab:
    sources:
        donki:
            factory: donki
            options:
                mailto: 'support@glitchr.io'   # in the User-Agent
```

**Why not `occurrences()`**: an occurrence has a place on the Earth, and these events happen on
the Sun (a flare's source is a heliographic location, `S20W62`), in the heliosphere (a CME, a
shock seen at L1) or around the whole Earth (a storm's Kp is planetary). So `events()` is a method
of this source, and an event's `place` is null.

**No key, and why not `api.nasa.gov`**: the brief of this package named `api.nasa.gov/DONKI` and
its public `DEMO_KEY`; on 2026-10-15 that address, like `kauai.ccmc.gsfc.nasa.gov/DONKI/WS`,
answered 301 to the CCMC's announcement that since 30 September 2026 the API is served at
`ccmc.gsfc.nasa.gov/DONKI-API/get/`, without a key - which this package calls.

See [docs/](docs/index.md) for what is read, how it maps, and what DONKI does not give here.

## Install and a first call

```sh
composer require omnilab/donki
```

A real call, with no account and no key: the geomagnetic storm of 10 May 2024 in NASA's DONKI.
`mailto` is your address, sent in the User-Agent so that the service can reach you before it blocks
a caller; the source keeps to the limits its [`policy.json`](policy.json) declares.

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnilab\Donki\DonkiSourceFactory;

$donki = (new DonkiSourceFactory())->create(['mailto' => 'you@example.org']);   // your address, in the User-Agent
$storm = $donki->entity('2024-05-10T15:00:00-GST-001');
echo $storm->label, ', Kp max ', $storm->value('kp_max'), "\n";
```

Its output on 2026-11-13:

```
Geomagnetic storm, Kp 9, 2024-05-10 15:00 UTC, Kp max 9
```

## Terms of use

DONKI is a service of NASA's Community Coordinated Modeling Center (Goddard Space Flight Center);
its records are NASA's and its partners' (the M2M Space Weather Analysis Office, NOAA's SWPC for
the Kp). The CCMC publishes a publications policy on ccmc.gsfc.nasa.gov asking that work using its
services acknowledge the CCMC; credit DONKI and the CCMC as the source. No rate is published.

## Usage policy

Declared as data in [`policy.json`](policy.json) (read at https://ccmc.gsfc.nasa.gov/tools/DONKI/ on 2026-11-10), and honoured on every request by the
core: a limit reached waits when its window ends within `max_wait` seconds, else the request is not
sent ([Usage policies](https://github.com/glitchr-omnisense/omnilab/blob/1.x/docs/policies.md)).

- Limits: not published (unknown).
- Licence: not stated; commercial use: unknown.
- Credit: not required: We acknowledge the Community Coordinated Modeling Center (CCMC) at Goddard Space Flight Center for the use of DONKI, https://ccmc.gsfc.nasa.gov/tools/DONKI/ (https://ccmc.gsfc.nasa.gov/tools/DONKI/).
- Needs: nothing.

## Responsibility

This package is an independent, unofficial client: it is not affiliated with, endorsed by or
sponsored by NASA or its Community Coordinated Modeling Center. It reaches their service on behalf of whoever runs it, and it does not
redistribute their data beyond the short excerpts recorded as test fixtures. Its MIT licence
covers this package's code only and grants no right to the service or to its data.

Whoever installs and runs this package is responsible for complying with the terms above, for
obtaining whatever they require (an account, a key, a licence or subscription, an attribution or
a citation, a rate limit to respect), and for any use made of what it returns. The authors
publish it free of charge, make no commercial use of it, and accept no liability for how it is
used (see [LICENSE](LICENSE)).

License: MIT.
