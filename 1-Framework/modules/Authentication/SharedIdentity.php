<?php
declare(strict_types=1);
namespace Jasr\Framework;

/** Configured, opt-in identity provider; bootstrap alone never starts a session. */
final class SharedIdentity
{
    public static function connect(?string $bootstrap = null): \Jasr\Users\Auth
    {
        global $frameworkConfig;
        $bootstrap ??= $frameworkConfig['authentication']['provider_bootstrap'] ?? '';
        if ($bootstrap === '' || !is_file($bootstrap)) throw new \RuntimeException('Shared identity provider is unavailable.');
        require_once $bootstrap;
        return \jasr_users_auth();
    }
}
