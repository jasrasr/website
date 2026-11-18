<?php
/** Filename: config.local.example.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Optional host-only storage override. */
declare(strict_types=1);
return ['storage_path' => __DIR__ . '/storage']; // Prefer an existing writable directory outside web root.
