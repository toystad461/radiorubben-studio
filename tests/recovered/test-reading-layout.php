<?php
declare(strict_types=1);
require dirname(__DIR__,2).'/app/case-workflow.php';
function reading_check(bool $ok,string $label):void{if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$body="Første setning.\nAndre setning.\n\nNeste avsnitt.\nSiste setning.";
$before=hash('sha256',$body);
reading_check(studio_news_reading_paragraphs($body)===['Første setning. Andre setning.','Neste avsnitt. Siste setning.'],'single line breaks merge, blank lines keep paragraphs');
reading_check(studio_news_reading_paragraphs(str_replace("\n","\r\n",$body))===studio_news_reading_paragraphs($body),'CRLF layout matches LF');
reading_check(studio_news_reading_paragraphs('')===[],'empty reading text');
$item=['sourceName'=>'NRK Vestland','sourceUrl'=>'https://www.nrk.no/vestland/test-1.12345678','web'=>['intro'=>'Ingress.','body'=>$body]];
$html=studio_web_html($item);
reading_check(strpos($html,'Basert på opplysninger fra NRK')<strpos($html,'Ingress.'),'NRK credit appears before article text');
reading_check(str_contains($html,'href="'.$item['sourceUrl'].'"')&&str_contains($html,'Les hele saken hos NRK'),'original NRK article is linked by publication markup');
reading_check(!str_contains($html,'<br')&&str_contains($html,'<p>Første setning. Andre setning.</p>'),'web delivery reads as natural paragraphs');
$item['web']['body']='<script>alert(1)</script>';
reading_check(!str_contains(studio_web_html($item),'<script>'),'proposal text remains escaped');
reading_check(hash('sha256',$body)===$before,'reading renderer never changes stored text');
