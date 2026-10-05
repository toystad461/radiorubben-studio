<?php
declare(strict_types=1);
require_once __DIR__ . '/board.php';
function studio_music_text(mixed $value, int $max = 180): string {
    if (!is_string($value)) throw new InvalidArgumentException('Ugyldig tekst.');
    $value=trim($value);
    if ($value==='' || studio_board_length($value)>$max || preg_match('/[\x00-\x1f\x7f]/',$value)) throw new InvalidArgumentException('Kontroller artist og tittel.');
    return $value;
}
function studio_music_plan_change(string $action, array $input, array $user, ?string $path=null): void {
    if (!in_array($user['role']??'', ['admin','producer','presenter'],true)) throw new InvalidArgumentException('Rollen kan bare lese musikkplanen.');
    studio_board_change(static function(array &$board) use($action,$input,$user): void {
        $board['musicPlan']??=[];
        if ($action==='add') {
            if (count(array_filter($board['musicPlan'],static fn($t)=>empty($t['archived'])))>=100) throw new InvalidArgumentException('Maksimalt 100 aktive musikkpunkter.');
            $artist=studio_music_text($input['artist']??null);$title=studio_music_text($input['title']??null);
            $slot=(string)($input['slot']??'');
            if (!preg_match('/^([01][0-9]|2[0-3]):00$/D',$slot)) throw new InvalidArgumentException('Velg sendetime.');
            $board['musicPlan'][]=['id'=>bin2hex(random_bytes(8)),'program'=>'god-morgen-vestland','artist'=>$artist,'title'=>$title,'slot'=>$slot,'revision'=>1,'createdAt'=>gmdate('c'),'createdBy'=>$user['name']??'Medarbeider','archived'=>false,'reserve'=>null,'history'=>[]];return;
        }
        foreach($board['musicPlan'] as &$track) {
            if($track['id']!==($input['id']??'') || !empty($track['archived']))continue;
            if($track['revision']!==(int)($input['revision']??0))throw new InvalidArgumentException('Musikkpunktet ble endret. Last siden på nytt.');
            $before=$track;unset($before['history']);
            if($action==='reserve') {
                $artist=studio_music_text($input['reserveArtist']??null);$title=studio_music_text($input['reserveTitle']??null);
                if($artist===$track['artist'] && $title===$track['title'])throw new InvalidArgumentException('Velg en annen låt som reserve.');
                $track['reserve']=['artist'=>$artist,'title'=>$title,'selectedAt'=>gmdate('c'),'selectedBy'=>$user['name']??'Medarbeider'];
            } elseif($action==='clear-reserve') $track['reserve']=null;
            elseif($action==='archive')$track['archived']=true;
            else throw new InvalidArgumentException('Ukjent handling.');
            $track['history'][]=['action'=>$action,'at'=>gmdate('c'),'actor'=>$user['name']??'Medarbeider','before'=>$before];$track['revision']++;return;
        }
        throw new InvalidArgumentException('Fant ikke musikkpunktet.');
    },$path);
}
