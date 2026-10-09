<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/case-workflow.php';
$rows=json_decode(file_get_contents(dirname(__DIR__).'/tests/fixtures/journalist/published-comparison.json'),true,64,JSON_THROW_ON_ERROR);
$report=['stylebook'=>RR_STYLEBOOK_VERSION,'mode'=>'editorial_fixture_comparison',
    'limitations'=>['Candidates are editorial examples, not runtime model responses.','Public source excerpts may differ from the historical publication source.','LIX/counts do not verify truth or ethical acceptability.'],'articles'=>[]];
foreach($rows as $row){
    if(!hash_equals($row['sourceExcerptSha256'],hash('sha256',$row['sourceExcerpt'])))throw new RuntimeException('Changed source fixture.');
    $before=studio_web_validate($row['before']);$candidate=studio_web_validate($row['candidate']);
    $report['articles'][]=['postId'=>$row['postId'],'url'=>$row['publishedUrl'],'candidateOrigin'=>$row['candidateOrigin'],
        'before'=>rr_text_metrics(studio_web_text($before)),'after'=>rr_text_metrics(studio_web_text($candidate)),
        'assessment'=>$row['assessment'],'candidate'=>$candidate,'automaticApproval'=>false];
}
echo json_encode($report,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),"\n";
