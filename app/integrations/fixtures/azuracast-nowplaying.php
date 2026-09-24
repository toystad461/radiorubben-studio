<?php
declare(strict_types=1);
// Synthetic data only. Fixed timestamps make tests reproducible; the UI labels it as example data.
return [
    'station' => ['id' => 1, 'name' => 'Radio Rubben TEST', 'shortcode' => 'radio_rubben_test'],
    'listeners' => ['total' => 7, 'unique' => 5, 'current' => 7],
    'live' => ['is_live' => false, 'streamer_name' => '', 'broadcast_start' => null, 'art' => null],
    'now_playing' => [
        'sh_id' => 3, 'played_at' => 1790251200, 'duration' => 180,
        'elapsed' => 45, 'remaining' => 135, 'playlist' => 'Testrotasjon',
        'song' => ['id' => 'test-song-3', 'title' => 'En ny dag (test)', 'artist' => 'Eksempelartist', 'text' => 'Eksempelartist - En ny dag (test)'],
    ],
    'playing_next' => null,
    'song_history' => [
        ['sh_id' => 2, 'played_at' => 1790251020, 'duration' => 180, 'song' => ['title' => 'Nær deg (test)', 'artist' => 'Eksempelband']],
        ['sh_id' => 1, 'played_at' => 1790250840, 'duration' => 180, 'song' => ['title' => 'God morgen (test)', 'artist' => 'Eksempelartist']],
    ],
    'is_online' => true,
    'cache' => null,
];
