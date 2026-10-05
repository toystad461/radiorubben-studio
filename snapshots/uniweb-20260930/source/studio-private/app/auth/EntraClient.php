<?php
declare(strict_types=1);
use Jumbojett\OpenIDConnectClient;
final class EntraClient extends OpenIDConnectClient
{
    private string $tenant;
    public function __construct(array $config)
    {
        $this->tenant = $config['tenant_id'];
        parent::__construct('https://login.microsoftonline.com/'.$this->tenant.'/v2.0', $config['client_id'], $config['client_secret']);
        $this->setRedirectURL(rtrim($config['base_url'], '/').'/auth/callback.php');
        $this->addScope(['profile', 'email']);
        $this->setCodeChallengeMethod('S256');
        $this->setTimeout(15);
        $this->addAuthParam(['response_mode'=>'query']);
    }
    protected function verifyJWTClaims($claims, ?string $accessToken = null): bool
    {
        return isset($claims->nonce, $claims->exp, $claims->tid, $claims->sub)
            && is_string($claims->nonce) && $claims->nonce === $this->getNonce()
            && is_int($claims->exp) && $claims->exp > time()
            && $claims->tid === $this->tenant && is_string($claims->sub) && $claims->sub !== ''
            && parent::verifyJWTClaims($claims, $accessToken);
    }
}
