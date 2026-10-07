<?php
declare(strict_types=1);
require __DIR__.'/../scripts/studio-selective.php';
function check(bool $v,string $m):void { if(!$v) throw new RuntimeException($m); }
function fails(callable $f,string $message):void {try{$f();}catch(Throwable $e){check(str_contains($e->getMessage(),$message),$e->getMessage());return;}throw new RuntimeException('Expected rejection: '.$message);}
$t=sys_get_temp_dir().'/rr-selective-'.bin2hex(random_bytes(8));mkdir($t,0700);
$r=$t.'/live';$s=$t.'/source';$w=$t.'/private';mkdir($r);mkdir($s);mkdir($w);mkdir($w.'/backups');
$b=['commit'=>str_repeat('a',40),'files'=>[]];
try {
for($i=0;$i<12;$i++){
 $p="studio-public/file$i.php";studioWrite($s.'/'.$p,"code-$i",0644);studioWrite($r.'/'.$p,"code-$i",0644);
 $b['files'][$p]=['source'=>studioHash($s.'/'.$p),'live'=>studioHash($r.'/'.$p)];
}
// Known live differences stay untouched on initialization.
$p='studio-public/file0.php';studioWrite($r.'/'.$p,'existing-logo',0640);$b['files'][$p]['live']=studioHash($r.'/'.$p);
$health=static fn()=>null;
$result=studioPublish($r,$s,$w,$b,str_repeat('b',40),'initial',$health);
check($result['changed']===0,'Bootstrap must preserve live drift');check(file_get_contents($r.'/'.$p)==='existing-logo','Logo preserved');
studioWrite($r.'/studio-private/config/local.php','private-key',0600);
studioWrite($r.'/studio-private/data/manus.json','editorial',0600);
studioWrite($s.'/'.$p,'new-logo',0644);
studioWrite($s.'/studio-public/new/file.php','new',0644);
studioWrite($s.'/studio-public/release.json',json_encode(['product'=>'Radio Rubben Studio','version'=>'0.1.0','commit'=>str_repeat('c',40)]),0644);
unlink($s.'/studio-public/file1.php');
$result=studioPublish($r,$s,$w,$b,str_repeat('c',40),'update',$health);
check($result['changed']===4,'Changed/add/remove and release manifest planned');check(file_get_contents($r.'/'.$p)==='new-logo','Update');
check(json_decode(file_get_contents($r.'/studio-public/release.json'),true)['version']==='0.1.0','Published release manifest');
check((fileperms($r.'/'.$p)&0777)===0640,'Existing permissions preserved');check(!file_exists($r.'/studio-public/file1.php'),'Removal');
check(file_get_contents($r.'/studio-private/config/local.php')==='private-key','Private config preserved');check(file_get_contents($r.'/studio-private/data/manus.json')==='editorial','Runtime data preserved');
$oldState=file_get_contents($w.'/selective-state.json');
$oldRelease=file_get_contents($r.'/studio-public/release.json');
studioWrite($s.'/studio-public/release.json',json_encode(['version'=>'0.1.1','commit'=>str_repeat('d',40)]),0644);
studioWrite($s.'/'.$p,'bad-change',0644);studioWrite($s.'/studio-public/new2.php','new2',0644);
unlink($s.'/studio-public/file2.php');
fails(fn()=>studioPublish($r,$s,$w,$b,str_repeat('d',40),'rollback',static function(){throw new RuntimeException('health failure');}),'health failure');
check(file_get_contents($r.'/'.$p)==='new-logo','Rollback restored');check(!file_exists($r.'/studio-public/new2.php'),'Rollback removes additions');check(file_exists($r.'/studio-public/file2.php'),'Rollback restores deletion');check(file_get_contents($w.'/selective-state.json')===$oldState,'Failed release cannot advance state');
check(file_get_contents($r.'/studio-public/release.json')===$oldRelease,'Failed release restores previous public version');
studioWrite($r.'/'.$p,'server-edit',0644);
fails(fn()=>studioPublish($r,$s,$w,$b,str_repeat('e',40),'drift',$health),'Production drift');
unlink($r.'/'.$p);symlink($t.'/outside',$r.'/'.$p);
fails(fn()=>studioPublish($r,$s,$w,$b,str_repeat('f',40),'symlink',$health),'Symlink rejected');
check(!studioManaged('studio-public/../config/local.php'),'Traversal rejected');check(!studioManaged('studio-private/config/local.php'),'Config excluded');
check(studioManaged('studio-public/release.json'),'Exact public release metadata managed');
check(!studioManaged('studio-public/users.json')&&!studioManaged('studio-public/config.json'),'Other JSON remains excluded');
echo "PASS bootstrap, selective update/add/delete, private data preservation, permissions, rollback, drift and symlink protection\n";
} finally {
foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($t,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST) as $f){if($f->isDir()&&!$f->isLink())rmdir($f->getPathname());else unlink($f->getPathname());}rmdir($t);
}
