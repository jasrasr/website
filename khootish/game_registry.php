<?php
declare(strict_types=1);
require_once __DIR__ . '/lib.php';

const KHOOTISH_GAME_LIFETIME_SECONDS = 86400;

function khootish_expiration_time(array $game): int {
    $created = strtotime((string)($game['created_at'] ?? ''));
    return ($created ?: time()) + KHOOTISH_GAME_LIFETIME_SECONDS;
}

function khootish_expire_stale_games(): int {
    ensure_storage();
    $expired = 0;
    foreach (glob(config('data_dir') . '/games/*.json') ?: [] as $path) {
        $code = clean_code((string)pathinfo($path, PATHINFO_FILENAME));
        if ($code === '') continue;
        $didExpire = with_lock('game:' . $code, LOCK_EX, function() use ($code) {
            $game = decode_file(game_path($code));
            if (!$game) return false;
            $phase = (string)($game['state']['phase'] ?? 'lobby');
            if ($phase === 'finished') return false;
            if (time() < khootish_expiration_time($game)) return false;

            $game['state']['phase'] = 'finished';
            $game['state']['ends_at'] = null;
            $game['finished_at'] = gmdate('c');
            $game['expired_at'] = gmdate('c');
            $game['close_reason'] = 'expired_24_hours';
            $game = archive_game($game);
            $game['revision'] = (int)($game['revision'] ?? 0) + 1;
            $game['updated_at'] = gmdate('c');
            atomic_write(game_path($code), $game);

            $archiveId = clean_id((string)($game['archive_id'] ?? ''));
            if ($archiveId !== '') {
                with_lock('history:' . $archiveId, LOCK_EX, function() use ($archiveId) {
                    $history = decode_file(history_path($archiveId));
                    if (!$history) return;
                    $history['close_reason'] = 'expired_24_hours';
                    $history['expired_at'] = gmdate('c');
                    atomic_write(history_path($archiveId), $history);
                });
            }
            return true;
        });
        if ($didExpire) $expired++;
    }
    return $expired;
}

function khootish_active_games(): array {
    khootish_expire_stale_games();
    ensure_storage();
    $items = [];
    foreach (glob(config('data_dir') . '/games/*.json') ?: [] as $path) {
        $game = decode_file($path);
        if (!$game) continue;
        $phase = (string)($game['state']['phase'] ?? 'lobby');
        if ($phase === 'finished') continue;
        $question = current_question($game);
        $answered = 0;
        if ($question) {
            foreach ($game['players'] ?? [] as $player) {
                if (($player['answered_question'] ?? null) === ($question['id'] ?? null)) $answered++;
            }
        }
        $items[] = [
            'code'=>(string)$game['code'],
            'title'=>(string)$game['title'],
            'quiz_id'=>(string)$game['quiz_id'],
            'phase'=>$phase,
            'team_count'=>count($game['players'] ?? []),
            'question_number'=>max(0, (int)($game['state']['question_index'] ?? -1) + 1),
            'question_count'=>count($game['questions'] ?? []),
            'answered_count'=>$answered,
            'created_at'=>$game['created_at'] ?? null,
            'started_at'=>$game['started_at'] ?? null,
            'expires_at'=>gmdate('c', khootish_expiration_time($game)),
            'expires_in_seconds'=>max(0, khootish_expiration_time($game) - time()),
        ];
    }
    usort($items, fn($a,$b)=>strcmp((string)$b['created_at'], (string)$a['created_at']));
    return $items;
}
