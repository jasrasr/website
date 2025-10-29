
# MPG Fuel Log Tracker

This is a lightweight PHP-based fuel tracking web application.

## Features

- ✅ Per-vehicle fuel logs stored in JSON format
- ✅ MPG calculation (if prior odometer exists)
- ✅ Export to CSV
- ✅ Admin dashboard with plate summaries and raw JSON links
- ✅ Trend chart (MPG over time)
- ✅ All timestamps recorded in Eastern Time (ET)
- ✅ Modern UI with reusable top-right menu

## Files Included

| File              | Description                                      |
|-------------------|--------------------------------------------------|
| index.php         | Entry form for fuel logging                      |
| fuel_form.php     | Modular HTML form used by index.php and save_log |
| save_log.php      | Processes form, saves logs, calculates MPG       |
| export_csv.php    | Exports logs as CSV per plate                    |
| view_latest.php   | Shows most recent log for a plate                |
| view_chart.php    | Displays MPG chart for plate using Chart.js      |
| admin.php         | Admin dashboard listing all plates + stats       |
| menu.php          | Top-right nav menu (icons + tooltips)            |
| php.ini           | Enables full error display + logging             |
| .htaccess         | PHP error override flags                         |
| README.md         | This file                                        |

## Setup Instructions

1. Upload all files to your `/mpg/` directory
2. Ensure `logs/` folder exists and is writable (chmod 755 or 775)
3. Enable PHP 8.2 or higher on your hosting plan
4. Visit `index.php` to begin entering logs

## License

MIT License

---
Author: Jason Lamb (https://jasonlamb.me)  
Generated with ChatGPT · Revision 1.0 · Last Updated: 2025-10-28 21:12:55
