# Weather Dashboard (PHP)

A lightweight, mobile-friendly PHP weather dashboard powered by the OpenWeather API.

This project is designed around **accuracy first**

* Latitude / Longitude is authoritative
* City names are never trusted alone
* Ambiguous locations are explicitly prevented
* No databases, no cron jobs, no background tasks

---

## Core Principles

* **Lat/Lon is the source of truth**
* City + State is required for human clarity
* ZIP and City/State inputs are helpers, not runtime truth
* Weather updates on page load with caching
* Browser refreshes automatically every hour if left open
* No silent fallbacks to ambiguous city names

---

## Features

* Current temperature
* Feels-like temperature
* Daily high / low
* Weather conditions
* Optional distance sorting from browser location
* Hourly auto-refresh
* Per-city JSON history
* Mobile-first responsive UI

---

## Project Structure

```
/weather
├── index.php                    # UI (mobile friendly)
├── weather_update.php           # Weather fetch and cache engine
├── config.php                   # Authoritative city configuration (lat/lon)
├── geocode_helper.php           # Setup-time helper for lat/lon + ZIP resolution
├── data/
├── ├── README.md
│   ├── weather.json             # Cached dashboard payload (automatically created)
│   └── history/                 # Folder Automatically created
├──     ├── README.md            # File Automatically created
│       ├── parma_oh.json        # City files Automatically created (example)
│       ├── sellersburg_in.json  # (example)
│       └── newhall_ca.json      # (example)
└── README.md
```

---

## Configuration (`config.php`)

All base cities **must** be defined using latitude and longitude.

```php
'cities' => [
    'newhall_ca' => [
        'label' => 'Newhall, CA',
        'lat'   => 34.3792,
        'lon'   => -118.5306,
        'zip'   => '91321'
    ]
]
```

### Why this matters

* Prevents duplicate city-name collisions
* Guarantees correct country and state
* Ensures accurate distance calculations
* Avoids API ambiguity (e.g., Newhall, England)

---

## Adding a New City (Recommended Workflow)

### Step 1: Use the geocode helper

Open in a browser:

```
/weather/geocode_helper.php?q=City,ST
```

or

```
/weather/geocode_helper.php?q=ZIP
```

Example:

```
/weather/geocode_helper.php?q=Newhall,CA
```

---

### Step 2: Verify the output

The helper prints:

* City
* State
* Country
* ZIP (if available)
* Latitude / Longitude

Only proceed if:

* Country is `US`
* State matches expectations
* Lat/Lon values are reasonable

---

### Step 3: Paste into `config.php`

Copy the generated block directly into the `cities` array.

This makes the city authoritative and permanent.

---

## ZIP and Manual City Entry (UI Behavior)

The dashboard UI allows:

* ZIP code entry
* City, ST entry

These entries are:

* Temporary
* Session-only
* Not written to `config.php`
* Intended for quick checks or comparisons

They never override configured base cities.

---

## Caching Behavior

* Weather data is cached in:

```
/data/weather.json
```

* Cache duration is controlled by:

```php
'update_interval_seconds' => 3600
```

* Cache is bypassed when ZIP or manual entries are used
* Cache files can be deleted safely at any time

---

## History Files

Each city has its own history file:

```
/data/history/{city_key}.json
```

Used for:

* Trend analysis
* Charting (future use)
* Auditing weather changes

Retention is controlled by:

```php
'history_points' => 48
```

---

## Time and Timezone Handling

* Timestamps are stored in UTC (ISO 8601)
* Displayed in the browser’s local timezone
* Timezone label is shown in the UI for clarity

---

## Distance Sorting

If browser location access is allowed:

* Cities are sorted by distance
* Distance is displayed in miles
* Uses a haversine calculation

If location access is denied:

* Original city order is preserved

---

## Security Notes

* No database
* No sessions
* No cookies
* No user data stored
* API key exists only in `config.php`
* `geocode_helper.php` is intended for admin/setup use

---

## Known Limitations

* OpenWeather does not always return ZIP codes for every location
* Reverse ZIP lookup is best-effort
* Helper accuracy depends on OpenWeather geocoding data

These limitations are surfaced explicitly and never hidden.

---

## Design Philosophy

This project prioritizes:

* Correctness over convenience
* Explicit configuration over guessing
* Setup-time validation over runtime surprises

If the data is wrong, the UI should fail — not lie.

---

## License

Internal / personal project.

Use, modify, and extend as needed.

---
