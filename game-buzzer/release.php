<?php
declare(strict_types=1);

// Update these together for every Game Buzzer release. This is the source
// revision time, not the visitor's current time or the server deployment time.
const GB_REVISION = '1.2.0';
const GB_UPDATED_AT = '2026-10-08T11:55:00Z';

function gb_release_footer(string $page): void {
    $updated = new DateTimeImmutable(GB_UPDATED_AT);
    $display = $updated->setTimezone(new DateTimeZone('America/New_York'))->format('M j, Y · g:i A T');
    echo '<footer><small>Game Buzzer · ' . htmlspecialchars($page, ENT_QUOTES, 'UTF-8')
        . '<br>Revision ' . GB_REVISION . ' · Updated <time datetime="' . GB_UPDATED_AT . '">'
        . $display . '</time></small></footer>';
}
