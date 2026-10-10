<?php
declare(strict_types=1);
// Read-only preview: never calls board mutation, writer, publisher or audio services.
if(PHP_SAPI!=='cli') {http_response_code(404);exit;}
require dirname(__DIR__).'/app/news-script.php';
require dirname(__DIR__).'/app/integrations/NewsDesk.php';
require dirname(__DIR__).'/app/config.php';
require dirname(__DIR__).'/app/producer.php';
$config=load_config();$result=['at'=>gmdate('c'),'mode'=>'read-only','modelConfigured'=>!empty($config['openai_api_key'])&&!empty($config['openai_model']),'items'=>[],'feeds'=>[]];
$seen=[];
foreach(newsdesk_sources() as $id=>$spec){
    $xml=newsdesk_fetch_rss($spec['feed']);
    $feed=$xml===null?null:newsdesk_decode_rss($xml,$id,$spec,gmdate('c'));
    $result['feeds'][$id]=['status'=>$feed===null?'unavailable':'read','count'=>count($feed??[])];
    foreach(array_slice($feed??[],0,4) as $source){
        $identity=studio_source_identity($source['url']);if(isset($seen[$identity]))continue;$seen[$identity]=true;
        $item=['originId'=>$source['id'],'title'=>$source['title'],'sourceUrl'=>$source['url'],'sourceAt'=>$source['publishedAt']];
        $row=['title'=>$source['title'],'url'=>$source['url'],'publishedAt'=>$source['publishedAt'],'feed'=>$id];
        try{
            $original=studio_news_source($item);
            $row['sourceSha256']=$original['sha256'];
            if(in_array('--source-text',$argv,true))$row['sourceText']=$original['text'];
            if($result['modelConfigured']){
                $assessment=studio_relevance_assess($item,$original,[],$config);
                unset($assessment['source']);$row['assessment']=$assessment;
            }else $row['assessment']=studio_relevance_empty($item,'Modellkonfigurasjon mangler lokalt. Originalen ble lest, men automatisk vurdering er ikke kjørt.');
        }catch(Throwable $e){$row['assessment']=studio_relevance_empty($item,$e instanceof InvalidArgumentException?$e->getMessage():'Originalen kunne ikke leses.');}
        $result['items'][]=$row;
    }
}
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR)."\n";
