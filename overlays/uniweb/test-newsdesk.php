<?php
declare(strict_types=1);
require __DIR__ . '/app/integrations/NewsDesk.php';
function check(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
$sources = newsdesk_sources();
$rss = '<rss><channel><item><title>Vegarbeid på Bømlo</title><link>https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx</link><description>&lt;p&gt;Kort omtale.&lt;/p&gt;</description><pubDate>Mon, 28 Sep 2026 09:00:00 GMT</pubDate></item></channel></rss>';
$cards = newsdesk_parse_rss($rss, 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z');
check(count($cards) === 1 && $cards[0]['summary'] === 'Kort omtale.', 'municipal card');
check(count(newsdesk_parse_rss(str_replace('www.bomlo.kommune.no', 'evil.example', $rss), 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'foreign host rejected');
check(count(newsdesk_parse_rss(str_replace('https://', 'http://', $rss), 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'non-HTTPS link rejected');
check(count(newsdesk_parse_rss('<!DOCTYPE rss>' . $rss, 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'DTD rejected');
$nrk = str_replace(['www.bomlo.kommune.no/aktuelt-og-kunngjeringar/', 'Vegarbeid på Bømlo'], ['www.nrk.no/nyheter/', 'Siste nytt'], $rss);
check(count(newsdesk_parse_rss($nrk, 'nrk', $sources['nrk'], '2026-09-28T10:00:00Z')) === 1, 'NRK card');
echo "NewsDesk parser OK\n";
