<?php
/*
    Filename    : footer.php
    Revision    : 1.1.0
    Description : Shared HTML footer included at the bottom of every page
    Author      : Jason Lamb (with help from Claude Code CLI)
    Created     : 2026-04-27
    Modified    : 2026-04-27
    Changelog   :
    1.0.0 initial release
    1.1.0 added revision, updated date, and live timezone/time display
*/
?>
    <p class="small">Revision <?= h(APP_REVISION) ?> | Updated <?= h(APP_UPDATED) ?> | <?= h(date('g:i A T')) ?>.</p>
</div>
</body>
</html>
