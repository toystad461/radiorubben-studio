<?php
declare(strict_types=1);

function studio_graph_token(): ?string
{
    if (!studio_is_admin(current_user()) || ($_SESSION['graph_expires'] ?? 0) <= time() + 30) return null;
    return is_string($_SESSION['graph_token'] ?? null) ? $_SESSION['graph_token'] : null;
}

/** @return array{status:int,data:array} */
function studio_graph_request(string $method, string $path, ?array $payload = null): array
{
    $token = studio_graph_token();
    if ($token === null || !preg_match('#^/[A-Za-z0-9/_?$=&%.,()\x27-]+$#D', $path)) {
        throw new RuntimeException('Graph access unavailable');
    }
    $curl = curl_init('https://graph.microsoft.com/v1.0' . $path);
    $body = '';
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => $method, CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token, 'Accept: application/json', 'Content-Type: application/json'],
        CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$body): int {
            if (strlen($body) + strlen($chunk) > 262144) return 0;
            $body .= $chunk;
            return strlen($chunk);
        },
    ]);
    if ($payload !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    $ok = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if ($ok === false) throw new RuntimeException('Graph transport failed');
    $data = json_decode($body, true);
    return ['status' => $status, 'data' => is_array($data) ? $data : []];
}

function studio_graph_user(string $upn): ?array
{
    if (!studio_valid_upn($upn)) return null;
    $result = studio_graph_request('GET', '/users/' . rawurlencode($upn) . '?$select=id,displayName,userPrincipalName');
    return $result['status'] === 200 ? $result['data'] : null;
}

function studio_graph_service_principal(string $clientId): string
{
    if (!preg_match('/^[0-9a-f-]{36}$/iD', $clientId)) throw new RuntimeException('Invalid client ID');
    $result = studio_graph_request('GET', "/servicePrincipals(appId='{$clientId}')?" . '$select=id');
    $id = $result['data']['id'] ?? null;
    if ($result['status'] !== 200 || !is_string($id) || !preg_match('/^[0-9a-f-]{36}$/iD', $id)) {
        throw new RuntimeException('Studio enterprise app unavailable');
    }
    return $id;
}

function studio_graph_assign(string $userId, string $clientId): bool
{
    if (!preg_match('/^[0-9a-f-]{36}$/iD', $userId)) throw new RuntimeException('Invalid user ID');
    $appId = studio_graph_service_principal($clientId);
    $existing = studio_graph_request('GET', '/users/' . $userId . '/appRoleAssignments?$select=resourceId');
    if ($existing['status'] !== 200) throw new RuntimeException('Could not inspect assignments');
    foreach ($existing['data']['value'] ?? [] as $entry) {
        if (($entry['resourceId'] ?? null) === $appId) return false;
    }
    $result = studio_graph_request('POST', '/users/' . $userId . '/appRoleAssignments', [
        'principalId' => $userId, 'resourceId' => $appId,
        'appRoleId' => '00000000-0000-0000-0000-000000000000',
    ]);
    if ($result['status'] !== 201) throw new RuntimeException('Could not assign Studio access');
    return true;
}

function studio_graph_create_user(string $name, string $upn, string $password): array
{
    if (!studio_valid_upn($upn) || strlen($name) < 2 || strlen($name) > 120) {
        throw new InvalidArgumentException('Invalid user details');
    }
    $nickname = explode('@', $upn, 2)[0];
    $result = studio_graph_request('POST', '/users', [
        'accountEnabled' => true, 'displayName' => $name,
        'mailNickname' => $nickname, 'userPrincipalName' => strtolower($upn),
        'passwordProfile' => ['forceChangePasswordNextSignIn' => true, 'password' => $password],
    ]);
    if ($result['status'] !== 201 || !is_string($result['data']['id'] ?? null)) {
        throw new RuntimeException('Could not create Entra user');
    }
    return $result['data'];
}
