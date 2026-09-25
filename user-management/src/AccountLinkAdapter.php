<?php
declare(strict_types=1);
namespace Jasr\Users;

/** Server-registered adapters only. Browser input must never select filesystem paths. */
interface AccountLinkAdapter
{
    public function project(): string;
    public function storage(): string;
    /** Public metadata only; never return passwords, hashes, or private record contents. */
    public function inventory(): array;
    /** Hold the project's data lock while invoking the callback with a private snapshot. */
    public function withSnapshot(string $legacyId, callable $callback): mixed;
}
