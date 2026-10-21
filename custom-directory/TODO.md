<!--
File Name     : TODO.md
Author        : Jason Lamb (with help from ChatGPT)
Description   : Actionable future work for custom-directory.
Revision      : 1.0.0
Created Date  : 2026-10-20
Modified Date : 2026-10-20
Changelog     :
  Rev 1.0.0 - Created a verified project follow-up list.
-->
# Custom Directory TODO

## Code-informed follow-up

- [ ] Tighten the download handler's root-path boundary check: a plain string-prefix comparison can accept a sibling path whose name begins with the document root.
- [ ] Add focused validation for traversal, sibling-prefix paths, and allowed nested downloads before modifying `download.php`.
- [ ] Document backup and recovery of favorites and download telemetry stored under the project directory.
