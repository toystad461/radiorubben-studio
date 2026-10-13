<?php
declare(strict_types=1);
// Tags from original-file evidence only. Editorial and AI assessments remain separate.
function music_library_file_tags(array $metadata):array {
    $tags=[];$genre=$metadata['fields']['genre']??null;
    if(is_string($genre))foreach(preg_split('/[;,\/]/u',$genre)?:[]as$value){$value=trim($value);if($value!==''&&mb_strlen($value)<=80&&count($tags)<8)$tags[]=['label'=>$value,'field'=>'genre'];}
    $year=$metadata['fields']['year']??null;
    if(is_string($year)&&preg_match('/^(19\d{2}|20\d{2})(?:-\d{2}-\d{2})?$/D',$year)&&((int)substr($year,0,4))<=(int)gmdate('Y'))$tags[]=['label'=>((int)substr($year,0,4)-((int)substr($year,0,4)%10)).'-tallet','field'=>'year'];
    return ['schemaVersion'=>1,'source'=>'file','sourceSha256'=>$metadata['sourceSha256'],'generatedAt'=>gmdate('c'),'ruleVersion'=>'file-tags-1','tags'=>$tags,'reviewRequired'=>empty($metadata['fields']['artist'])||empty($metadata['fields']['title']),'pending'=>['onlineIdentification','energyAssessment','scheduleAssessment']];
}
function music_library_tag_labels(array $track):array {
    $labels=[];$manual=is_array($track['tags']??null)?$track['tags']:[];
    foreach($manual as$label)if(is_string($label)&&trim($label)!==''&&mb_strlen($label)<=80)$labels[]=trim($label);
    $auto=$track['autoTags']??null;
    if(is_array($auto)&&($auto['source']??null)==='file'&&($auto['sourceSha256']??null)===($track['sha256']??null)&&is_array($auto['tags']??null))foreach($auto['tags']as$tag)if(is_array($tag)&&is_string($tag['label']??null)&&mb_strlen($tag['label'])<=80)$labels[]=$tag['label'];
    return array_slice(array_values(array_unique($labels)),0,28);
}
