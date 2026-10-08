<?php
declare(strict_types=1);
require_once __DIR__.'/programs.php';
const RR_AUDIO_VERSION='1.0.0';
/** Station disclosure is applied after pronunciation; source text cannot override it. */
function rr_audio_disclosure():array {
    return ['version'=>'1.0.0','text'=>'Denne stemmen er KI-generert.','placement'=>'start'];
}

function rr_audio_profiles():array {
    return [
        'bulletin'=>['name'=>'Samlet nyhetssending','min'=>20,'max'=>180,'words'=>450,'instruction'=>'Samles fra kontrollerte enkeltsaker i radiolisten.'],
        'news-short'=>['name'=>'Nyhetsstikk · 15–20 sek','min'=>15,'max'=>20,'words'=>45,'instruction'=>'Ett hovedpoeng og nødvendig kildeattribusjon.'],
        'news-standard'=>['name'=>'Nyhetsstikk · 30–45 sek','min'=>30,'max'=>45,'words'=>100,'instruction'=>'Hovedpoeng og én relevant, dokumentert bakgrunnsopplysning.'],
        'news-extended'=>['name'=>'Utvidet nyhetsstikk · 60–90 sek','min'=>60,'max'=>90,'words'=>200,'instruction'=>'Forklar dokumentert sammenheng og forløp uten å gjenta poenger.'],
        'host'=>['name'=>'Programlederstikk','min'=>10,'max'=>45,'words'=>100,'instruction'=>'Varm, muntlig introduksjon tilpasset programprofilen, uten oppdiktet opplevelse eller sendestatus.'],
        'sport'=>['name'=>'Sportsoppdatering','min'=>15,'max'=>45,'words'=>100,'instruction'=>'Prioriter dokumentert resultat eller hendelse. Ikke dikt kampforløp, tabell, stemning eller spilleropplysninger.'],
        'event'=>['name'=>'Lokalt arrangement','min'=>15,'max'=>45,'words'=>100,'instruction'=>'Formidle dokumentert hva, hvor og når. Skill invitasjon og plan fra gjennomført arrangement.'],
        'teaser'=>['name'=>'Kort teaser','min'=>5,'max'=>15,'words'=>30,'instruction'=>'Kort, konkret interessevekker uten klikkagn eller løfte om noe som ikke er planlagt.'],
        'transition'=>['name'=>'Overgang mellom programposter','min'=>5,'max'=>15,'words'=>30,'instruction'=>'En naturlig overgang basert på oppgitte fakta. Ikke finn på neste låt, gjest eller programpost.'],
    ];
}
function rr_audio_profile(string $id):array {
    $p=rr_audio_profiles()[$id]??null;
    if(!$p)throw new InvalidArgumentException('Velg en gyldig manusprofil.');
    return ['id'=>$id,'version'=>RR_AUDIO_VERSION]+$p;
}
function rr_audio_profile_instructions(string $id):string {
    $p=rr_audio_profile($id);
    return ' RR Audio: Skriv et selvstendig muntlig manus direkte fra den samme originalkilden, ikke en forkortelse av nettartikkelen. '
        .$p['instruction'].' Ønsket varighet '.$p['min'].'–'.$p['max'].' sekunder ved naturlig tale. '
        .'Varigheten er et mål, aldri tillatelse til å fylle på fakta. Hvis kilden er for knapp, skriv kortere og la redaktøren velge en kortere profil. '
        .'Den ferdige lyden starter automatisk med «'.rr_audio_disclosure()['text'].'». Sett av omtrent to sekunder innenfor totalvarigheten til dette; ikke skriv merkingen inn i nyhetsmanuset. '
        .'Manuset skal være rent talespråk, uten lydtagger, SSML, sceneanvisninger eller overskrift.';
}
