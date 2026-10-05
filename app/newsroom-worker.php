<?php
declare(strict_types=1);
// Private CLI entry point used by the existing scheduler. No HTTP execution or publication.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/config.php';
require_once __DIR__.'/newsroom.php';
require_once __DIR__.'/integrations/NewsDesk.php';
require_once __DIR__.'/producer.php';
try {
    if(($argv[1]??'')==='tick')$result=studio_newsroom_tick(newsdesk_all(dirname(__DIR__).'/config'),load_config());
    elseif(($argv[1]??'')==='queue'){
        $result=['version'=>STUDIO_NEWSROOM_VERSION,'items'=>[]];
        foreach(studio_board_active(studio_board_read()) as $item)if(studio_web_is_news($item)){
            $card=studio_newsroom_card($item);
            if($card['status']==='ready')$result['items'][]=['key'=>'studio:'.$item['id'].':'.studio_web_approval_hash($item)];
        }
    }else throw new InvalidArgumentException('Unknown mode');
    echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
}catch(Throwable $e){fwrite(STDERR,"Newsroom preparation unavailable; inspect the authenticated desk.\n");exit(1);}
