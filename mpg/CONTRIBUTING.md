# Contributing to MPG Fuel Log Tracker

Contributions are welcome. This project is primarily maintained as a practical personal fuel tracker, but changes should be designed so another person can install, understand, and use it without knowing the author's environment.

## Development principles

- Prefer correct and transparent data over clever automation.
- Preserve backward compatibility with existing JSON logs whenever practical.
- Never silently reinterpret or overwrite explicit user-entered values.
- Keep the mobile entry workflow fast.
- Treat GPS, OCR, EXIF, AI, and heuristics as suggestions that require confirmation when ambiguity matters.
- Keep secrets, API keys, device data, and private fuel logs out of the repository.

## Typical workflow

1. Review `README.md`, `ROADMAP.md`, and `docs/ARCHITECTURE.md` before making a behavioral change.
2. Identify the smallest coherent change.
3. Update code and any related data-model handling.
4. Test both manual entry and photo-assisted entry when the change touches shared save logic.
5. Test with old JSON entries that do not contain newly introduced fields.
6. Update file revision headers when the project convention uses them.
7. Update `ROADMAP.md` when a planned item is completed or materially changed.
8. Update `CHANGELOG.md` under `Unreleased`.
9. Use a concise commit message that says what changed and why.

## Versioning

Use a lightweight semantic-versioning model:

- `MAJOR` for incompatible data-model or workflow changes.
- `MINOR` for backward-compatible features.
- `PATCH` for fixes, small improvements, validation, and documentation.

Individual PHP files may also retain their own revision number in the file header. File revisions and project release versions serve different purposes and do not need to match.

## JSON data compatibility

Existing log entries may not contain newer fields such as `fill_type`, comments, station details, or location metadata. Code should therefore use safe defaults when reading optional fields.

Examples of expected defaults:

- Missing `fill_type` should be treated as `full` for legacy data unless there is clear evidence otherwise.
- Missing comments should be treated as an empty value.
- Missing location/station data should not block charts, exports, or statistics.

Do not bulk-rewrite historical user logs merely to add new fields unless a migration is explicitly requested and backed up.

## Fuel-calculation changes

Fuel math is core data logic. Changes should be tested against at least these cases:

1. First-ever entry.
2. Normal full fill following a full fill.
3. One partial fill followed by a full fill.
4. Multiple partial fills followed by a full fill.
5. Bad or unchanged odometer input.
6. Two-of-three price/gallons/total calculation.
7. Legacy entry without `fill_type`.

A partial fuel event should remain in history. It should not receive a misleading standalone MPG value. MPG should be calculated at the next full fill using the complete full-to-full mileage and the sum of fuel added since the previous full fill.

## Station and location data

Keep station brand separate from physical station location. A brand such as `Speedway` may have many physical stations.

A physical station profile may contain:

- brand/name
- city
- street address or intersection
- latitude/longitude
- optional nickname

Normalize brands for duplicate detection while preserving a user-friendly display value.

## Security and privacy

Do not commit:

- `.env` files or API keys
- raw private fuel logs unless intentionally sanitized for examples
- device identifiers or whitelist files containing private information
- precise personal location history intended only for production data

Location capture must remain optional.

## AI-assisted changes

AI-generated code should be reviewed the same way as human-written code. In particular:

- verify calculations independently
- confirm old data still loads
- avoid inventing external API behavior
- document new dependencies or network services
- favor explicit code over opaque "smart" behavior in core fuel calculations

## Documentation

If behavior changes, documentation changes with it. At minimum check:

- `README.md`
- `ROADMAP.md`
- `CHANGELOG.md`
- `docs/ARCHITECTURE.md` when the design or data model changes
