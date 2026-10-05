<?php
declare(strict_types=1);
use Jumbojett\OpenIDConnectClient;

final class VippsClient extends OpenIDConnectClient
{
    private string $expectedIssuer;
    private string $expectedClient;
    public function __construct(array $config)
    {
        $host = $config['vipps_environment'] === 'production' ? 'https://api.vipps.no' : 'https://apitest.vipps.no';
        $this->expectedIssuer = $host.'/access-management-1.0/access/';
        $this->expectedClient = $config['vipps_client_id'];
        parent::__construct($this->expectedIssuer, $config['vipps_client_id'], $config['vipps_client_secret']);
        $this->setRedirectURL(rtrim($config['base_url'], '/').'/auth/callback.php');
        $this->addScope(['name', 'phoneNumber']); // library adds openid
        $this->setCodeChallengeMethod('S256');
        $this->setTokenEndpointAuthMethodsSupported(['client_secret_basic']);
        $this->setTimeout(15);
        $this->setIssuerValidator(fn($issuer) => $issuer === $this->expectedIssuer);
    }
    protected function fetchURL(string $url, ?string $post_body = null, array $headers = [])
    {
        $headers[] = 'Vipps-System-Name: radiorubben-studio';
        $headers[] = 'Vipps-System-Version: 1.0.0';
        return parent::fetchURL($url, $post_body, $headers);
    }
    public function verifyJWTSignature(string $jwt): bool
    {
        $header = $this->decodeJWT($jwt);
        if (!is_object($header) || ($header->alg ?? null) !== 'RS256') throw new RuntimeException('Unexpected signing algorithm');
        return parent::verifyJWTSignature($jwt);
    }
    protected function verifyJWTClaims($claims, ?string $accessToken = null): bool
    {
        return isset($claims->nonce, $claims->exp, $claims->iat, $claims->sub, $claims->aud, $claims->iss)
            && is_string($claims->nonce) && $claims->nonce === $this->getNonce()
            && is_int($claims->exp) && $claims->exp > time()
            && is_int($claims->iat) && $claims->iat <= time() + 60
            && is_string($claims->sub) && $claims->sub !== ''
            && ($claims->iss === $this->expectedIssuer)
            && (is_string($claims->aud) || is_array($claims->aud))
            && (!is_array($claims->aud) || count($claims->aud) <= 1 || ($claims->azp ?? null) === $this->expectedClient)
            && (!isset($claims->azp) || $claims->azp === $this->expectedClient)
            && parent::verifyJWTClaims($claims, $accessToken);
    }
    public static function authorizedUser(object $claims, object $info, array $allowedPhones): array
    {
        if (!isset($claims->sub, $info->sub) || !is_string($claims->sub) || !is_string($info->sub)
            || $claims->sub === '' || !hash_equals($claims->sub, $info->sub)
            || ($info->phone_number_verified ?? false) !== true
            || !is_string($info->phone_number ?? null)) throw new RuntimeException('Unverified identity');
        // Vipps returns a country-prefixed number; accept optional +, never infer a country.
        $phone = $info->phone_number;
        if (!preg_match('/^\+?[1-9][0-9]{7,14}$/D', $phone)) throw new RuntimeException('Invalid phone claim');
        $phone = '+'.ltrim($phone, '+');
        if (!in_array($phone, $allowedPhones, true)) throw new RuntimeException('Not authorized');
        return ['id'=>$claims->sub, 'name'=>is_string($info->name ?? null) ? $info->name : 'medarbeider', 'provider'=>'vipps', 'phone'=>$phone];
    }
}
