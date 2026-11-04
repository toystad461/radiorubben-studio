<?php
declare(strict_types=1);

/** Read-only inventory using the installed Studio's own validity rules. */
function music_ai_inventory_rows(array $tracks): array {
    $counts = array_fill_keys(['total', 'music', 'otherAudio', 'metadataCurrent', 'audioAnalysisCurrent', 'audioProfileCurrent', 'technicalCueReady', 'aiCurrent', 'aiStale', 'readyForAi', 'needsAttention'], 0);
    $rows = [];
    foreach ($tracks as $track) {
        $counts['total']++;
        if (($track['kind'] ?? 'music') !== 'music') { $counts['otherAudio']++; continue; }
        $counts['music']++;
        $metadata = music_library_metadata($track);
        $analysis = music_library_analysis_current($track);
        $profile = music_audio_profile_current($track);
        $assessment = music_library_producer_current($track);
        $technical = ($track['technicalCheck']['ready'] ?? false) === true
            && ($track['technicalCheck']['sourceSha256'] ?? null) === $track['sha256'];
        $inputReady = false;
        try { music_library_producer_input($track); $inputReady = true; }
        catch (InvalidArgumentException) { /* Report the missing prerequisite below. */ }
        $missing = [];
        if (($track['available'] ?? false) !== true) $missing[] = 'original_unavailable';
        if (!$metadata) $missing[] = 'metadata_missing_or_stale';
        elseif (!$inputReady) $missing[] = 'artist_or_title_missing';
        if (!$analysis) $missing[] = 'tempo_key_missing_or_stale';
        if (!$profile) $missing[] = 'full_audio_profile_missing_or_stale';
        if (($track['audioQueue']['status'] ?? null) === 'running') $missing[] = 'audio_job_running_or_abandoned';
        $ready = !$assessment && !$missing;
        $stale = !$assessment && isset($track['producerAssessment']);
        foreach (['metadataCurrent' => (bool)$metadata, 'audioAnalysisCurrent' => (bool)$analysis,
            'audioProfileCurrent' => (bool)$profile, 'technicalCueReady' => $technical,
            'aiCurrent' => (bool)$assessment, 'aiStale' => $stale, 'readyForAi' => $ready,
            'needsAttention' => !$assessment && !$ready] as $name => $yes) {
            if ($yes) $counts[$name]++;
        }
        $fields = $metadata['fields'] ?? [];
        foreach (['artist', 'title'] as $name) {
            $override = $track['manualOverrides'][$name] ?? null;
            if (is_string($override) && trim($override) !== '' && mb_strlen($override) <= 240 && preg_match('//u', $override)) $fields[$name] = $override;
        }
        $jobStatus = $track['musicAiJob']['status'] ?? null;
        if (!in_array($jobStatus, ['running', 'prepared', 'retry', 'failed'], true)) $jobStatus = null;
        $rows[] = [
            'trackId' => $track['id'], 'artist' => $fields['artist'] ?? null, 'title' => $fields['title'] ?? null,
            'status' => $assessment ? 'ai_current' : ($ready ? 'ready_for_ai_runtime_check' : 'prerequisites_missing'),
            'missing' => $missing, 'technicalCueReady' => $technical,
            'aiStale' => $stale, 'previousAiJobStatus' => $jobStatus,
            'previousAiJobAttempts' => is_int($track['musicAiJob']['attempts'] ?? null) ? $track['musicAiJob']['attempts'] : null,
            'energy' => $assessment['energy'] ?? null, 'periods' => $assessment['periods'] ?? [],
            'confidence' => $assessment['confidence'] ?? null,
        ];
    }
    return ['counts' => $counts, 'tracks' => $rows];
}

function music_ai_inventory(string $app, ?string $root = null): array {
    $root ??= music_library_root();
    $index = $root . '/library.json';
    $before = is_file($index) && !is_link($index) ? hash_file('sha256', $index) : null;
    // Older versions accept only root and include every audio type; newer L02 filters by kind.
    $reader = new ReflectionFunction('music_library_read');
    $tracks = $reader->getNumberOfParameters() >= 2 ? music_library_read($root, 'all') : music_library_read($root);
    $report = music_ai_inventory_rows($tracks);
    $after = is_file($index) && !is_link($index) ? hash_file('sha256', $index) : null;
    $files = [];
    foreach (get_included_files() as $file) {
        if (str_starts_with($file, $app . '/app/')) $files[substr($file, strlen($app) + 1)] = hash_file('sha256', $file);
    }
    ksort($files);
    return [
        'schemaVersion' => 1, 'status' => $before === $after ? 'read_only_inventory' : 'library_changed_during_read',
        'generatedAtUtc' => gmdate('c'), 'appRoot' => $app, 'libraryHashBefore' => $before,
        'libraryHashAfter' => $after, 'installedCodeHashes' => $files,
        'limits' => [
            'No API request, credential loading, analysis, approval or library write is performed.',
            'Original availability is the installed reader check; this does not rehash or decode every original.',
            'Ready for AI means prerequisites only; API access, worker activation and retry eligibility remain separate.',
            'Technical cue readiness alone does not provide tempo, key or the full audio profile.',
        ],
    ] + $report;
}

if (!defined('RR_MUSIC_AI_INVENTORY_TEST')) {
    if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
    ini_set('display_errors', '0');
    try {
        $args = $argv; array_shift($args);
        if (count($args) !== 1 || !str_starts_with($args[0], '--app-root=/')) throw new InvalidArgumentException('arguments');
        $app = realpath(substr($args[0], 11));
        if (!$app || !is_file($app . '/app/music-library-queue.php')) throw new RuntimeException('application');
        // Load definitions only. Do not load configuration or call load_config().
        require_once $app . '/app/music-library-queue.php';
        echo json_encode(music_ai_inventory($app), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    } catch (Throwable) {
        fwrite(STDERR, "STOPP: Read-only inventory failed. Check application path, access and library integrity. No credentials are logged.\n");
        exit(1);
    }
}
