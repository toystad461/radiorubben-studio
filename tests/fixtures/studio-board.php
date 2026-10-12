<?php
declare(strict_types=1);
$root=dirname(__DIR__,2);$tmp=sys_get_temp_dir().'/rr-studio-view-'.bin2hex(random_bytes(4));
foreach(['/app/views','/public']as$d)mkdir($tmp.$d,0700,true);
copy($root.'/public/ai-studio.php',$tmp.'/public/ai-studio.php');copy($root.'/app/producer.php',$tmp.'/app/producer.php');
foreach(['head','sidebar','navigation','account']as$n)copy($root.'/app/views/'.$n.'.php',$tmp.'/app/views/'.$n.'.php');
file_put_contents($tmp.'/app/bootstrap.php','<?php function current_user(){return ["id"=>"synthetic","role"=>"admin","name"=>"Testredaktør"];} function studio_can($u,$p){return true;} function studio_roles(){return ["admin"=>"Administrator"];} function escape($s){return htmlspecialchars((string)$s,ENT_QUOTES,"UTF-8");} function icon(...$a){return "";} $config=["auth_mode"=>"demo","openai_api_key"=>"synthetic"];');
$_SESSION=['csrf'=>'synthetic'];$_SERVER['REMOTE_ADDR']='127.0.0.1';
register_shutdown_function(static function()use($tmp){foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($tmp,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST)as$f)$f->isDir()?rmdir($f->getPathname()):unlink($f->getPathname());rmdir($tmp);});
require $tmp.'/public/ai-studio.php';
