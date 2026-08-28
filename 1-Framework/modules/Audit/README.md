# Audit Module

## Purpose

Record security-relevant and business-relevant actions in a consistent, reviewable format.

## Planned responsibilities

- actor identity
- event type
- affected resource
- before-and-after summaries when appropriate
- timestamp and request ID
- source IP or client context when permitted
- append-oriented storage
- retention and review guidance

## Boundary

Audit records explain who did what and when. Diagnostic implementation details belong in the Logging module.

Audit records must avoid storing passwords, secrets, authentication tokens, or entire sensitive records.