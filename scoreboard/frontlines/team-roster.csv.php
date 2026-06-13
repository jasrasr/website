<?php declare(strict_types=1);
/**
 * Filename: frontlines/team-roster.csv.php
 * Revision : 1.2.0
 * Description : CSV export for the public Frontlines team roster.
 * Author : Jason Lamb (with help from Codex CLI)
 * Created Date : 2026-06-09
 * Modified Date : 2026-06-09
 * Changelog :
 * 1.0.0 Initial CSV export with name, team, gender, and grade columns
 * 1.1.0 Added role column after name
 * 1.2.0 Reordered columns and included gender probability from roster helper
 */

require __DIR__ . '/scoreboard_lib.php';
require __DIR__ . '/team_roster.php';

$data = readFrontlinesRosterData();

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="frontlines-team-roster.csv"');

$handle = fopen('php://output', 'wb');
fputcsv($handle, frontlinesRosterCsvHeaders());
foreach (frontlinesRosterCsvRows($data) as $row) {
    fputcsv($handle, $row);
}
fclose($handle);
