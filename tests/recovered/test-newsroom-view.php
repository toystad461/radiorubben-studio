<?php
require dirname(__DIR__,2).'/app/newsroom-view.php';
function verify($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
$cards=['wp:1'=>['status'=>'published'],'wp:2'=>['status'=>'ready'],'wp:3'=>['status'=>'attention']];
$s=studio_newsroom_selection($cards,'','');verify($s['selected']==='wp:2'&&$s['filter']==='ready','Ready story opens directly');
$s=studio_newsroom_selection($cards,'wp:3','ready');verify($s['selected']==='wp:3'&&$s['filter']==='attention','Revised attention story remains selected');
$s=studio_newsroom_selection($cards,'wp:1','all',true);verify($s['selected']==='wp:2'&&$s['filter']==='ready','Confirmed decision advances to remaining ready story');
$s=studio_newsroom_selection($cards,'wp:2','all',true);verify($s['selected']===''&&$s['filter']==='ready','Last decision shows an empty reader');
$s=studio_newsroom_selection($cards,'missing','attention');verify($s['selected']==='wp:3','Missing selection recovers within the chosen filter');
verify(studio_newsroom_image_url('https://www.radiorubben.no/wp-content/uploads/2026/10/news.jpg'),'Published image allowed');
foreach(['http://www.radiorubben.no/wp-content/uploads/test.jpg','https://evil.test/wp-content/uploads/test.jpg','https://www.radiorubben.no.evil.test/wp-content/uploads/test.jpg','https://user@www.radiorubben.no/wp-content/uploads/test.jpg','https://www.radiorubben.no:443/wp-content/uploads/test.jpg','https://www.radiorubben.no/wp-content/uploads/../test.jpg','https://www.radiorubben.no/wp-content/uploads/%2e%2e/test.jpg','https://www.radiorubben.no/wp-content/uploads/test.svg','https://www.radiorubben.no/wp-content/uploads/test.jpg?target=evil','https://www.radiorubben.no/wp-admin/test.jpg'] as $url)verify(!studio_newsroom_image_url($url),'Image request rejects untrusted URL');
