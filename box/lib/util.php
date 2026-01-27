<?php
function formatTimestampLocal(string $iso): string {
    try {
        $dt = new DateTime($iso, new DateTimeZone('America/New_York'));
        return $dt->format('M j, Y g:i A T');
    } catch (Exception $e) {
        return $iso;
    }
}
