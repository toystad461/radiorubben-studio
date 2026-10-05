<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/app/integrations/NewsDesk.php';
function newsdesk_check(bool $condition, string $label): void { if (!$condition) throw new RuntimeException($label); }
$sources = newsdesk_sources();
$rss = '<rss><channel><item><title>Vegarbeid på Bømlo</title><link>https://www.bomlo.kommune.no/aktuelt-og-kunngjeringar/test.123.aspx</link><description>&lt;p&gt;Kort omtale.&lt;/p&gt;</description><pubDate>Mon, 28 Sep 2026 09:00:00 GMT</pubDate></item></channel></rss>';
$cards = newsdesk_parse_rss($rss, 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z');
newsdesk_check(count($cards) === 1 && $cards[0]['summary'] === 'Kort omtale.', 'municipal card');
newsdesk_check(count(newsdesk_parse_rss(str_replace('www.bomlo.kommune.no', 'evil.example', $rss), 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'foreign host rejected');
newsdesk_check(count(newsdesk_parse_rss(str_replace('https://', 'http://', $rss), 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'non-HTTPS link rejected');
newsdesk_check(count(newsdesk_parse_rss('<!DOCTYPE rss>' . $rss, 'bomlo', $sources['bomlo'], '2026-09-28T10:00:00Z')) === 0, 'DTD rejected');
$nrk = str_replace(['www.bomlo.kommune.no/aktuelt-og-kunngjeringar/', 'Vegarbeid på Bømlo'], ['www.nrk.no/nyheter/', 'Siste nytt'], $rss);
newsdesk_check(count(newsdesk_parse_rss($nrk, 'nrk_vestland_siste', $sources['nrk_vestland_siste'], '2026-09-28T10:00:00Z')) === 1, 'NRK card');
$traffic = ['type'=>'FeatureCollection', 'totalFeatures'=>2, 'features'=>[]];
$road = ['@featureType'=>'geoJsonSituationSimple', 'isMainRecord'=>true,
    'confidentiality'=>'noRestriction', 'situationId'=>'NPRA_HBT_28-09-2026.1',
    'locationDescription'=>'Fv. 541 Sakseid i Bømlo', 'description'=>'Vegarbeid.|Lysregulering.',
    'lastUpdateTime'=>gmdate('c', time()-60), 'endTime'=>gmdate('c', time()+3600)];
$traffic['features'] = [['properties'=>$road], ['properties'=>$road + ['isMainRecord'=>false]]];
$traffic['features'][1]['properties']['isMainRecord'] = false;
$parsed = newsdesk_traffic_parse(json_encode($traffic), gmdate('c'));
newsdesk_check(count($parsed ?? []) === 1 && $parsed[0]['source'] === 'vegvesen'
    && $parsed[0]['summary'] === 'Vegarbeid. Lysregulering.', 'traffic main record');
$traffic['totalFeatures'] = 3;
newsdesk_check(newsdesk_traffic_parse(json_encode($traffic), gmdate('c')) === null, 'truncated traffic rejected');
$traffic['totalFeatures'] = 2;
$traffic['features'][0]['properties']['endTime'] = gmdate('c', time()-3600);
newsdesk_check(newsdesk_traffic_parse(json_encode($traffic), gmdate('c')) === [], 'expired traffic omitted');
echo "NewsDesk parser OK\n";
