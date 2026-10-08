<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/newsroom.php';
function channel_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$dir=sys_get_temp_dir().'/newsroom-channels-'.bin2hex(random_bytes(5));mkdir($dir,0700);
$user=['name'=>'Fixture','role'=>'admin'];$config=['openai_api_key'=>'fixture','openai_model'=>'fixture'];
$text='Kommunen arrangerer et åpent møte på biblioteket 8. oktober. Møtet handler om trafikksikkerhet. Alle innbyggere kan delta i møtet og stille spørsmål.';
$source=['id'=>'fixture','title'=>'Et møte','sourceName'=>'NRK','url'=>'https://www.nrk.no/vestland/test-1.12345678'];
try{
    foreach(['radio'=>2,'web'=>2,'both'=>4] as $channel=>$expected){
        $path=$dir.'/'.$channel.'.json';$calls=0;$reads=0;
        $item=studio_newsroom_intake($source,$channel,$user,$path);
        channel_check(studio_board_channel($item)===$channel,'intake stores selected channel atomically');
        $before=file_get_contents($path);
        $again=studio_newsroom_intake($source,'both',$user,$path);
        channel_check($again['id']===$item['id']&&file_get_contents($path)===$before,'reopening preserves channel and existing story');
        foreach(['invalid','observer'] as $bad){
            try{studio_newsroom_intake($source,$bad==='invalid'?'other':$channel,$bad==='observer'?['role'=>'observer']:$user,$path);throw new RuntimeException('Expected rejection');}catch(InvalidArgumentException $e){}
            channel_check(file_get_contents($path)===$before,'invalid intake cannot mutate story: '.$bad);
        }
        $fetch=static function($url)use(&$reads,$text){$reads++;return '<html><head><link rel="canonical" href="'.$url.'"></head><body><article><p>'.$text.'</p></article></body></html>';};
        $request=static function($config,$payload)use(&$calls,$text){
            $calls++;$input=json_decode($payload['input'],true);
            if(isset($input['segments'])){
                $segments=[];foreach($input['segments'] as $i=>$segment)$segments[]=['index'=>$i,'verdict'=>'supported','evidence'=>[$text],'reason'=>'Belegg.'];
                return json_encode(['segments'=>$segments,'issues'=>[]]);
            }
            if(str_starts_with($payload['instructions'],'Skriv ett nyhetsmanus'))return "Dette melder NRK.\nKommunen arrangerer et åpent møte på biblioteket 8. oktober.";
            return json_encode(['title'=>'Åpent møte','intro'=>'Kommunen inviterer.','body'=>"Møtet handler om trafikksikkerhet.\nAlle innbyggere kan delta."]);
        };
        studio_newsroom_prepare($item['id'],1,$user,$config,'',$path,$request,$fetch);
        $prepared=studio_case_get($item['id'],$path);
        channel_check($calls===$expected&&$reads===1,'selected channel uses existing robot and exactly one original fetch: '.$channel);
        channel_check(empty($prepared['web']['delivery'])&&$prepared['status']==='draft'&&!$prepared['verified'],'preparation never approves or publishes: '.$channel);
        channel_check(($channel==='web')===empty($prepared['script']),'radio draft follows selected channel: '.$channel);
        channel_check(($channel==='radio')===empty($prepared['web']['body']),'web draft follows selected channel: '.$channel);
        if($channel==='both')channel_check($prepared['sourceCheck']['source']===$prepared['web']['check']['source'],'both drafts share the original snapshot');
        $flow=studio_newsroom_flow($prepared);
        channel_check($flow['radioApproved']===false,'generated text has no human approval');
        channel_check(str_contains($flow['next']['url'],$channel==='web'?'#web-material':'#radio-material'),'next action follows selected channel');
        if($channel==='radio'){
            $card=studio_newsroom_card($prepared);
            channel_check(!$card['canApprove']&&$flow['web']==='Ikke valgt'&&!str_contains(implode(' ',$card['reasons']),'Nettsaken'),'radio has no web blockers or publish action');
            $approved=$prepared;$approved['status']='ready';$approved['verified']=true;$approved['approvedBy']='Editor';
            channel_check(studio_newsroom_flow($approved)['radioApproved'],'checked and manually approved radio leads to rundown');
            $edited=$approved;$edited['script'].=' Ny påstand.';
            channel_check(!studio_newsroom_flow($edited)['radioApproved'],'changed text never displays approved');
            $expired=$approved;$expired['sourceCheck']['checkedAt']=gmdate('c',time()-3601);
            channel_check(!studio_newsroom_flow($expired)['radioApproved'],'expired check never displays approved');
            unset($approved['approvedBy']);channel_check(!studio_newsroom_flow($approved)['radioApproved'],'missing approver never displays approved');

            $script=$prepared['script'];$before=$calls;
            studio_newsroom_prepare($item['id'],$prepared['revision'],$user,$config,'',$path,$request,$fetch);
            channel_check(studio_case_get($item['id'],$path)['script']===$script&&$calls===$before+1,'existing radio script is checked without rewriting');
        }
    }
}finally{foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
