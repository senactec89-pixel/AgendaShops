<?php
declare(strict_types=1);
namespace AgendaShops\Http;

use AgendaShops\Infrastructure\Database;
use AgendaShops\Domain\{Input, Problem};
use AgendaShops\Modules\Identity\{IdentityService, GoogleAuth};
use AgendaShops\Modules\Catalog\CatalogService;
use AgendaShops\Modules\Bookings\BookingService;
use AgendaShops\Modules\Community\CommunityService;
use AgendaShops\Modules\Operations\OperationsService;

final class SiteController
{
    private IdentityService $identity;
    private CatalogService $catalog;
    private BookingService $bookings;
    private CommunityService $community;
    private OperationsService $operations;
    private ?array $user = null;
    public function __construct(private Database $db)
    {
        $this->identity = new IdentityService($db);
        $this->catalog = new CatalogService($db);
        $this->bookings = new BookingService($db,$this->catalog);
        $this->community = new CommunityService($db,$this->catalog,$this->bookings);
        $this->operations = new OperationsService($db,$this->catalog);
        if (isset($_SESSION['user_id'])) {
            $this->user = $db->one('SELECT * FROM users WHERE id=? AND active=1',[$_SESSION['user_id']]);
            if (!$this->user || (int)$this->user['session_version'] !== ($_SESSION['session_version'] ?? 0)) {
                $this->user = null;
                unset($_SESSION['user_id'],$_SESSION['session_version']);
            }
        }
    }
    public function handle(string $method, string $path): Response
    {
        foreach ($_GET as $value) { if (!is_string($value) || strlen($value) > 2048) { throw new Problem('Parâmetros de consulta inválidos.'); } }
        $this->bookings->expire();
        if (str_starts_with($path,'/acao/')) {
            if ($method !== 'POST') { throw new Problem('Método não permitido.',405); }
            return $this->action(substr($path,6));
        }
        if (!in_array($method,['GET','HEAD'],true)) { throw new Problem('Método não permitido.',405); }
        if ($path === '/auth/google/callback') {
            $this->signIn((new GoogleAuth($this->db))->finish($_GET));
            return $this->redirect('/perfil');
        }
        if ($path === '/') { return $this->view('public/home',['title'=>'Seu próximo cuidado']); }
        if ($path === '/instalar') {
            $token = is_string($_GET['token'] ?? null) ? $_GET['token'] : '';
            $this->setupToken($token);
            return $this->view('auth/account',['title'=>'Criar administrador','mode'=>'setup','setup_token'=>$token]);
        }
        if ($path === '/cadastro') { return $this->view('auth/account',['title'=>'Criar conta','mode'=>'register']); }
        if ($path === '/entrar') { return $this->view('auth/account',['title'=>'Entrar','mode'=>'login']); }
        if ($path === '/recuperar') { return $this->view('auth/account',['title'=>'Recuperar acesso','mode'=>'forgot']); }
        if ($path === '/redefinir') { return $this->view('auth/account',['title'=>'Redefinir senha','mode'=>'reset']); }
        if (in_array($path,['/termos','/privacidade'],true)) { return $this->view('public/legal',['title'=>$path === '/termos' ? 'Termos de uso' : 'Política de privacidade','mode'=>$path]); }
        if ($path === '/explorar') {
            if (is_numeric($_GET['lat'] ?? null) && is_numeric($_GET['lng'] ?? null) && abs((float)$_GET['lat'])<=90 && abs((float)$_GET['lng'])<=180) {
                $_SESSION['coordinates'] = ['lat'=>$_GET['lat'],'lng'=>$_GET['lng']];
            }
            $rows = $this->catalog->search($_GET);
            if (!empty($_GET['date']) && is_string($_GET['date'])) {
                $rows = array_values(array_filter($rows,fn($row)=>count($this->bookings->slots($row['id'],$_GET['date'])) > 0));
            }
            return $this->view('public/explore',['title'=>'Encontre seu próximo cuidado','services'=>$rows,'categories'=>$this->catalog->categories()]);
        }
        if ($path === '/comparar') {
            $rows = [];
            foreach ($_SESSION['compare'] ?? [] as $id) {
                try { $rows[] = $this->catalog->publicService($id); } catch (Problem $error) { if ($error->status !== 404) { throw $error; } }
            }
            $_SESSION['compare'] = array_column($rows,'id');
            foreach ($rows as &$row) {
                $row['distance'] = $this->catalog->distance($row,$_SESSION['coordinates'] ?? []);
                $row['next_slot'] = null;
                for ($d=0;$d<7;$d++) {
                    $day=(new \DateTimeImmutable('today',new \DateTimeZone($row['timezone'])))->modify('+' . $d . ' days')->format('Y-m-d');
                    $slots=$this->bookings->slots($row['id'],$day);
                    if ($slots) { $row['next_slot']=$slots[0]; break; }
                }
            }
            return $this->view('public/compare',['title'=>'Compare serviços','services'=>$rows]);
        }
        if (preg_match('~^/servicos/([a-f0-9]{32})$~D',$path,$match)) {
            $service = $this->catalog->publicService($match[1]);
            $zone = new \DateTimeZone($service['timezone']);
            $date = is_string($_GET['date'] ?? null) ? $_GET['date'] : (new \DateTimeImmutable('tomorrow',$zone))->format('Y-m-d');
            return $this->view('public/service',['title'=>$service['name'],'service'=>$service,'date'=>$date,'slots'=>$this->bookings->slots($service['id'],$date)]);
        }
        if (preg_match('~^/estabelecimentos/([a-z0-9_-]+)$~D',$path,$match)) {
            $business = $this->db->one("SELECT * FROM businesses WHERE slug=? AND status='APPROVED'",[$match[1]]);
            if (!$business) { throw new Problem('Estabelecimento não encontrado.',404); }
            $services = array_values(array_filter($this->catalog->search([]),fn($s)=>$s['business_id'] === $business['id']));
            foreach ($services as &$service) {
                $service['next_slot'] = null;
                for ($d=0;$d<7;$d++) {
                    $date=(new \DateTimeImmutable('today',new \DateTimeZone($business['timezone'])))->modify('+' . $d . ' days')->format('Y-m-d');
                    $slots=$this->bookings->slots($service['id'],$date);
                    if ($slots) { $service['next_slot']=$slots[0]; break; }
                }
            }
            return $this->view('public/business',['title'=>$business['name'],'business'=>$business,'services'=>$services,'professionals'=>$this->db->all('SELECT * FROM professionals WHERE business_id=? AND active=1',[$business['id']]),'reviews'=>$this->db->all('SELECT r.*,u.name FROM reviews r JOIN users u ON u.id=r.customer_id WHERE r.business_id=? AND r.visible=1 ORDER BY r.created_at DESC',[$business['id']])]);
        }
        if (str_starts_with($path,'/api/v1/slots/')) {
            $id = substr($path,14);
            return new Response(200,json_encode($this->bookings->slots($id,Input::text($_GET,'date')),JSON_THROW_ON_ERROR),['Content-Type'=>'application/json; charset=UTF-8']);
        }
        if ($path === '/perfil') {
            $this->requireUser();
            return $this->view('customer/profile',['title'=>'Minha conta','notifications'=>$this->db->all('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100',[$this->user['id']])]);
        }
        if ($path === '/favoritos') {
            $this->requireUser();
            $rows = $this->db->all("SELECT b.* FROM businesses b JOIN favorites f ON f.business_id=b.id WHERE f.user_id=? AND b.status='APPROVED'",[$this->user['id']]);
            return $this->view('customer/favorites',['title'=>'Meus favoritos','businesses'=>$rows]);
        }
        if ($path === '/agendamentos') {
            $this->requireUser();
            $rows = $this->db->all('SELECT b.*,bs.name AS business_name,u.name AS customer_name FROM bookings b JOIN businesses bs ON bs.id=b.business_id JOIN users u ON u.id=b.customer_id WHERE b.customer_id=? OR b.business_id IN(SELECT business_id FROM memberships WHERE user_id=?) ORDER BY b.created_at DESC',[$this->user['id'],$this->user['id']]);
            foreach ($rows as &$row) {
                $row['conversation'] = $this->db->one('SELECT id FROM conversations WHERE booking_id=?',[$row['id']])['id'];
                $row['reviewed'] = (bool)$this->db->one('SELECT id FROM reviews WHERE booking_id=?',[$row['id']]);
                $row['history'] = $this->db->all('SELECT * FROM booking_history WHERE booking_id=? ORDER BY created_at,id',[$row['id']]);
            }
            return $this->view('shared/bookings',['title'=>'Solicitações e agendamentos','bookings'=>$rows]);
        }
        if ($path === '/mensagens') {
            $this->requireUser();
            $id = is_string($_GET['conversa'] ?? null) ? $_GET['conversa'] : '';
            $messages = $id ? $this->community->read($this->user,$id) : [];
            return $this->view('shared/messages',['title'=>'Mensagens','conversations'=>$this->community->conversations($this->user),'messages'=>$messages,'conversation'=>$id ? $this->community->conversation($this->user,$id) : null,'reads'=>$id ? $this->db->all('SELECT r.*,u.name FROM conversation_reads r JOIN users u ON u.id=r.user_id WHERE r.conversation_id=? AND r.user_id<>?',[$id,$this->user['id']]) : []]);
        }
        if ($path === '/parceiro') {
            $this->requireUser();
            $businesses = $this->db->all('SELECT b.*,m.role AS membership_role FROM businesses b JOIN memberships m ON m.business_id=b.id WHERE m.user_id=? ORDER BY b.name',[$this->user['id']]);
            $selected = is_string($_GET['business'] ?? null) ? $_GET['business'] : ($businesses[0]['id'] ?? '');
            $business = null;
            foreach ($businesses as $item) { if ($item['id'] === $selected) { $business = $item; } }
            if ($selected && !$business) { throw new Problem('Acesso negado.',403); }
            $services = $business ? $this->db->all('SELECT * FROM services WHERE business_id=? ORDER BY name',[$selected]) : [];
            foreach ($services as &$service) { $service['linked_professionals'] = array_column($this->db->all('SELECT professional_id FROM service_professionals WHERE service_id=?',[$service['id']]),'professional_id'); }
            return $this->view('partner/dashboard',['title'=>'Portal do parceiro','businesses'=>$businesses,'business'=>$business,'categories'=>$this->catalog->categories(),'professionals'=>$business ? $this->db->all('SELECT * FROM professionals WHERE business_id=? ORDER BY name',[$selected]) : [],'services'=>$services,'blocks'=>$business ? $this->db->all('SELECT sb.*,p.name FROM schedule_blocks sb JOIN professionals p ON p.id=sb.professional_id WHERE p.business_id=? ORDER BY sb.starts_at',[$selected]) : [],'members'=>$business ? $this->db->all('SELECT u.name,u.email,m.role FROM memberships m JOIN users u ON u.id=m.user_id WHERE m.business_id=?',[$selected]) : []]);
        }
        if (preg_match('~^/admin/conversas/([a-f0-9]{32})$~D',$path,$match)) {
            $this->requireUser(); $this->catalog->admin($this->user);
            $conversation = $this->db->one('SELECT * FROM conversations WHERE id=?',[$match[1]]);
            if (!$conversation) { throw new Problem('Conversa não encontrada.',404); }
            $this->catalog->audit($this->user,'CONVERSATION_INSPECTED',$match[1]);
            return $this->view('admin/conversation',['title'=>'Inspeção de conversa','conversation'=>$conversation,'messages'=>$this->db->all('SELECT m.*,u.name AS sender_name FROM messages m LEFT JOIN users u ON u.id=m.sender_id WHERE m.conversation_id=? ORDER BY m.created_at,m.id',[$match[1]])]);
        }
        if ($path === '/admin') {
            $this->requireUser(); $this->catalog->admin($this->user);
            return $this->view('admin/dashboard',['title'=>'Administração','metrics'=>['Contas ativas'=>(int)$this->db->one('SELECT COUNT(*) AS total FROM users WHERE active=1')['total'],'Estabelecimentos aguardando aprovação'=>(int)$this->db->one("SELECT COUNT(*) AS total FROM businesses WHERE status='PENDING'")['total'],'Solicitações aguardando parceiro'=>(int)$this->db->one("SELECT COUNT(*) AS total FROM bookings WHERE status='PENDING_PARTNER_APPROVAL'")['total'],'Atendimentos concluídos'=>(int)$this->db->one("SELECT COUNT(*) AS total FROM bookings WHERE status='COMPLETED'")['total']],'businesses'=>$this->db->all('SELECT * FROM businesses ORDER BY name'),'categories'=>$this->db->all('SELECT * FROM categories ORDER BY sort_order,name'),'reports'=>$this->db->all('SELECT * FROM reports ORDER BY created_at DESC LIMIT 100'),'audit'=>$this->db->all('SELECT a.*,u.name FROM audit_events a JOIN users u ON u.id=a.actor_id ORDER BY a.created_at DESC LIMIT 100'),'reviews'=>$this->db->all('SELECT * FROM reviews ORDER BY created_at DESC LIMIT 100'),'conversations'=>$this->db->all('SELECT c.*,b.service_name FROM conversations c JOIN bookings b ON b.id=c.booking_id ORDER BY b.created_at DESC LIMIT 100')]);
        }
        throw new Problem('Página não encontrada.',404);
    }
    private function action(string $action): Response
    {
        $data = $_POST;
        foreach ($data as $key=>$value) {
            if (in_array($key,['weekdays','professionals'],true)) {
                if (!is_array($value) || count($value)>100 || array_filter($value,fn($item)=>!is_string($item))) { throw new Problem('Seleção inválida.'); }
            } elseif (!is_string($value) || strlen($value)>12000) { throw new Problem('Dados do formulário inválidos.'); }
        }
        if (!is_string($data['csrf'] ?? null) || !hash_equals($_SESSION['csrf'], $data['csrf'])) { throw new Problem('A sessão do formulário expirou. Recarregue a página.',419); }
        if (is_string($data['slot'] ?? null) && str_contains($data['slot'], '|')) {
            [$data['professional_id'], $data['starts_at']] = explode('|', $data['slot'], 2);
        }
        $return = is_string($data['return'] ?? null) ? $data['return'] : '';
        if (!preg_match('~^/(?!/)[a-zA-Z0-9/_?=&%.-]*$~D',$return)) { $return = '/perfil'; }
        if ($action === 'setup') {
            $token = Input::text($data,'setup_token',64,64);
            $this->setupToken($token);
            $user = $this->db->transaction(function () use ($data,$token): array {
                if (!$this->db->sqlite) { $this->db->one('SELECT pg_advisory_xact_lock(147502923)'); }
                $this->setupToken($token);
                $user = $this->identity->register($data,'web-setup');
                $this->db->execute("UPDATE users SET role='ADMIN' WHERE id=?",[$user['id']]);
                return $user;
            });
            unlink(Database::storage() . '/setup.token');
            $this->signIn($user); $return = '/admin';
        } elseif ($action === 'register') {
            $this->identity->limit('register:' . ($_SERVER['REMOTE_ADDR'] ?? ''),10,900);
            $this->signIn($this->identity->register($data,'web'));
            $return = '/perfil';
        } elseif ($action === 'login') {
            $this->signIn($this->identity->login($data,$_SERVER['REMOTE_ADDR'] ?? 'local'));
            $return = '/perfil';
        } elseif ($action === 'forgot') {
            $this->identity->resetRequest($data,$_SERVER['REMOTE_ADDR'] ?? 'local');
            $_SESSION['flash'] = 'Se houver uma conta, enviaremos instruções para recuperar o acesso.';
            return $this->redirect('/recuperar');
        } elseif ($action === 'reset') {
            $this->identity->reset($data); $return = '/entrar';
        } elseif ($action === 'google') {
            $_SESSION['google_consent'] = ($data['consent'] ?? '') === '1';
            return $this->redirect((new GoogleAuth($this->db))->start());
        } elseif ($action === 'compare') {
            $id = Input::text($data,'service_id');
            $ids = $_SESSION['compare'] ?? [];
            if (in_array($id,$ids,true)) { $ids = array_values(array_diff($ids,[$id])); }
            else {
                $service = $this->catalog->publicService($id);
                if (count($ids) >= 3) { throw new Problem('Compare no máximo três serviços.'); }
                if ($ids && $this->catalog->publicService($ids[0])['category_id'] !== $service['category_id']) { throw new Problem('Compare serviços da mesma categoria.'); }
                $ids[] = $id;
            }
            $_SESSION['compare'] = $ids;
        } else {
            $user = $this->requireUser();
            $this->identity->limit('action:' . $user['id'],100,60);
            switch ($action) {
                case 'logout':
                    $_SESSION = []; session_regenerate_id(true); $_SESSION['csrf'] = bin2hex(random_bytes(32)); $return = '/'; break;
                case 'profile': $this->identity->update($user,$data); break;
                case 'delete-account':
                    if (($data['confirm'] ?? '') !== 'EXCLUIR') { throw new Problem('Digite EXCLUIR para confirmar.'); }
                    $this->identity->delete($user); $_SESSION = ['csrf'=>bin2hex(random_bytes(32))]; session_regenerate_id(true); $return = '/'; break;
                case 'business-create': $id = $this->catalog->createBusiness($user,$data); $return = '/parceiro?business=' . $id; break;
                case 'business-save': $this->catalog->updateBusiness($user,$data); break;
                case 'professional-save': $this->catalog->saveProfessional($user,$data); break;
                case 'service-save': $this->catalog->saveService($user,$data); break;
                case 'promotion': $this->catalog->promotion($user,$data); break;
                case 'category': $this->operations->category($user,$data); break;
                case 'staff': $this->operations->staff($user,$data); break;
                case 'block': $this->operations->block($user,$data); break;
                case 'upload': $this->operations->upload($user,$data,$_FILES['image'] ?? []); break;
                case 'favorite': $this->community->favorite($user,Input::text($data,'business_id')); break;
                case 'booking': $this->bookings->request($user,$data); $return = '/agendamentos'; break;
                case 'transition': $this->bookings->transition($user,Input::text($data,'booking_id'),Input::text($data,'status'),Input::text($data,'reason',0,1000)); break;
                case 'reschedule': $this->bookings->reschedule($user,Input::text($data,'booking_id'),$data); break;
                case 'review': $this->community->review($user,$data); break;
                case 'message': $this->community->message($user,$data); break;
                case 'report': $this->community->report($user,$data); break;
                case 'moderate': $this->community->moderate($user,$data); break;
                case 'notifications-read': $this->db->execute('UPDATE notifications SET read_at=? WHERE user_id=?',[Database::now(),$user['id']]); break;
                default: throw new Problem('Ação não encontrada.',404);
            }
        }
        $_SESSION['flash'] = 'Operação realizada com sucesso.';
        return $this->redirect($return);
    }
    private function signIn(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['session_version'] = (int)$user['session_version'];
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    private function setupToken(string $token): void
    {
        $file = Database::storage() . '/setup.token';
        if ($this->db->one("SELECT id FROM users WHERE role='ADMIN'") || !is_file($file) || !hash_equals(trim(file_get_contents($file)),$token)) {
            throw new Problem('Instalação indisponível. Use o endereço privado gerado por php bin/console.php install.',403);
        }
    }
    private function requireUser(): array
    {
        if (!$this->user) { throw new Problem('Entre na sua conta para continuar.',401); }
        return $this->user;
    }
    public function view(string $template, array $page, int $status = 200): Response
    {
        $user = $this->user;
        $db = $this->db;
        $catalog = $this->catalog;
        $bookingService = $this->bookings;
        $flash = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        ob_start(); require dirname(__DIR__,2) . '/templates/pages/' . $template . '.php'; $content = (string)ob_get_clean();
        ob_start(); require dirname(__DIR__,2) . '/templates/layouts/main.php'; $body = (string)ob_get_clean();
        return new Response($status,$body);
    }
    private function redirect(string $url): Response { return new Response(303,'',['Location'=>$url]); }
}
