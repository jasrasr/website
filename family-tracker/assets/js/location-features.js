/**
 * Project: Family GPS Tracker
 * File: assets/js/location-features.js
 * Revision: 1.0.0
 * Description: Shared routing ETA and geofence overlay helpers for browser UI and Node tests.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-14
 * Modified: 2026-07-14
 */
(function (root, factory) {
    'use strict';
    if (typeof module === 'object' && module.exports) module.exports = factory();
    else root.FamilyTrackerLocationFeatures = factory();
}(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    function pointLat(point) { return Number(point && (point.latitude ?? point.lat)); }
    function pointLon(point) { return Number(point && (point.longitude ?? point.lon)); }
    function validPoint(point) { return Number.isFinite(pointLat(point)) && Number.isFinite(pointLon(point)); }

    function osrmRouteUrl(from, to) {
        if (!validPoint(from) || !validPoint(to)) return '';
        return 'https://router.project-osrm.org/route/v1/driving/'
            + pointLon(from) + ',' + pointLat(from) + ';' + pointLon(to) + ',' + pointLat(to)
            + '?overview=false&alternatives=false&steps=false';
    }

    function formatEtaSeconds(seconds) {
        var totalMinutes = Math.max(1, Math.round(Number(seconds || 0) / 60));
        if (totalMinutes < 60) return totalMinutes + ' min';
        var hours = Math.floor(totalMinutes / 60);
        var minutes = totalMinutes % 60;
        return hours + ' hr' + (hours === 1 ? '' : 's') + (minutes ? ' ' + minutes + ' min' : '');
    }

    function formatDistanceMeters(meters) {
        var miles = Math.max(0, Number(meters || 0)) / 1609.344;
        if (miles < 10) return miles.toFixed(1) + ' mi';
        return Math.round(miles) + ' mi';
    }

    function routeSummaryFromOsrm(data) {
        var route = data && Array.isArray(data.routes) ? data.routes[0] : null;
        if (!route || !Number.isFinite(Number(route.duration)) || !Number.isFinite(Number(route.distance))) return null;
        return {
            etaText: formatEtaSeconds(Number(route.duration)),
            distanceText: formatDistanceMeters(Number(route.distance)),
            durationSeconds: Number(route.duration),
            distanceMeters: Number(route.distance)
        };
    }

    function normalizeGeofenceZone(zone) {
        var latitude = Number(zone && zone.latitude);
        var longitude = Number(zone && zone.longitude);
        var radiusMeters = Number(zone && zone.radiusMeters);
        var id = String((zone && zone.id) || '').trim();
        if (!id || !Number.isFinite(latitude) || !Number.isFinite(longitude) || !Number.isFinite(radiusMeters) || radiusMeters <= 0) return null;
        return {
            id: id,
            name: String((zone && zone.name) || 'Unnamed place').trim() || 'Unnamed place',
            latitude: latitude,
            longitude: longitude,
            radiusMeters: radiusMeters
        };
    }

    return {
        osrmRouteUrl: osrmRouteUrl,
        formatEtaSeconds: formatEtaSeconds,
        routeSummaryFromOsrm: routeSummaryFromOsrm,
        normalizeGeofenceZone: normalizeGeofenceZone
    };
}));

