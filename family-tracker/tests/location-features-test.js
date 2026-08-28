/**
 * Project: Family GPS Tracker
 * File: tests/location-features-test.js
 * Revision: 1.0.0
 * Description: Verifies routing ETA helpers and geofence overlay normalization.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-14
 * Modified: 2026-07-14
 */
'use strict';

const assert = require('assert');
const features = require('../assets/js/location-features.js');

const from = { latitude: 41.5, longitude: -81.7 };
const to = { latitude: 41.6, longitude: -81.8 };
assert.strictEqual(
  features.osrmRouteUrl(from, to),
  'https://router.project-osrm.org/route/v1/driving/-81.7,41.5;-81.8,41.6?overview=false&alternatives=false&steps=false',
  'OSRM route URLs should use lon,lat ordering for both points.'
);

assert.strictEqual(features.formatEtaSeconds(540), '9 min', 'Short ETA should be rounded to minutes.');
assert.strictEqual(features.formatEtaSeconds(5400), '1 hr 30 min', 'Long ETA should include hours and minutes.');
assert.deepStrictEqual(
  features.routeSummaryFromOsrm({ routes: [{ duration: 540, distance: 12345 }] }),
  { etaText: '9 min', distanceText: '7.7 mi', durationSeconds: 540, distanceMeters: 12345 },
  'OSRM summaries should expose display text and raw values.'
);

assert.deepStrictEqual(
  features.normalizeGeofenceZone({ id: 'zone-1', name: 'Home', latitude: '41.5', longitude: '-81.7', radiusMeters: '250' }),
  { id: 'zone-1', name: 'Home', latitude: 41.5, longitude: -81.7, radiusMeters: 250 },
  'Geofence zones should normalize numeric overlay fields.'
);

assert.strictEqual(features.normalizeGeofenceZone({ id: 'bad', latitude: 'x', longitude: -81, radiusMeters: 250 }), null, 'Invalid zones should be ignored.');

console.log('location-features-test passed');
