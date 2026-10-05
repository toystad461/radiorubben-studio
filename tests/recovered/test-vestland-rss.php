<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/integrations/NewsDesk.php';
require dirname(__DIR__, 2) . '/app/board.php';
$checks = 0;
function vestland_check(bool $ok, string $why): void { $GLOBALS['checks']++; if (!$ok) throw new RuntimeException($why); }
function vestland_xml(string $url, string $title = 'Syntetisk lokal testnyhet', string $date = 'Sat, 03 Oct 2026 10:00:00 GMT'): string {
    return '<rss version="2.0"><channel><title>Testdata, ikke NRK-innhold</title><item><title>'.htmlspecialchars($title).'</title><link>'.htmlspecialchars($url).'</link><description>&lt;p&gt;Konstruert testbeskrivelse.&lt;/p&gt;</description><pubDate>'.$date.'</pubDate></item></channel></rss>';
}
$specs = newsdesk_sources();$top = 'nrk_vestland_toppsaker';$latest = 'nrk_vestland_siste';
vestland_check($specs[$top]['feed'] === 'https://www.nrk.no/vestland/toppsaker.rss' && $specs[$latest]['feed'] === 'https://www.nrk.no/vestland/siste.rss', 'Both exact user-selected feeds');
vestland_check(count($specs) === 3 && isset($specs['bomlo']) && !in_array('https://www.nrk.no/nyheter/siste.rss', array_column($specs, 'feed'), true), 'Regional NRK scope replaces national source and retains municipality');
$at = '2026-10-03T10:10:00Z';$now = strtotime($at);
$url = 'https://www.nrk.no/vestland/eksempel-1.12345678';
$a = newsdesk_parse_rss(vestland_xml($url), $top, $specs[$top], $at);
$b = newsdesk_parse_rss(vestland_xml($url.'?utm_source=rss#avsnitt', 'Oppdatert testtittel', 'Sat, 03 Oct 2026 10:05:00 GMT'), $latest, $specs[$latest], $at);
vestland_check(count($a) === 1 && count($b) === 1 && $a[0]['id'] === $b[0]['id'] && $b[0]['url'] === $url, 'Tracking and feed membership cannot duplicate an article');
$c = newsdesk_parse_rss(vestland_xml('https://www.nrk.no/vestland/ny-tittel-1.12345678'), $latest, $specs[$latest], $at);
vestland_check($c[0]['id'] === $a[0]['id'], 'Headline URL changes preserve NRK article identity');
$d = newsdesk_parse_rss(vestland_xml('https://www.nrk.no/vestland/annen-sak-1.99999999'), $latest, $specs[$latest], $at);
vestland_check($d[0]['id'] !== $a[0]['id'], 'Different articles are not merged by equal headline');
$feed = static fn(array $items, string $status = 'updated') => ['status'=>$status,'items'=>$items,'fetchedAt'=>$at];
$sections = newsdesk_sections([$top=>$feed($a),$latest=>$feed(array_merge($b,$d))]);
vestland_check(count($sections['nrk']['items']) === 2 && $sections['nrk']['items'][0]['title'] === 'Oppdatert testtittel', 'One shared card uses newest dated copy');
vestland_check($sections['nrk']['items'][0]['isTopStory'] && count($sections['nrk']['items'][0]['feedIds']) === 2, 'Top story and both feed memberships retained');
$partial = newsdesk_sections([$top=>$feed($b,'stale'),$latest=>$feed($a)]);
vestland_check($partial['nrk']['status'] === 'partial' && $partial['nrk']['items'][0]['title'] === $a[0]['title'], 'Current copy wins over later dated stale cache');
vestland_check(count(newsdesk_sections([$top=>$feed($a)])['nrk']['items']) === 1, 'One feed outage does not hide other feed');
vestland_check(newsdesk_parse_rss(vestland_xml(str_replace('www.nrk.no','evil.example',$url)), $top, $specs[$top], $at) === [], 'Foreign hosts refused');
vestland_check(newsdesk_parse_rss(vestland_xml('https://name:secret@www.nrk.no/vestland/test-1.12345678'), $top, $specs[$top], $at) === [], 'URL credentials refused');
vestland_check(newsdesk_parse_rss(vestland_xml($url, 'Fremtid', 'Sun, 04 Oct 2026 10:00:00 GMT'), $top, $specs[$top], $at) === [], 'Future publication is not presented as current news');
vestland_check(newsdesk_decode_rss('<!DOCTYPE rss><rss><channel/></rss>', $top, $specs[$top], $at) === null, 'XML external declarations rejected');
vestland_check(newsdesk_decode_rss('<html>Access denied</html>', $top, $specs[$top], $at) === null, 'Blocked pages are not a news feed');

$dir = sys_get_temp_dir().'/rr-vestland-'.bin2hex(random_bytes(6));mkdir($dir,0700);
$calls = 0;$response = vestland_xml($url);
$fetch = static function(string $target) use (&$calls,&$response,$specs,$top): ?string {
    vestland_check($target === $specs[$top]['feed'], 'Only fixed source requested');$calls++;return $response;
};
try {
    $one = newsdesk_feed($top,$specs[$top],$dir,$fetch,$now);
    vestland_check($one['status'] === 'updated' && count($one['items']) === 1, 'First response cached');
    newsdesk_feed($top,$specs[$top],$dir,$fetch,$now+299);
    vestland_check($calls === 1, 'No upstream poll on every page load');
    $response = null;$failed = newsdesk_feed($top,$specs[$top],$dir,$fetch,$now+301);
    vestland_check($failed['status'] === 'stale' && $failed['items'] === $one['items'], 'Outage retains last good data with stale status');
    newsdesk_feed($top,$specs[$top],$dir,$fetch,$now+302);
    vestland_check($calls === 2, 'Failed source still respects retry interval');
    $expired = newsdesk_feed($top,$specs[$top],$dir,$fetch,$now+21601);
    vestland_check($expired['status'] === 'unavailable' && $expired['items'] === [], 'Expired cache is not current news');
    $response = '<rss><channel><title>Tom testfeed</title></channel></rss>';
    $empty = newsdesk_feed($top,$specs[$top],$dir,$fetch,$now+22000);
    vestland_check($empty['status'] === 'updated' && $empty['items'] === [], 'Valid empty feed is distinct from fetch failure');
    $path = $dir.'/board.json';$user = ['name'=>'Testredaktør'];
    $legacy = $a[0];$legacy['id'] = hash('sha256','nrk:'.$url);$legacy['url'] = $url.'?utm_source=legacy';
    studio_board_add_source($legacy,$user,$path);$board = studio_board_read($path);
    $id = $board['items'][0]['id'];
    studio_board_update($id,1,'save',['title'=>'Min redigerte tittel','script'=>'Menneskelig manus.','notes'=>'Behold notatet.','verified'=>'1'],$user,$path);
    studio_board_update($id,2,'ready',[],$user,$path);$before = studio_board_read($path)['items'][0];
    studio_board_add_source($b[0],$user,$path);studio_board_add_source($c[0],$user,$path);
    $after = studio_board_read($path);
    vestland_check(count($after['items']) === 1 && $after['items'][0] === $before, 'Legacy article is not duplicated; human script, approval and history untouched');
    vestland_check(studio_board_has_source(studio_board_active($after),$b[0]), 'UI recognizes already selected legacy item');
    studio_board_add_source($d[0],$user,$path);$after = studio_board_read($path);
    vestland_check(count($after['items']) === 2 && $after['items'][1]['status'] === 'draft' && !$after['items'][1]['verified'], 'New RSS card remains unverified draft');
    vestland_check($after['items'][1]['sourceFeeds'] === [$latest], 'Feed provenance carried into source draft');
    echo "OK: $checks Vestland RSS checks; synthetic fixtures only, no upstream requests or AI calls\n";
} finally {
    foreach (glob($dir.'/*') as $file)unlink($file);rmdir($dir);
}
