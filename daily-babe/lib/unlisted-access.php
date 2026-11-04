<?php

declare(strict_types=1);

function unlisted_gallery_identifier(string $babyId): string
{
    if (preg_match('/^[a-f0-9]{24}$/D', $babyId) !== 1) {
        return '';
    }
    return 'u-' . substr(hash('sha256', 'daily-babe-unlisted-v1:' . $babyId), 0, 40);
}

function unlisted_normalize_name(string $name): string
{
    $name = trim((string) preg_replace('/\s+/u', ' ', $name));
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

function unlisted_gallery_credentials_match(array $settings, string $name, string $birthDate): bool
{
    $expectedName = unlisted_normalize_name((string) ($settings['babyName'] ?? ''));
    $expectedFirstName = explode(' ', $expectedName, 2)[0] ?? '';
    $enteredName = unlisted_normalize_name($name);
    $expectedBirthDate = (string) ($settings['birthDate'] ?? '');
    $parsedBirthDate = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    return $expectedName !== ''
        && (hash_equals($expectedName, $enteredName) || hash_equals($expectedFirstName, $enteredName))
        && $parsedBirthDate !== false
        && $parsedBirthDate->format('Y-m-d') === $birthDate
        && hash_equals($expectedBirthDate, $birthDate);
}

