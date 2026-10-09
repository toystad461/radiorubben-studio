<?php
declare(strict_types=1);

/** Public business details checked 2026-10-07. Import is an explicit, idempotent admin action. */
function studio_crm_candidates(): array
{
    return [
        ['company'=>'Kulleseidkanalen Gjestehamn', 'contact'=>'', 'phone'=>'53 42 11 00', 'email'=>'post@kulleseidkanalen.no', 'website'=>'https://kulleseidkanalen.no/', 'priority'=>'1',
            'opportunity'=>'Forslag: en avgrenset kampanje for ett arrangement, med mål om flere bestillinger. Interesse og budsjett er uavklart.',
            'nextStep'=>'Ring og avklar markedsansvarlig og hvilket kommende arrangement de ønsker å løfte frem.'],
        ['company'=>'Bømlo Storsenter', 'contact'=>'', 'phone'=>'95 22 90 63', 'email'=>'anita@bomlostorsenter.no', 'website'=>'https://bomlostorsenter.no/', 'priority'=>'1',
            'opportunity'=>'Forslag: lokal handelsdag med annonsering og eventuelt speaker. Interesse og budsjett er uavklart.',
            'nextStep'=>'Be om en kort prat om senterets aktivitetsplan og ett felles tiltak.'],
        ['company'=>'MEKK Bømlo', 'contact'=>'', 'phone'=>'46 52 42 17', 'email'=>'bomlo@mekk.no', 'website'=>'https://mekk.no/bomlo', 'priority'=>'2',
            'opportunity'=>'Forslag: sesongkampanje for én produktgruppe innen bil, båt, hjem eller fritid. Interesse og budsjett er uavklart.',
            'nextStep'=>'Snakk med butikksjefen og avklar hvem som bestemmer over lokal annonsering.'],
        ['company'=>'Bømlo Hotell', 'contact'=>'', 'phone'=>'92 92 67 00', 'email'=>'post@bomlohotell.no', 'website'=>'https://bomlohotell.no/', 'priority'=>'2',
            'opportunity'=>'Forslag: profilering av møter og selskapslokale overfor lokale bedrifter og familier. Interesse og budsjett er uavklart.',
            'nextStep'=>'Avklar hvilket tilbud de ønsker flere bestillinger på og hvem som har markedsansvaret.'],
        ['company'=>'Finnås Kraftlag', 'contact'=>'', 'phone'=>'53 42 89 00', 'email'=>'firmapost@finnas-kraftlag.no', 'website'=>'https://www.finnas-kraftlag.no/', 'priority'=>'3',
            'opportunity'=>'Forslag: kommersielt samarbeid rundt lokal nettradio og bredbånd. Interesse og budsjett er uavklart.',
            'nextStep'=>'Be om markedsansvarlig og avklar om Radio Rubben kan inngå i planene for 2027.'],
    ];
}
