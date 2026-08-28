<?php
/**
 * Project: Family GPS Tracker
 * File: includes/profile-helpers.php
 * Revision: 1.0.0
 * Description: Sanitizes member profile preferences and builds generated avatar metadata.
 * Author: Jason Lamb / ChatGPT scaffold
 * Created: 2026-07-14
 * Modified: 2026-07-14
 */

declare(strict_types=1);

function profile_text_field(array $input, string $key, int $maxLength): string
{
    $value = isset($input[$key]) ? trim((string)$input[$key]) : '';
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return strlen($value) > $maxLength ? substr($value, 0, $maxLength) : $value;
}

function safe_avatar_url(string $url): string
{
    $url = trim($url);
    if ($url === '' || strlen($url) > 300) {
        return '';
    }
    $parts = parse_url($url);
    if (!is_array($parts)) {
        return '';
    }
    $scheme = strtolower((string)($parts['scheme'] ?? ''));
    return in_array($scheme, ['https', 'http'], true) && !empty($parts['host']) ? $url : '';
}

function generated_avatar_initials(array $user): string
{
    $name = trim((string)($user['displayName'] ?? $user['username'] ?? ''));
    $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(substr($parts[0], 0, 1) . substr($parts[count($parts) - 1], 0, 1));
    }
    $source = $parts[0] ?? $name ?: 'Member';
    return strtoupper(substr($source, 0, 2));
}

function generated_avatar_color(array $user): string
{
    $seed = (string)($user['id'] ?? $user['username'] ?? $user['displayName'] ?? 'member');
    $palette = ['#2563EB', '#059669', '#D97706', '#DC2626', '#7C3AED', '#0891B2', '#BE123C', '#4D7C0F'];
    $index = hexdec(substr(hash('sha256', $seed), 0, 2)) % count($palette);
    return $palette[$index];
}

function user_profile_preferences(array $user): array
{
    $profile = is_array($user['profile'] ?? null) ? $user['profile'] : [];
    $nickname = profile_text_field($profile, 'nickname', 80);
    $avatarUrl = safe_avatar_url((string)($profile['avatarUrl'] ?? ''));
    $avatarMode = profile_text_field($profile, 'avatarMode', 20);
    if (!in_array($avatarMode, ['generated', 'picture'], true) || ($avatarMode === 'picture' && $avatarUrl === '')) {
        $avatarMode = 'generated';
    }

    return [
        'nickname' => $nickname,
        'avatarMode' => $avatarMode,
        'avatarUrl' => $avatarUrl,
        'avatarInitials' => generated_avatar_initials($user),
        'avatarColor' => generated_avatar_color($user),
    ];
}

function apply_user_profile_preferences(array $user, array $input): array
{
    $current = user_profile_preferences($user);
    $nickname = profile_text_field($input, 'nickname', 80);
    $avatarUrl = array_key_exists('avatarUrl', $input) ? safe_avatar_url((string)($input['avatarUrl'] ?? '')) : $current['avatarUrl'];
    $avatarMode = profile_text_field($input, 'avatarMode', 20);
    if (!in_array($avatarMode, ['generated', 'picture'], true)) {
        $avatarMode = $current['avatarMode'];
    }
    if ($avatarMode === 'picture' && $avatarUrl === '') {
        $avatarMode = 'generated';
    }

    $user['profile'] = [
        'nickname' => $nickname,
        'avatarMode' => $avatarMode,
        'avatarUrl' => $avatarUrl,
        'updatedAt' => function_exists('now_iso') ? now_iso() : gmdate('c'),
    ];
    return $user;
}

function public_profile_preferences(array $user): array
{
    return user_profile_preferences($user);
}