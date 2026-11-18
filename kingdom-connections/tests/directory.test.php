<?php
/** Filename: directory.test.php | Revision: 1.0.0 | Author: Jason Lamb with ChatGPT
 * Created: 2026-11-17 | Modified: 2026-11-17 | Description: Isolated directory persistence and validation tests. */
declare(strict_types=1);
if (PHP_SAPI !== "cli") { http_response_code(404); exit; }
require_once dirname(__DIR__) . '/bootstrap.php';
function check(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function rejects(callable $action): void {
    try { $action(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException('Expected invalid input rejection.');
}
$temp = sys_get_temp_dir() . '/kingdom-test-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    $store = new KingdomDirectory($temp . '/directory.json');
    check($store->all() === [], 'Fresh directory is empty.');
    $input = ['name'=>'Synthetic Test Business', 'skills'=>' Plumbing, electrical, PLUMBING ', 'testimony'=>'Synthetic faith statement', 'about'=>'<script>test</script>', 'website'=>'https://example.test'];
    $id = $store->save($input, 'test-admin');
    check($store->all()[0]['skills'] === ['plumbing','electrical'], 'Skills normalized and deduplicated.');
    check($store->all()[0]['ratings'] === [], 'No fabricated ratings.');
    $store->rate(['id'=>$id, 'stars'=>'1', 'comment'=>'Synthetic low feedback'], 'test-admin');
    $store->rate(['id'=>$id, 'stars'=>'5', 'comment'=>'Synthetic high feedback'], 'test-admin');
    check(str_starts_with(ratingLabel($store->all()[0]), '3.0 / 5'), 'Average computed from records.');
    $input['id'] = $id; $input['name'] = 'Renamed synthetic business';
    $store->save($input, 'other-admin');
    $b = $store->all()[0];
    check(count($b['ratings']) === 2 && $b['updatedBy'] === 'other-admin', 'Edits preserve reviews.');
    check($b['ratings'][0]['source'] === 'admin-recorded', 'Feedback provenance retained.');
    check(h($b['about']) === '&lt;script&gt;test&lt;/script&gt;', 'HTML escaped.');
    foreach (['0','6','1.5','abc'] as $stars) rejects(fn() => $store->rate(['id'=>$id,'stars'=>$stars,'comment'=>'test'], 'test-admin'));
    foreach (['javascript:alert(1)','data:image/png;base64,AAAA','//example.test/logo.png'] as $url) rejects(fn() => $store->save(array_replace($input, ['logo'=>$url]), 'test-admin'));
    rejects(fn() => $store->save(array_replace($input, ['email'=>'invalid']), 'test-admin'));
    rejects(fn() => $store->save(array_replace($input, ['skills'=>' , ']), 'test-admin'));
    rejects(fn() => $store->save(array_replace($input, ['name'=>['invalid']]), 'test-admin'));
    rejects(fn() => $store->save(array_replace($input, ['id'=>str_repeat('f',32)]), 'test-admin'));
    check(count($store->all()) === 1 && count($store->all()[0]['ratings']) === 2, 'Invalid writes do not alter data.');
    file_put_contents($temp . '/directory.json', '{broken');
    try { $store->all(); throw new RuntimeException('Corrupt JSON must fail closed.'); } catch (JsonException $e) {}
    echo "Directory persistence, ratings, validation and escaping checks passed.\n";
} finally {
    foreach (glob($temp . '/*') ?: [] as $file) unlink($file);
    rmdir($temp);
}
