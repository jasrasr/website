<?php
declare(strict_types=1);

// Compatibility redirect for existing bookmarks after the organization dashboard rename.
header('Location: org.php', true, 301);
exit;
