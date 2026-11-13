# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable release: it installs with Composer's default minimum-stability (stable).

### Added

- Omnilab DONKI: NASA's space weather events from the CCMC's Database Of Notifications, Knowledge,
  Information - solar flares (GOES class, begin, peak, end, source, active region), coronal mass
  ejections (speed, half angle, direction, type), geomagnetic storms (Kp), interplanetary shocks,
  solar energetic particle events, magnetopause crossings, radiation belt enhancements, high speed
  streams - by activity id, or every event of a period, with the events each is linked to; no key
  needed.

### Release

- Requires `omnisense/omnilab` `^1.0`, stable versions, where it required `@dev` ones:
  `composer require omnilab/donki` installs in a project at Composer's default minimum-stability
  (stable). `extra.branch-alias` names the branch (`dev-1.x`: `1.x-dev`).
- CI: `.github/workflows/ci.yml` (glitchr-studio/ci's php-package.yml, pinned: the package
  installed alone, PHPUnit, PHPStan against its baseline, a consumer install) and `release.yml`
  (that CI on the tag, then glitchr-studio/ci's php-release.yml); `phpunit.xml.dist` runs the
  package's own tests.
- README: the install line and a first call.
