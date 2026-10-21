<?php
declare(strict_types=1);
$detailUrl='/music-library.php?detail=1&track='.$actionTrack['id'];
$actionTitle=music_library_effective_fields($actionTrack)['title']??$actionTrack['originalName'];
?>
<details class="track-actions">
<summary aria-label="<?=escape('Valg for '.$actionTitle)?>" title="Låtvalg">⋯</summary>
<nav aria-label="<?=escape('Handlinger for '.$actionTitle)?>">
<?php if($canUpload&&$actionTrack['available']):?><a href="<?=escape($detailUrl)?>#edit-track">Rediger låt</a><?php endif;?>
<?php if($canListen&&$actionTrack['available']&&$queueConnected):?><button type="button" data-track-queue="<?=escape($actionTrack['id'])?>" data-track-version="<?=escape($actionTrack['sha256'])?>">Legg sist i køen</button><?php endif;?>
<a href="<?=escape($detailUrl)?>#cue-points">Cue-punkter</a>
<a href="<?=escape($detailUrl)?>#album-cover">Albumcover</a>
<a href="<?=escape($detailUrl)?>#track-detail">Vis låtdetaljer</a>
<?php if($queueConnected):?><a href="/music-studio.php">Åpne musikkøen</a><?php endif;?>
</nav>
</details>
