<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/case-workflow.php';
$rows=json_decode(file_get_contents(dirname(__DIR__).'/fixtures/journalist/published-comparison.json'),true,64,JSON_THROW_ON_ERROR);
foreach($rows as $row){
    if(hash('sha256',$row['sourceExcerpt'])!==$row['sourceExcerptSha256'])throw new RuntimeException('Fixture source changed.');
    $before=studio_web_validate($row['before']);$after=studio_web_validate($row['candidate']);
    if(rr_text_metrics(studio_web_text($after))['words']>=rr_text_metrics(studio_web_text($before))['words'])throw new RuntimeException('Candidate must remove repetition.');
    preg_match_all('/\d+/u',studio_web_text($after),$numbers);
    foreach($numbers[0] as $number)if(!str_contains($row['sourceExcerpt'],$number))throw new RuntimeException('Unsupported numeric token.');
    if($row['postId']===1283&&(!str_contains($after['title'],'mulig')||!str_contains($after['body'],'skal politiet ha')))throw new RuntimeException('Uncertainty must survive revision.');
}
echo "Public article fixtures retain numeric tokens and uncertainty; semantic and ethical judgment still require an editor.\n";
