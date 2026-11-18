<?php
/** Revision 1.2.1 — Allow phone setup of an empty directory. */
declare(strict_types=1);

final class FirstAdmin
{
    private const TEMPORARY_DIGEST = 'fcd2db7b339ca7262a0bb78582e5b2268f79cd13dd723e448cbc6ca70d81fb26';
    public const USERNAME = 'super-admin';

    public static function complete(Identity $identity, string $temporary, string $password, string $confirmation): void
    {
        if (!hash_equals(self::TEMPORARY_DIGEST, hash('sha256', $temporary))) {
            throw new InvalidArgumentException('Check the temporary password.');
        }
        if ($password !== $confirmation || $password === $temporary || str_contains($password, "\0")) {
            throw new InvalidArgumentException('Choose a different password and enter it twice.');
        }
        // Identity checks that the store is empty inside the account write lock.
        $identity->account('', self::USERNAME, 'Super Admin', 'super', $password, true);
    }
}
