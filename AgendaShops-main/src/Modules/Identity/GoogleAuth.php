<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Identity;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Domain\Problem;

final class GoogleAuth
{
    public function __construct(private Database $db) {}
    public static function configured(): bool { return (bool)getenv('GOOGLE_CLIENT_ID') && (bool)getenv('GOOGLE_CLIENT_SECRET'); }
    public function start(): string
    {
        if (!self::configured()) { throw new Problem('Login com Google aguarda configuração do estabelecimento.', 503); }
        $_SESSION['oauth_state'] = bin2hex(random_bytes(32));
        $_SESSION['oauth_verifier'] = bin2hex(random_bytes(32));
        $_SESSION['oauth_at'] = time();
        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(['client_id'=>getenv('GOOGLE_CLIENT_ID'),'redirect_uri'=>$this->callback(),'response_type'=>'code','scope'=>'openid email profile','state'=>$_SESSION['oauth_state'],'code_challenge'=>rtrim(strtr(base64_encode(hash('sha256', $_SESSION['oauth_verifier'], true)), '+/', '-_'), '='),'code_challenge_method'=>'S256']);
    }
    public function finish(array $query): array
    {
        if (!is_string($query['state'] ?? null) || !isset($_SESSION['oauth_state']) || !hash_equals($_SESSION['oauth_state'], $query['state']) || time() - ($_SESSION['oauth_at'] ?? 0) > 600 || !is_string($query['code'] ?? null)) { throw new Problem('Sessão Google inválida. Tente novamente.', 403); }
        $verifier = $_SESSION['oauth_verifier'];
        unset($_SESSION['oauth_state'], $_SESSION['oauth_verifier'], $_SESSION['oauth_at']);
        $token = $this->request('https://oauth2.googleapis.com/token', ['code'=>$query['code'],'client_id'=>getenv('GOOGLE_CLIENT_ID'),'client_secret'=>getenv('GOOGLE_CLIENT_SECRET'),'redirect_uri'=>$this->callback(),'grant_type'=>'authorization_code','code_verifier'=>$verifier]);
        if (!is_string($token['access_token'] ?? null)) { throw new Problem('Google não autorizou o acesso.', 401); }
        // Identity is read directly from Google's authenticated TLS userinfo endpoint,
        // never from an unverified browser-provided token or JWT payload.
        $identity = $this->request('https://openidconnect.googleapis.com/v1/userinfo', null, $token['access_token']);
        if (($identity['email_verified'] ?? false) !== true || !filter_var($identity['email'] ?? '', FILTER_VALIDATE_EMAIL) || empty($identity['sub'])) { throw new Problem('O Google precisa fornecer um e-mail verificado.', 401); }
        $email = mb_strtolower($identity['email']);
        return $this->db->transaction(function () use ($identity,$email): array {
            $user = $this->db->one('SELECT * FROM users WHERE google_id=? OR email=?', [$identity['sub'],$email]);
            if ($user) {
                if (!(int)$user['active'] || ($user['google_id'] && $user['google_id'] !== $identity['sub'])) { throw new Problem('Conta indisponível.', 403); }
                // Linking an existing local account requires its current authenticated session.
                if (!$user['google_id'] && ($_SESSION['user_id'] ?? '') !== $user['id']) { throw new Problem('Entre com sua senha e vincule o Google pelo perfil para proteger sua conta.', 409); }
                $this->db->execute('UPDATE users SET google_id=? WHERE id=?', [$identity['sub'],$user['id']]);
                return $user;
            }
            if (($_SESSION['google_consent'] ?? false) !== true) { throw new Problem('Aceite os termos no cadastro antes de entrar com Google.'); }
            $id = $this->db->insert('users', ['name'=>mb_substr($identity['name'] ?? 'Cliente',0,100),'email'=>$email,'google_id'=>$identity['sub'],'role'=>'CUSTOMER','terms_version'=>'2026-10-23','consent_at'=>Database::now(),'consent_origin'=>'google-web','created_at'=>Database::now()]);
            return $this->db->one('SELECT * FROM users WHERE id=?', [$id]);
        });
    }
    private function callback(): string { return rtrim(getenv('APP_URL') ?: 'http://localhost:8000','/') . '/auth/google/callback'; }
    private function request(string $url, ?array $data = null, ?string $token = null): array
    {
        $curl = curl_init($url);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_HTTPHEADER=>$token ? ['Authorization: Bearer ' . $token] : []]);
        if ($data !== null) { curl_setopt($curl,CURLOPT_POSTFIELDS,http_build_query($data)); }
        $body = curl_exec($curl);
        $status = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        curl_close($curl);
        if (!is_string($body) || $status !== 200) { throw new Problem('Não foi possível conectar ao Google. Tente novamente.', 503); }
        return json_decode($body,true,512,JSON_THROW_ON_ERROR);
    }
}
