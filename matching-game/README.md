# Matching Game

A memory game for young children. Choose **Colors**, **Letters**, or **Numbers** and press **New game**. Tap or click two blocks to reveal them. Matches stay visible and other blocks turn over after a short pause. The default board is 4×4. Choose any grid from 2×2 through 6×6 with an even number of blocks.

The timer starts with the first block and stops when the last pair is found. The page shows attempts, completed games, pairs found, and the best time for each mode and board size in the current browser tab session. This score uses session storage when available and never requires an account.

## Optional account history

Public play needs no sign-in. To save finished games to a private account history, deploy this project beside `1-Framework` and `user-management` on a PHP 8.1+ host. Register project ID `matching-game` in User Management at its deployed path and grant intended users **Member** access. Signed-in members can view their 50 most recent completed games, including mode, board size, time, and tries. No card order or moves are uploaded.

Keep `matching-game/data/` writable by PHP and blocked from direct HTTP access. The included `.htaccess` denies access on Apache/LiteSpeed; configure the equivalent for other servers. Runtime score files are ignored by Git. If shared identity is unavailable, the game remains public and session scores still work.
