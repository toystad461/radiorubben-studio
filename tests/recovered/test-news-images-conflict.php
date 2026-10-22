<?php
declare(strict_types=1);
function rrni_manifest(): array {return [];}
try {require dirname(__DIR__,2).'/app/news-images.php';}
catch(RuntimeException $e){
    if(!str_contains($e->getMessage(),'ulike bildekontrakter'))throw $e;
    echo "OK: incompatible existing image contract rejected\n";exit(0);
}
throw new RuntimeException('Incompatible contract was accepted');
