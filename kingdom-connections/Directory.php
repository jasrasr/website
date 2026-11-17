<?php
/** Filename: Directory.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Validated business directory and admin-recorded ratings. */
declare(strict_types=1);
use Jasr\Framework\JsonStore;
final class KingdomDirectory
{
    public function __construct(private string $path) {}
    public function all(): array { return array_values(JsonStore::read($this->path)['records']); }
    public static function text(array $input, string $key, int $max, bool $required = false): string {
        if (isset($input[$key]) && !is_string($input[$key])) throw new InvalidArgumentException('Invalid ' . $key . '.');
        $value = trim($input[$key] ?? '');
        if (($required && $value === '') || strlen($value) > $max) throw new InvalidArgumentException('Check ' . $key . ' (maximum ' . $max . ' bytes).');
        return $value;
    }
    public static function url(array $input, string $key): string {
        $value = self::text($input, $key, 2048);
        if ($value !== '' && (!filter_var($value, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($value, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true))) throw new InvalidArgumentException('Use an http or https URL for ' . $key . '.');
        return $value;
    }
    public function save(array $input, string $actor): string {
        $id = self::text($input, 'id', 32);
        if ($id !== '' && !preg_match('/^[a-f0-9]{32}$/D', $id)) throw new InvalidArgumentException('Invalid listing ID.');
        $business = [];
        foreach (['name'=>160, 'about'=>6000, 'testimony'=>6000, 'employee'=>160, 'address'=>500, 'phone'=>80, 'email'=>254] as $key=>$max) $business[$key] = self::text($input, $key, $max, in_array($key, ['name', 'testimony'], true));
        if ($business['email'] !== '' && !filter_var($business['email'], FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Enter a valid email address.');
        foreach (['website', 'picture', 'logo'] as $key) $business[$key] = self::url($input, $key);
        $tags = array_values(array_unique(array_filter(array_map(static fn(string $tag): string => strtolower(trim($tag)), explode(',', self::text($input, 'skills', 1000, true))))));
        if (!$tags || count($tags) > 25 || max(array_map('strlen', $tags)) > 60) throw new InvalidArgumentException('Enter 1–25 skills, each at most 60 bytes.');
        $business['skills'] = $tags;
        $new = $id === ''; $id = $new ? bin2hex(random_bytes(16)) : $id;
        JsonStore::update($this->path, static function(array $records) use ($id, $business, $new, $actor): array {
            if (!$new && !isset($records[$id])) throw new InvalidArgumentException('Listing no longer exists.');
            $records[$id] = array_merge($business, ['id'=>$id, 'ratings'=>$records[$id]['ratings'] ?? [], 'createdAt'=>$records[$id]['createdAt'] ?? gmdate(DATE_ATOM), 'updatedAt'=>gmdate(DATE_ATOM), 'updatedBy'=>$actor]);
            return $records;
        });
        return $id;
    }
    public function rate(array $input, string $actor): void {
        $id = self::text($input, 'id', 32, true);
        $stars = filter_var($input['stars'] ?? null, FILTER_VALIDATE_INT);
        if ($stars === false || $stars < 1 || $stars > 5) throw new InvalidArgumentException('Select 1–5 stars.');
        $comment = self::text($input, 'comment', 2000, true);
        JsonStore::update($this->path, static function(array $records) use ($id, $stars, $comment, $actor): array {
            if (!isset($records[$id])) throw new InvalidArgumentException('Listing no longer exists.');
            $records[$id]['ratings'][] = ['id'=>bin2hex(random_bytes(16)), 'stars'=>$stars, 'comment'=>$comment, 'recordedBy'=>$actor, 'createdAt'=>gmdate(DATE_ATOM), 'source'=>'admin-recorded'];
            return $records;
        });
    }
}
