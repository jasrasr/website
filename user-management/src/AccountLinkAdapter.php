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
    /** Canonical existing permission, or null when the project does not specify one.
     * Unknown/unsupported permissions must throw, never silently become a default.
     */
    public function sourceRole(array $snapshot): ?string;
    /** Roles this adapter can safely enforce on linked data. */
    public function supportedRoles(): array;
    /** Hold the project's data lock while invoking the callback with a private snapshot. */
    public function withSnapshot(string $legacyId, callable $callback): mixed;
}
