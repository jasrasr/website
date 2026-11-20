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

    $demoImages = DemoData::defaultImages(['name'=>'Demo Shining Light Cleaning', 'employee'=>'Skyler Grant']);
    check($demoImages['logo'] === 'assets/images/demo/logos/shining-light-cleaning.webp', 'Demo logo correctly matched.');
    check($demoImages['picture'] === 'assets/images/demo/avatars/skyler-grant.webp', 'Demo avatar correctly matched.');
    check(is_file(dirname(__DIR__) . '/' . $demoImages['logo']), 'Bundled logo exists.');
    check(is_file(dirname(__DIR__) . '/' . $demoImages['picture']), 'Bundled avatar exists.');
    check(DemoData::defaultImages(['name'=>'Shining Light Cleaning', 'employee'=>'Skyler Grant']) === [], 'Real businesses do not inherit demo graphics.');
    check(DemoData::defaultImages(['name'=>'Demo Shining Light Cleaning', 'employee'=>'Other Person']) === [], 'Different employees do not inherit demo portraits.');
    $faithful = ['name'=>'Demo Faithful Home Services', 'employee'=>'Sam Mercer',
        'logo'=>'https://example.test/old-logo.png', 'picture'=>'https://example.test/old-avatar.png',
        'images'=>['logo'=>null, 'picture'=>'https://example.test/old-upload.png']];
    $faithfulImages = DemoData::displayImages($faithful);
    check($faithfulImages['logo'] === 'assets/images/demo/logos/faithful-home-services.webp', 'Faithful demo gets its logo despite legacy saved images.');
    check($faithfulImages['picture'] === 'assets/images/demo/avatars/sam-mercer.webp', 'Sam Mercer gets the matching demo portrait despite legacy saved images.');
    check(is_file(dirname(__DIR__) . '/' . $faithfulImages['logo']), 'Faithful demo logo file exists.');
    check(is_file(dirname(__DIR__) . '/' . $faithfulImages['picture']), 'Sam Mercer demo portrait file exists.');
    $other = ['name'=>'Demo Shining Light Cleaning', 'employee'=>'Skyler Grant',
        'logo'=>'https://example.test/custom-logo.png', 'picture'=>'https://example.test/custom-avatar.png'];
    $otherImages = DemoData::displayImages($other);
    check($otherImages['logo'] === $other['logo'] && $otherImages['picture'] === $other['picture'], 'Other demo uploads still take precedence.');
    $unknownImages = DemoData::displayImages(['name'=>'Faithful Home Services', 'employee'=>'Sam Mercer']);
    check($unknownImages === ['picture'=>'','logo'=>''], 'Real business with matching name does not inherit pinned demo images.');
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
