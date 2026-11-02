<?php
declare(strict_types=1);
define('PUBLIC_ROOT', __DIR__);
$bootstrap=dirname(__DIR__).'/agendashops-private/config/bootstrap.php';
if(!is_file($bootstrap)) $bootstrap=dirname(__DIR__).'/config/bootstrap.php';
require $bootstrap;
ini_set('session.use_strict_mode','1');
$sessionDir=ROOT.'/storage/sessions'; if(!is_dir($sessionDir)) mkdir($sessionDir,0700,true); session_save_path($sessionDir);
session_set_cookie_params(['httponly'=>true,'secure'=>env('SESSION_SECURE')==='1','samesite'=>'Lax','path'=>'/']); session_start();
header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; script-src 'self'; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
header('Cache-Control: no-store');
$path=trim(parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH),'/');$base=trim(parse_url(env('APP_URL'),PHP_URL_PATH)??'','/');
if($base && ($path===$base || str_starts_with($path,$base.'/'))) $path=trim(substr($path,strlen($base)),'/');
// Opening the front controller directly is equivalent to opening the home page.
if($path==='index.php') $path='';
$json=str_starts_with($path,'api/');
try {
 if($_SERVER['REQUEST_METHOD']==='POST') csrf_check();
 if($path==='sair' && $_SERVER['REQUEST_METHOD']==='POST') { $_SESSION=[];session_regenerate_id(true); go('entrar'); }
 if(in_array($path,['entrar','cadastrar'],true)) {
  if($_SERVER['REQUEST_METHOD']==='POST') {
   rate_limit($path, $path==='entrar'?10:5);
   $email=email_value('email');
   if($path==='entrar') {
    $u=query('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
    if(!$u || !password_verify(required('password',200),$u['password'])) throw new RuntimeException('E-mail ou senha incorretos.',422);
    session_regenerate_id(true); $_SESSION['uid']=$u['id']; $_SESSION['csrf']=bin2hex(random_bytes(32)); auth(); go($u['role']==='superadmin'?'plataforma':'painel');
   }
   $name=required('establishment');$owner=required('name');$phone=phone_value();$hash=password_value();$category=required('category',100);
   if(!isset($_POST['privacy'])) throw new RuntimeException('Leia e aceite o aviso de privacidade.',422);
   $slug=trim(preg_replace('/[^a-z0-9]+/','-',strtolower(iconv('UTF-8','ASCII//TRANSLIT',$name))),'-')?:'agenda'; $slug=substr($slug,0,140).'-'.bin2hex(random_bytes(4));
   db()->beginTransaction();try {
    query('INSERT INTO establishments(name,slug,category,phone,address) VALUES (?,?,?,?,?)',[$name,$slug,$category,$phone,trim($_POST['address']??'')]);$t=(int)db()->lastInsertId();
    query('INSERT INTO establishment_settings(establishment_id,message_template) VALUES (?,?)',[$t,'Olá, {cliente}! Seu agendamento de {servico} no {estabelecimento} está {status} para {data} às {hora}.']);
    query("INSERT INTO users(establishment_id,name,email,password,role) VALUES (?,?,?,?,'admin')",[$t,$owner,$email,$hash]);$uid=(int)db()->lastInsertId();
    for($d=0;$d<7;$d++) query('INSERT INTO business_hours VALUES (?,?,?,?,?)',[$t,$d,'08:00','18:00',$d===0?1:0]);
    db()->commit();
   }catch(Throwable $e){db()->rollBack();throw $e;}
   session_regenerate_id(true);$_SESSION['uid']=$uid;$_SESSION['csrf']=bin2hex(random_bytes(32));go('painel');
  }
  view('auth',['register'=>$path==='cadastrar']);exit;
 }
 if(preg_match('~^api/(disponibilidade|reservar)/([a-z0-9-]+)$~D',$path,$m)) {
  rate_limit('public-'.$m[1],$m[1]==='reservar'?12:120,300);
  $est=query('SELECT * FROM establishments WHERE slug=? AND active=1',[$m[2]])->fetch();if(!$est) throw new RuntimeException('Estabelecimento não encontrado.',404);
  if($m[1]==='disponibilidade') { if($_SERVER['REQUEST_METHOD']!=='GET') throw new RuntimeException('Método inválido.',405); $data=(new app\Services\Availability)->slots((int)$est['id'],(int)($_GET['service']??0),(string)($_GET['date']??''),(int)($_GET['professional']??0)); }
  else { if($_SERVER['REQUEST_METHOD']!=='POST') throw new RuntimeException('Método inválido.',405);if(!isset($_POST['privacy'])) throw new RuntimeException('Aceite o aviso de privacidade.',422);$data=(new app\Services\Booking)->save((int)$est['id'],(int)($_POST['service']??0),(int)($_POST['professional']??0),required('date'),required('time'),['name'=>required('name'),'phone'=>phone_value(),'email'=>email_value('email',true)]);$data=['redirect'=>url('confirmacao/'.$data['token'])]; }
  header('Content-Type: application/json; charset=utf-8');echo json_encode($data);exit;
 }
 if(preg_match('~^agendar/([a-z0-9-]+)$~D',$path,$m)) {
  $est=query('SELECT * FROM establishments WHERE slug=? AND active=1',[$m[1]])->fetch();if(!$est) throw new RuntimeException('Estabelecimento não encontrado.',404);
  view('booking',['est'=>$est,'services'=>query('SELECT * FROM services WHERE establishment_id=? AND active=1 AND deleted_at IS NULL',[$est['id']])->fetchAll(),'professionals'=>query('SELECT p.id,p.name,ps.service_id FROM professionals p JOIN professional_services ps ON ps.establishment_id=p.establishment_id AND ps.professional_id=p.id WHERE p.establishment_id=? AND p.active=1 AND p.deleted_at IS NULL',[$est['id']])->fetchAll(),'settings'=>query('SELECT * FROM establishment_settings WHERE establishment_id=?',[$est['id']])->fetch()]);exit;
 }
 if(preg_match('~^(confirmacao|calendario)/([a-f0-9]{64})$~D',$path,$m)) {
  $a=query('SELECT a.*,c.name client,c.phone,s.name service,p.name professional,e.name establishment,e.address FROM appointments a JOIN clients c ON c.id=a.client_id JOIN services s ON s.id=a.service_id JOIN professionals p ON p.id=a.professional_id JOIN establishments e ON e.id=a.establishment_id WHERE a.token=?',[$m[2]])->fetch();if(!$a) throw new RuntimeException('Reserva não encontrada.',404);
  if($m[1]==='calendario') {
   $icsEscape=fn($v)=>str_replace(["\\",";",",","\r","\n"],["\\\\","\\;","\\,","","\\n"],$v);
   header('Content-Type: text/calendar; charset=utf-8');header('Content-Disposition: attachment; filename="agendamento.ics"');
   echo "BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//AgendaShops//PT-BR\r\nBEGIN:VEVENT\r\nUID:".$a['token']."@agendashops\r\nDTSTAMP:".gmdate('Ymd\THis\Z')."\r\nDTSTART:".gmdate('Ymd\THis\Z',strtotime($a['starts_at']))."\r\nDTEND:".gmdate('Ymd\THis\Z',strtotime($a['ends_at']))."\r\nSUMMARY:".$icsEscape($a['service'].' - '.$a['establishment'])."\r\nLOCATION:".$icsEscape($a['address'])."\r\nEND:VEVENT\r\nEND:VCALENDAR\r\n";exit;
  } view('confirmation',['a'=>$a]);exit;
 }
 if($path==='plataforma') {
  $u=auth();if($u['role']!=='superadmin') throw new RuntimeException('Acesso restrito.',403);
  if($_SERVER['REQUEST_METHOD']==='POST') { query('UPDATE establishments SET active=? WHERE id=?',[(int)($_POST['active']??0)===1?1:0,(int)($_POST['id']??0)]);go('plataforma'); }
  view('platform',['u'=>$u,'establishments'=>query('SELECT e.*,(SELECT COUNT(*) FROM appointments a WHERE a.establishment_id=e.id) bookings FROM establishments e ORDER BY e.created_at DESC')->fetchAll()]);exit;
 }
 if($path==='painel' || str_starts_with($path,'painel/')) {
  $u=auth();$t=tenant();$section=substr($path,7)?:'dashboard';
  if(!in_array($section,['dashboard','agenda','agendamentos','servicos','profissionais','horarios','clientes','configuracoes'],true)) throw new RuntimeException('Página não encontrada.',404);
  if($u['role']==='professional' && !in_array($section,['dashboard','agenda','agendamentos'],true)) throw new RuntimeException('Acesso restrito.',403);
  if($_SERVER['REQUEST_METHOD']==='POST') {
   $action=required('action');
   if($action==='status') (new app\Services\Booking)->status($t,(int)($_POST['id']??0),required('status'),$u);
   elseif($action==='booking') (new app\Services\Booking)->save($t,(int)($_POST['service']??0),(int)($_POST['professional']??0),required('date'),required('time'),['name'=>required('name'),'phone'=>phone_value(),'email'=>email_value('email',true)],$u,(int)($_POST['reschedule']??0));
   else (new app\Services\Management)->save($t,$action);
   $_SESSION['flash']='Alterações salvas com sucesso.';go($path);
  }
  view('admin',['u'=>$u,'t'=>$t,'section'=>$section]);exit;
 }
 if($path==='privacidade') {view('privacy');exit;}
 if($path==='') {view('landing');exit;}
 throw new RuntimeException('Página não encontrada.',404);
} catch(Throwable $ex) {
 if(db_safe_transaction()) db()->rollBack();
 $code=$ex instanceof RuntimeException && $ex->getCode()>=400 && $ex->getCode()<=599?$ex->getCode():500;http_response_code($code);
 $message=$code===500?'Não foi possível concluir a operação. Verifique a configuração ou tente novamente.':$ex->getMessage();
 if($ex instanceof PDOException && $ex->getCode()==='23000') { $message='Este e-mail já está cadastrado ou os dados estão em conflito.';http_response_code(422); }
 error_log(get_class($ex).': '.$ex->getMessage());
 if($json) {header('Content-Type: application/json; charset=utf-8');echo json_encode(['error'=>$message]);} else view('error',['message'=>$message]);
}
function db_safe_transaction(): bool { try {return db()->inTransaction();}catch(Throwable){return false;} }
