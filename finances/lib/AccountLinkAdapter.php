<?php
declare(strict_types=1);
namespace Jasr\Finances;

final class AccountLinkAdapter implements \Jasr\Users\AccountLinkAdapter
{
    public function project(): string { return 'finances'; }
    public function storage(): string { return Accounts::storage(); }
    public function inventory(): array { return Accounts::inventory(); }
    public function withSnapshot(string $legacyId, callable $callback): mixed { return Accounts::snapshot($legacyId, $callback); }
}
