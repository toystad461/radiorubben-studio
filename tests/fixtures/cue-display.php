<?php
ob_start();require dirname(__DIR__).'/cue-display.php';ob_end_clean();
function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8');}
$_SESSION=['csrf'=>'synthetic'];$t['musicAudioProfile']=$profile;
$selected=$t+['available'=>true,'revision'=>2];$selectedMetadata=$t['metadata'];$canUpload=true;$canListen=true;
require dirname(__DIR__,2).'/app/views/music-library-cues.php';
