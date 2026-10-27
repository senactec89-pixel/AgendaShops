<?php
declare(strict_types=1);
namespace AgendaShops\Http;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Domain\Problem;
use AgendaShops\Modules\Health\HealthController;

final class Application
{
    public function handle(string $method, string $uri): Response
    {
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? $path : '/';
        $isApi = str_starts_with($path, '/api/');
        $headers = [
            'Content-Type'=>$isApi ? 'application/json; charset=UTF-8' : 'text/html; charset=UTF-8',
            'X-Content-Type-Options'=>'nosniff',
            'Referrer-Policy'=>'strict-origin-when-cross-origin',
            'Cache-Control'=>'no-store',
            'Content-Security-Policy'=>"default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://accounts.google.com",
        ];
        $controller = null;
        try {
            if ($path === '/api/v1/health') {
                if (!in_array($method,['GET','HEAD'],true)) { throw new Problem('METHOD_NOT_ALLOWED',405); }
                return new Response(200,json_encode((new HealthController())->check(),JSON_THROW_ON_ERROR),$headers);
            }
            if (preg_match('~^/media/([a-f0-9]{32}\.(jpg|png|webp))$~D',$path,$match) && in_array($method,['GET','HEAD'],true)) {
                $file = Database::storage() . '/media/' . $match[1];
                if (!is_file($file)) { throw new Problem('Imagem não encontrada.',404); }
                $headers['Content-Type'] = ['jpg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp'][$match[2]];
                return new Response(200,file_get_contents($file),$headers);
            }
            if (session_status() !== PHP_SESSION_ACTIVE) {
                $dir = Database::storage() . '/sessions';
                if (!is_dir($dir)) { mkdir($dir,0700,true); }
                session_save_path($dir);
                ini_set('session.use_strict_mode','1');
                ini_set('session.use_only_cookies','1');
                session_name('agendashops_session');
                session_set_cookie_params(['httponly'=>true,'secure'=>(getenv('APP_ENV') === 'production' || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')),'samesite'=>'Lax','path'=>'/']);
                session_start();
            }
            $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
            $db = new Database();
            // Installation is explicit via bin/console.php; never run DDL per request.
            $controller = new SiteController($db);
            $response = $controller->handle($method,$path);
            return new Response($response->status,$response->body,array_merge($headers,$response->headers));
        } catch (Problem $error) {
            if ($error->status === 405) { $headers['Allow'] = str_starts_with($path,'/acao/') ? 'POST' : 'GET, HEAD'; }
            if ($isApi) { return new Response($error->status,json_encode(['error'=>$error->getMessage()],JSON_THROW_ON_ERROR),$headers); }
            if ($controller) {
                $result = $controller->view('shared/error',['title'=>'Não foi possível continuar','message'=>$error->getMessage(),'code'=>$error->status],$error->status);
                return new Response($result->status,$result->body,$headers);
            }
            return new Response($error->status,'<h1>' . e($error->getMessage()) . '</h1>',$headers);
        } catch (\Throwable $error) {
            // Do not expose SQL, credentials, payloads or personal information.
            error_log('AgendaShops request failed: ' . get_class($error));
            $message = 'Serviço temporariamente indisponível. Tente novamente. Na primeira execução, realize a instalação pelo console.';
            return new Response(503,$isApi ? json_encode(['error'=>$message],JSON_THROW_ON_ERROR) : '<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><title>AgendaShops</title><h1>AgendaShops</h1><p>' . e($message) . '</p><a href="/">Tentar novamente</a></html>',$headers);
        }
    }
}
