<?php
declare(strict_types=1);
namespace RadioRubben\Integrations;

use Closure;
use RuntimeException;
use Throwable;

/** Read-only, server-side client. There is deliberately no arbitrary request/control method. */
final class AzuraCastClient
{
    private const MAX_BYTES = 2097152;
    private string $baseUrl;
    private string $stationId;
    private string $apiKey;
    private ?Closure $transport;

    /** The optional transport is for tests: fn(string $url, array $headers): array{status:int,body:string}. */
    public function __construct(array $config, ?Closure $transport = null)
    {
        $this->baseUrl = rtrim(trim((string)($config['azuracast_base_url'] ?? '')), '/');
        $this->stationId = (string)($config['azuracast_station_id'] ?? '1');
        $this->apiKey = (string)($config['azuracast_api_key'] ?? '');
        $this->transport = $transport;
        if (!preg_match('/\A[a-zA-Z0-9_-]+\z/', $this->stationId)
            || ($this->apiKey !== '' && !preg_match('/\A[\x21-\x7e]+\z/', $this->apiKey))) {
            throw new RuntimeException('Ugyldig AzuraCast-konfigurasjon.');
        }
        if ($this->isMock()) return;
        $url = parse_url($this->baseUrl);
        $localHttp = ($config['azuracast_allow_local_http'] ?? false) === true
            && ($url['scheme'] ?? '') === 'http'
            && in_array($url['host'] ?? '', ['localhost', '127.0.0.1', '[::1]', 'host.docker.internal'], true);
        if (!is_array($url) || empty($url['host']) || isset($url['user']) || isset($url['pass'])
            || isset($url['query']) || isset($url['fragment']) || !empty($url['path'])
            || preg_match('/[\x00-\x20\x7f\\\\]/', $this->baseUrl)
            || (!(($url['scheme'] ?? '') === 'https') && !$localHttp)) {
            throw new RuntimeException('AzuraCast krever en gyldig HTTPS-baseadresse uten sti. Lokal HTTP må aktiveres eksplisitt.');
        }
    }

    public function isMock(): bool { return $this->baseUrl === ''; }

    public function station(): array
    {
        return $this->isMock() ? $this->fixture()['station'] : $this->get('/station/'.$this->stationId, false, false);
    }

    /** Includes recent song_history, aggregate listeners and live.is_live; never listener identities. */
    public function nowPlaying(): array
    {
        $data = $this->isMock() ? $this->fixture() : $this->get('/nowplaying/'.$this->stationId, false, false);
        if (!is_array($data['station'] ?? null) || !is_array($data['listeners'] ?? null)
            || !is_array($data['live'] ?? null) || !is_bool($data['is_online'] ?? null)
            || !is_bool($data['live']['is_live'] ?? null)
            || !is_array($data['song_history'] ?? null) || !array_is_list($data['song_history'])
            || !array_key_exists('now_playing', $data)
            || ($data['now_playing'] !== null && !is_array($data['now_playing']))) {
            throw new RuntimeException('AzuraCast returnerte et uventet Now Playing-format.');
        }
        return $data;
    }

    // Optional authenticated reads. Not called by the test page and never exposed through a browser proxy.
    public function playlists(): array { return $this->get('/station/'.$this->stationId.'/playlists', true); }
    public function media(): array { return $this->get('/station/'.$this->stationId.'/files', true); }
    public function queue(): array { return $this->get('/station/'.$this->stationId.'/queue', true); }

    private function fixture(): array { return require __DIR__.'/fixtures/azuracast-nowplaying.php'; }

    private function get(string $path, bool $authenticated, bool $list = true): array
    {
        if ($this->isMock()) return []; // No network, including authenticated reads, when unconfigured.
        if ($authenticated && $this->apiKey === '') {
            throw new RuntimeException('Dette lesekallet krever en serverlagret AzuraCast API-nøkkel.');
        }
        $headers = ['Accept: application/json'];
        if ($authenticated) $headers[] = 'Authorization: Bearer '.$this->apiKey;
        try {
            $response = $this->transport !== null
                ? ($this->transport)($this->baseUrl.'/api'.$path, $headers)
                : $this->curlGet($this->baseUrl.'/api'.$path, $headers);
        } catch (Throwable) {
            // Do not retain the original exception: URLs, headers or response bodies may contain secrets.
            throw new RuntimeException('Kan ikke kontakte AzuraCast. Kontroller tilkoblingen på serveren.');
        }
        if (($response['status'] ?? 0) !== 200) {
            throw new RuntimeException('AzuraCast avviste lesekallet eller er utilgjengelig.');
        }
        $body = $response['body'] ?? '';
        if (!is_string($body) || strlen($body) > self::MAX_BYTES) {
            throw new RuntimeException('AzuraCast-svaret er for stort.');
        }
        try {
            $data = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('AzuraCast returnerte ugyldig JSON.');
        }
        // Also distinguish {} from [] (both decode to [] in associative mode).
        if (!is_array($data) || !str_starts_with(ltrim($body), $list ? '[' : '{')
            || ($list && !array_is_list($data))) {
            throw new RuntimeException('AzuraCast returnerte et uventet svarformat.');
        }
        return $data;
    }

    private function curlGet(string $url, array $headers): array
    {
        $handle = curl_init($url);
        if ($handle === false) throw new RuntimeException('Transport unavailable.');
        $body = '';
        try {
            curl_setopt_array($handle, [
                CURLOPT_HTTPGET => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 5,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$body): int {
                    if (strlen($body) + strlen($chunk) > self::MAX_BYTES) return 0;
                    $body .= $chunk;
                    return strlen($chunk);
                },
            ]);
            if (curl_exec($handle) === false) throw new RuntimeException('Transport failed.');
            return ['status' => (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'body' => $body];
        } finally {
            curl_close($handle);
        }
    }
}
