<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/newsroom.php';
require __DIR__.'/relevance-fixture.php';
function feedback_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function feedback_reject(callable $fn,string $label):void{try{$fn();}catch(InvalidArgumentException $e){feedback_check(true,$label);return;}throw new RuntimeException($label);}
$dir=sys_get_temp_dir().'/feedback-'.bin2hex(random_bytes(5));mkdir($dir,0700);$path=$dir.'/board.json';
$admin=['name'=>'Thomas','role'=>'admin'];$producer=['name'=>'Editor','role'=>'producer'];
$config=['openai_api_key'=>'fixture','openai_model'=>'fixture'];
$text='Bømlo kommune arrangerer et åpent møte på biblioteket 8. oktober. Møtet handler om trafikksikkerhet. Alle innbyggere kan delta og stille spørsmål.';
$source=['id'=>'fixture','title'=>'Møte','sourceName'=>'NRK','publishedAt'=>gmdate('c'),'url'=>'https://www.nrk.no/vestland/mote-1.12345678','publishedAt'=>gmdate('c')];
try{
    studio_board_add_source($source,$admin,$path);$item=studio_board_read($path)['items'][0];
    studio_board_change(static function(&$b)use($text,$source){$b['items'][0]['web']=['title'=>'Møte','intro'=>'Kommunen inviterer.','body'=>'Alle kan delta.','check'=>['status'=>'needs_review','source'=>['url'=>$source['url'],'text'=>$text,'sha256'=>hash('sha256',$text)]]];},$path);
    $item=studio_case_get($item['id'],$path);$before=$item;
    $input=['item'=>$item['id'],'itemRevision'=>$item['revision'],'text'=>'Bevar kildens forbehold. Ikke legg til konsekvenser som ikke står i kilden.'];
    feedback_reject(fn()=>studio_memory_change('feedback',$input,['role'=>'observer'],$path),'observer cannot comment');
    feedback_reject(fn()=>studio_memory_change('feedback',array_replace($input,['itemRevision'=>0]),$admin,$path),'stale feedback cannot attach to a newer draft');
    feedback_reject(fn()=>studio_memory_change('feedback',$input+['apply'=>'1','styleOnly'=>'1'],$producer,$path),'producer cannot activate memory');
    feedback_reject(fn()=>studio_memory_change('feedback',$input+['apply'=>'1'],$admin,$path),'activation requires explicit limited learning scope');
    studio_memory_change('feedback',$input,$producer,$path);
    $board=studio_board_read($path);$pending=$board['editorialRules'][0];
    feedback_check($board['items'][0]===$before,'comments do not rewrite drafts or alter approval/revision');
    feedback_check($pending['evidence']['revision']===$item['revision']&&$pending['evidence']['source']['text']===$text,'feedback binds immutable version and source snapshot');
    feedback_check(studio_memory_context($board,$item['program'])['rules']===[],'stored pending comments are not used');
    feedback_reject(fn()=>studio_memory_change('feedback',$input,$admin,$path),'duplicate submission does not create repeated learning');
    $activeText='Skriv korte setninger og bevar forskjellen mellom meldt og bekreftet.';
    studio_memory_change('feedback',array_replace($input,['text'=>$activeText,'apply'=>'1','styleOnly'=>'1']),$admin,$path);
    $active=studio_board_read($path)['editorialRules'][1];
    $seen=[];$reads=0;
    $fetch=static function($url)use($text,&$reads){$reads++;return '<html><head><link rel="canonical" href="'.$url.'"></head><body><article><p>'.$text.'</p></article></body></html>';};
    $request=static function($c,$p)use(&$seen,$activeText,$text){
        if(($p['text']['format']['name']??'')==='rss_relevance')return relevance_fixture_response();
        $input=json_decode($p['input'],true);
        if(isset($input['segments'])){
            feedback_check(!isset($input['editorial']),'learning cannot influence factual evidence verdicts');
            return json_encode(['segments'=>array_map(static fn($s)=>['index'=>$s['index'],'verdict'=>'supported','evidence_indexes'=>[0],'reason'=>'Belegg i originalen.'],$input['segments']),'issues'=>[]]);
        }
        feedback_check(array_column($input['editorial']['rules'],'text')===[$activeText],'only active advice reaches generation');
        feedback_check(!str_contains(json_encode($input['editorial']),'Alle kan delta.'),'old draft and example facts do not enter memory context');
        feedback_check($input['source']['text']===$text,'current original remains the fact source');
        $radio=str_starts_with($p['instructions'],'Skriv ett nyhetsmanus');$seen[]=$radio?'radio':'web';
        return $radio?"Dette melder NRK.\nKommunen arrangerer et åpent møte.":json_encode(['title'=>'Møte','intro'=>'Kommunen arrangerer et møte.','body'=>'Alle innbyggere kan delta.']);
    };
    studio_newsroom_prepare($item['id'],$item['revision'],$admin,$config,'',$path,$request,$fetch);
    $prepared=studio_case_get($item['id'],$path);
    feedback_check($seen===['web','radio']&&$reads===1,'both newsroom generators use approved comments and one shared source');
    feedback_check($prepared['newsroom']['editorial']['rules'][0]['id']===$active['id'],'job records exact advice version used');
    feedback_check(!$prepared['verified']&&empty($prepared['web']['approvedHash'])&&empty($prepared['web']['delivery']),'learning never approves or publishes');
    $board=studio_board_read($path);
    feedback_check($board['editorialRules'][0]['evidence']['before']===studio_memory_feedback_text($before),'original commented draft survives later regeneration');
    studio_memory_change('disable',['id'=>$active['id'],'revision'=>1],$admin,$path);
    feedback_check(studio_memory_context(studio_board_read($path),$item['program'])['rules']===[],'disabled advice stops affecting new drafts');
    feedback_check(studio_memory_context(studio_board_read($path),'another-program')===[],'feedback stays isolated to the program');
}finally{foreach(glob($dir.'/*')as$file)unlink($file);rmdir($dir);}
