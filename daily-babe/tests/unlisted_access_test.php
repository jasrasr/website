<?php

declare(strict_types=1);

require dirname(__DIR__) . '/lib/unlisted-access.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$babyId = '0123456789abcdef01234567';
$identifier = unlisted_gallery_identifier($babyId);
assert_true(preg_match('/^u-[a-f0-9]{40}$/D', $identifier) === 1, 'Unlisted identifier must be opaque.');
assert_true($identifier === unlisted_gallery_identifier($babyId), 'Unlisted identifier must be stable.');
assert_true($identifier !== unlisted_gallery_identifier('fedcba9876543210fedcba98'), 'Different galleries must have different identifiers.');
assert_true(!str_contains($identifier, 'hadley') && !str_contains($identifier, '11022026'), 'Identifier must not contain gallery details.');

$settings = ['babyName' => 'Hadley Elizabeth', 'birthDate' => '2026-11-02'];
assert_true(unlisted_gallery_credentials_match($settings, 'Hadley Elizabeth', '2026-11-02'), 'Exact credentials should match.');
assert_true(unlisted_gallery_credentials_match($settings, '  HADLEY   ELIZABETH ', '2026-11-02'), 'Name matching should ignore case and repeated spaces.');
assert_true(unlisted_gallery_credentials_match($settings, 'Hadley', '2026-11-02'), 'The first name should match for family-friendly access.');
assert_true(!unlisted_gallery_credentials_match($settings, 'Elizabeth', '2026-11-02'), 'An unrelated name fragment must not match.');
assert_true(!unlisted_gallery_credentials_match($settings, 'Hadley Elizabeth', '2026-11-03'), 'Wrong birth dates must not match.');
assert_true(!unlisted_gallery_credentials_match($settings, 'Hadley Elizabeth', '11/02/2026'), 'Birth date must use a valid date value.');

fwrite(STDOUT, "Unlisted gallery identifier and credential tests passed.\n");

