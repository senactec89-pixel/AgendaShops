<?php
declare(strict_types=1);
namespace AgendaShops\Modules\Identity;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Domain\{Input, Problem};

final class IdentityService
{
    public function __construct(private Database $db) {}

    public function register(array $data, string $origin): array
    {
        if (($data['consent'] ?? '') !== '1') { throw new Problem('Aceite os termos e a política de privacidade.'); }
        $name = Input::text($data, 'name', 2, 100);
        $email = Input::email($data);
        $phone = Input::phone($data);
        $password = $this->password($data);
        if ($this->db->one('SELECT id FROM users WHERE email = ? OR phone = ?', [$email, $phone])) {
            throw new Problem('E-mail ou telefone já cadastrado.');
        }
        try {
            $id = $this->db->insert('users', ['name' => $name, 'email' => $email, 'phone' => $phone, 'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role' => 'CUSTOMER', 'terms_version' => '2026-10-23', 'consent_at' => Database::now(), 'consent_origin' => $origin, 'created_at' => Database::now()]);
        } catch (\PDOException $e) {
            if (in_array($e->getCode(), ['23000', '23505'], true)) { throw new Problem('E-mail ou telefone já cadastrado.'); }
            throw $e;
        }
        return $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public function login(array $data, string $ip): array
    {
        $email = Input::email($data);
        $this->limit('login:' . $ip . ':' . $email, 8, 900);
        $user = $this->db->one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        $password = is_string($data['password'] ?? null) ? $data['password'] : '';
        $dummy = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
        if (!password_verify($password, $user['password_hash'] ?? $dummy) || !$user || !$user['password_hash']) {
            throw new Problem('E-mail ou senha incorretos.', 401);
        }
        $this->db->execute('DELETE FROM rate_limits WHERE key_hash = ?', [hash('sha256', 'login:' . $ip . ':' . $email)]);
        return $user;
    }

    public function limit(string $key, int $maximum = 15, int $seconds = 300): void
    {
        $key = hash('sha256', $key);
        $this->db->transaction(function () use ($key, $maximum, $seconds): void {
            $row = $this->db->one('SELECT * FROM rate_limits WHERE key_hash = ?', [$key]);
            if (!$row || (int)$row['window_start'] < time() - $seconds) {
                $this->db->execute('INSERT INTO rate_limits(key_hash,attempts,window_start) VALUES(?,1,?) ON CONFLICT(key_hash) DO UPDATE SET attempts=1,window_start=excluded.window_start', [$key, time()]);
            } else {
                if ((int)$row['attempts'] >= $maximum) { throw new Problem('Muitas tentativas. Aguarde alguns minutos.', 429); }
                $this->db->execute('UPDATE rate_limits SET attempts = attempts + 1 WHERE key_hash = ?', [$key]);
            }
        });
    }

    public function update(array $user, array $data): void
    {
        $name = Input::text($data, 'name', 2, 100);
        $phone = Input::phone($data);
        if ($this->db->one('SELECT id FROM users WHERE phone = ? AND id <> ?', [$phone, $user['id']])) { throw new Problem('Telefone já cadastrado.'); }
        $this->db->execute('UPDATE users SET name = ?, phone = ? WHERE id = ?', [$name, $phone, $user['id']]);
    }

    public function delete(array $user): void
    {
        $this->db->transaction(function () use ($user): void {
            if ($user['role'] === 'ADMIN' && (int)$this->db->one("SELECT COUNT(*) AS total FROM users WHERE role='ADMIN' AND active=1")['total'] <= 1) {
                throw new Problem('A última conta administrativa não pode ser excluída.');
            }
            if ($this->db->one("SELECT id FROM bookings WHERE (customer_id = ? OR business_id IN (SELECT business_id FROM memberships WHERE user_id = ?)) AND status IN ('PENDING_PARTNER_APPROVAL','CONFIRMED','IN_PROGRESS')", [$user['id'], $user['id']])) {
                throw new Problem('Cancele ou conclua os agendamentos ativos antes de excluir a conta.');
            }
            $this->db->execute('UPDATE users SET name = ?, email = ?, phone = NULL, password_hash = NULL, google_id = NULL, active = 0, session_version = session_version + 1 WHERE id = ?', ['Conta excluída', $user['id'] . '@deleted.invalid', $user['id']]);
            $this->db->execute('DELETE FROM favorites WHERE user_id = ?', [$user['id']]);
            $this->db->execute("UPDATE businesses SET status = 'SUSPENDED' WHERE owner_id = ?", [$user['id']]);
        });
    }

    public function resetRequest(array $data, string $ip): void
    {
        $email = Input::email($data);
        $this->limit('reset:' . $ip, 5, 900);
        $user = $this->db->one('SELECT * FROM users WHERE email = ? AND active = 1', [$email]);
        if (!$user) { return; }
        $token = bin2hex(random_bytes(32));
        $this->db->transaction(function () use ($user, $token): void {
            $this->db->execute('DELETE FROM password_resets WHERE user_id = ?', [$user['id']]);
            $this->db->insert('password_resets', ['user_id' => $user['id'], 'token_hash' => hash('sha256', $token), 'expires_at' => gmdate('Y-m-d\TH:i:s.u\Z', time() + 1800)]);
        });
        $url = rtrim(getenv('APP_URL') ?: 'http://localhost:8000', '/') . '/redefinir?token=' . $token;
        (new \AgendaShops\Infrastructure\Mailer())->send($email, 'Recuperar acesso ao AgendaShops', "Para definir sua senha, abra este link em até 30 minutos:\n" . $url . "\nSe você não solicitou, ignore esta mensagem.");
    }

    public function reset(array $data): void
    {
        $password = $this->password($data);
        $hash = hash('sha256', Input::text($data, 'token', 64, 64));
        $this->db->transaction(function () use ($password, $hash): void {
            $row = $this->db->one('SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?' . ($this->db->sqlite ? '' : ' FOR UPDATE'), [$hash, Database::now()]);
            if (!$row) { throw new Problem('Link inválido ou expirado. Solicite outro.'); }
            $this->db->execute('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ? AND active = 1', [password_hash($password, PASSWORD_DEFAULT), $row['user_id']]);
            $this->db->execute('UPDATE password_resets SET used_at = ? WHERE id = ?', [Database::now(), $row['id']]);
        });
    }

    private function password(array $data): string
    {
        $password = $data['password'] ?? '';
        if (!is_string($password) || strlen($password) < 10 || strlen($password) > 72) { throw new Problem('A senha deve ter entre 10 e 72 caracteres.'); }
        if ($password !== ($data['password_confirmation'] ?? '')) { throw new Problem('As senhas não coincidem.'); }
        return $password;
    }
}
