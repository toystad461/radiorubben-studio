<?php
declare(strict_types=1);
require_once __DIR__.'/music-studio.php';
/** Boot-only resumption, never a watchdog that undoes an operator's Stop. */
function music_boot_allowed(array $queue,array $state,array $control,array $heartbeat):bool {
    return ($queue['studio']['rotation']['enabled']??false)===true
        && ($control['command']['action']??'')==='start'
        && ($control['command']['id']??null)===($state['ack']??null)
        && ($state['mode']??'')==='stopped'
        && !music_playout_inflight_entries($state)
        && music_playout_heartbeat_fresh($state,$heartbeat);
}
