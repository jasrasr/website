# Weather Dashboard (PHP / JSON)

Author: Jason Lamb  
Current UI Revision: 2.1  

## Overview
A lightweight PHP-based weather dashboard that updates lazily on page load.
No database, no cron jobs, no background services.

## Features
- Multi-city support
- ZIP code input for temporary cities
- Per-city rolling history files
- Mobile-friendly UI
- Browser-local timestamp with timezone clarity
- Hourly update guard

## File Structure
- `index.php` – UI and ZIP input
- `weather_update.php` – API fetch, caching, history
- `config.php` – Configuration
- `data/weather.json` – Latest snapshot
- `data/history/*.json` – Per-city history

## Requirements
- PHP 8+
- OpenWeatherMap API key
- Writable `data/` directory

## Notes
- ZIP-based cities are session-only
- History length configurable in `config.php`
- Timestamps stored in UTC and displayed in local browser time

## License
Personal / internal use.
