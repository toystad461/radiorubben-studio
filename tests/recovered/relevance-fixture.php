<?php
/** Synthetic model response for tests of downstream workflows, never production code. */
function relevance_fixture_response(array $changes=[]): string {
    return json_encode(array_replace([
        'locality'=>'bomlo','localEvidence'=>[0],'impactEvidence'=>[0],
        'impact'=>'Bømlo-innbyggere kan delta i det lokale møtet.','value'=>'Lokal medvirkning og fellesskap.',
        'temporalKind'=>'event','eventDate'=>null,'eventEvidence'=>[],'current'=>true,'timelinessReason'=>'Kilden beskriver en aktuell lokal sak.',
        'sourceSufficient'=>true,'sourceReason'=>'Originalen beskriver aktivitet, målgruppe og formål.',
        'radio'=>true,'radioReason'=>'Kort informasjon om lokalt møte.','web'=>true,'webReason'=>'Originalen har konkrete opplysninger for en kort lokal nettsak.',
        'relation'=>'new','relatedId'=>null,'newEvidence'=>[],'relationReason'=>'Ingen tidligere sak om hendelsen.',
        'facts'=>['Lokalt møte i Bømlo.']
    ],$changes),JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
}
function relevance_fixture(array $item,array $source): array {
    $item['relevance']=['policy'=>STUDIO_RELEVANCE_POLICY,'identity'=>studio_relevance_identity($item,$source),
        'assessedAt'=>gmdate('c'),'source'=>$source,'sourceSufficient'=>true,'recommendation'=>'both',
        'reason'=>'Syntetisk utvalgsfixture for isolert nedstrømstest.','relation'=>'new'];
    return $item;
}
