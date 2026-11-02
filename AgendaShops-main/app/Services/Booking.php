<?php
namespace app\Services;
use RuntimeException;
use Throwable;
final class Booking {
 public function save(int $tenant,int $service,int $professional,string $date,string $time,array $client,?array $actor=null,int $reschedule=0): array {
  if($actor && ((int)$actor['establishment_id']!==$tenant || !in_array($actor['role'],['admin','professional'],true))) throw new RuntimeException('Acesso restrito.',403);
  if(!preg_match('/^\d{2}:\d{2}$/D',$time)) throw new RuntimeException('Horário inválido.',422);
  $db=\db(); $db->exec('SET TRANSACTION ISOLATION LEVEL READ COMMITTED'); $db->beginTransaction();
  try {
   // All booking and schedule mutations serialize through this tenant row.
   $est=\query('SELECT * FROM establishments WHERE id=? FOR UPDATE',[$tenant])->fetch();
   if(!$est || !$est['active']) throw new RuntimeException('Estabelecimento indisponível.',403);
   $old=null;
   if($reschedule) {
    $old=\query('SELECT * FROM appointments WHERE establishment_id=? AND id=? FOR UPDATE',[$tenant,$reschedule])->fetch();
    if(!$old || !$actor || ($actor['role']==='professional' && (int)$old['professional_id']!==(int)$actor['professional_id'])) throw new RuntimeException('Reserva não encontrada.',404);
    if(!in_array($old['status'],['pending','confirmed'],true)) throw new RuntimeException('Somente reservas ativas podem ser reagendadas.',422);
   }
   if($actor && $actor['role']==='professional') $professional=(int)$actor['professional_id'];
   $slots=(new Availability)->slots($tenant,$service,$date,$professional,$reschedule);
   $selected=null; foreach($slots as $slot) if($slot['time']===$time) {$selected=$slot;break;}
   if(!$selected) throw new RuntimeException('Este horário não está mais disponível. Escolha outro.',409);
   $svc=\query('SELECT * FROM services WHERE establishment_id=? AND id=?',[$tenant,$service])->fetch();
   $start=$date.' '.$time.':00'; $end=date('Y-m-d H:i:s',strtotime($start)+(int)$svc['duration']*60); $busy=date('Y-m-d H:i:s',strtotime($end)+(int)$svc['buffer']*60);
   if($old) {
    \query('UPDATE appointments SET professional_id=?,service_id=?,starts_at=?,ends_at=?,busy_until=?,price=? WHERE establishment_id=? AND id=?',[$selected['professional_id'],$service,$start,$end,$busy,$svc['price'],$tenant,$reschedule]);
    $id=$reschedule; $token=$old['token']; $status=$old['status'];
   } else {
    // Do not let unauthenticated visitors overwrite existing client identity.
    \query('INSERT INTO clients(establishment_id,name,phone,email) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE id=LAST_INSERT_ID(id)',[$tenant,$client['name'],$client['phone'],$client['email']]);
    $cid=(int)$db->lastInsertId(); $token=bin2hex(random_bytes(32));
    $status=\query('SELECT auto_confirm FROM establishment_settings WHERE establishment_id=?',[$tenant])->fetchColumn()?'confirmed':'pending';
    \query('INSERT INTO appointments(establishment_id,client_id,professional_id,service_id,starts_at,ends_at,busy_until,price,status,token) VALUES (?,?,?,?,?,?,?,?,?,?)',[$tenant,$cid,$selected['professional_id'],$service,$start,$end,$busy,$svc['price'],$status,$token]); $id=(int)$db->lastInsertId();
   }
   \query('INSERT INTO appointment_status_history(establishment_id,appointment_id,status,user_id,note) VALUES (?,?,?,?,?)',[$tenant,$id,$status,$actor['id']??null,$old?'Reagendado':'Criado']);
   $db->commit(); return ['id'=>$id,'token'=>$token];
  } catch(Throwable $e) { if($db->inTransaction()) $db->rollBack(); throw $e; }
 }
 public function status(int $tenant,int $id,string $status,array $actor): void {
  if((int)$actor['establishment_id']!==$tenant || !in_array($actor['role'],['admin','professional'],true)) throw new RuntimeException('Acesso restrito.',403);
  if(!in_array($status,['pending','confirmed','completed','cancelled','no_show'],true)) throw new RuntimeException('Status inválido.',422);
  \db()->beginTransaction(); try {
   \query('SELECT id FROM establishments WHERE id=? FOR UPDATE',[$tenant]);
   $a=\query('SELECT * FROM appointments WHERE establishment_id=? AND id=? FOR UPDATE',[$tenant,$id])->fetch();
   if(!$a || ($actor['role']==='professional' && (int)$a['professional_id']!==(int)$actor['professional_id'])) throw new RuntimeException('Reserva não encontrada.',404);
   $allowed=['pending'=>['confirmed','cancelled','no_show'],'confirmed'=>['completed','cancelled','no_show'],'completed'=>[],'cancelled'=>[],'no_show'=>[]];
   if(!in_array($status,$allowed[$a['status']],true)) throw new RuntimeException('Transição de status não permitida.',422);
   \query('UPDATE appointments SET status=? WHERE establishment_id=? AND id=?',[$status,$tenant,$id]);
   \query('INSERT INTO appointment_status_history(establishment_id,appointment_id,status,user_id) VALUES (?,?,?,?)',[$tenant,$id,$status,$actor['id']]); \db()->commit();
  } catch(\Throwable $e) { \db()->rollBack(); throw $e; }
 }
}
