<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Future improvements for the debt payoff planner.
Revision      : 1.0.0
Created Date  : 2026-07-25
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Added metadata and clarified existing backup scope and validation needs.
-->
# Future Upgrades

The items below are proposed enhancements. The current app already has snapshot backup and restore; the encrypted export and deployment options remain separate future work.

## Data and Modeling
- Add recurring extra-payment calendars instead of a single annual blue moon payment.
- Add adjustable interest-rate schedules for teaser APRs, HELOC resets, and ARM mortgage changes.
- Add escrow, taxes, insurance, and dynamic minimum-payment formulas for credit cards and mortgages.
- Add import and export for CSV, JSON, and spreadsheet formats.

## User Experience
- Add payoff charts, balance history, and printable payoff reports.
- Add filters, archive mode, and closed-loan history.
- Add due-date reminders and statement-date tracking.
- Add optional two-factor authentication and email-based password reset.

## Admin and Deployment
- Add audit logging for admin actions.
- Add configurable password policy and forced password rotation for the sample account.
- Add database-backed storage for multi-server deployments.
- Extend the existing backup/restore flow with encrypted export options for user data.

## Code-informed follow-up

- Add regression checks for rate-schedule and recurring-payment calculations before changing the amortization model.
- Define how imported loans and changed interest schedules interact with the existing audit and snapshot restore flows.
