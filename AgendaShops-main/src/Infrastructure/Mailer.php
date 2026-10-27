<?php
declare(strict_types=1);
namespace AgendaShops\Infrastructure;

final class Mailer
{
    public function send(string $to, string $subject, string $body): void
    {
        $from = getenv('MAIL_FROM') ?: 'noreply@agendashops.local';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL) || !filter_var($from, FILTER_VALIDATE_EMAIL)) { throw new \RuntimeException('Invalid mail address.'); }
        if ((getenv('MAIL_TRANSPORT') ?: 'file') === 'file') {
            if (getenv('APP_ENV') === 'production') { throw new \RuntimeException('Configure MAIL_TRANSPORT=smtp em produção.'); }
            $path = getenv('MAIL_PATH') ?: Database::storage() . '/mail';
            if (!is_dir($path)) { mkdir($path, 0700, true); }
            file_put_contents($path . '/' . Database::id() . '.txt', "To: {$to}\nSubject: {$subject}\n\n{$body}", LOCK_EX);
            return;
        }
        $host = getenv('SMTP_HOST') ?: '';
        $port = (int)(getenv('SMTP_PORT') ?: 587);
        $socket = stream_socket_client('tcp://' . $host . ':' . $port, $error, $message, 15);
        if (!$socket) { throw new \RuntimeException('Mail delivery unavailable.'); }
        stream_set_timeout($socket, 15);
        try {
            $this->expect($socket, [220]);
            $this->command($socket, 'EHLO agendashops', [250]);
            $this->command($socket, 'STARTTLS', [220]);
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { throw new \RuntimeException('Mail TLS failed.'); }
            $this->command($socket, 'EHLO agendashops', [250]);
            if (getenv('SMTP_USER')) {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode(getenv('SMTP_USER')), [334]);
                $this->command($socket, base64_encode(getenv('SMTP_PASSWORD') ?: ''), [235]);
            }
            $this->command($socket, 'MAIL FROM:<' . $from . '>', [250]);
            $this->command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->command($socket, 'DATA', [354]);
            $message = 'From: ' . $from . "\r\nTo: " . $to . "\r\nSubject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($body),76,"\r\n") . ".";
            $this->command($socket, $message, [250]);
            fwrite($socket, "QUIT\r\n");
        } finally { fclose($socket); }
    }
    private function command($socket, string $command, array $codes): void
    {
        fwrite($socket, $command . "\r\n");
        $this->expect($socket, $codes);
    }
    private function expect($socket, array $codes): void
    {
        do {
            $line = fgets($socket, 2048);
            if ($line === false) { throw new \RuntimeException('Mail server unavailable.'); }
        } while (isset($line[3]) && $line[3] === '-');
        if (!in_array((int)substr($line, 0, 3), $codes, true)) { throw new \RuntimeException('Mail server rejected delivery.'); }
    }
}
