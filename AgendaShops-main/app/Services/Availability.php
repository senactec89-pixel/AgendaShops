<?php
namespace app\Services;
use DateTimeImmutable;
use RuntimeException;
final class Availability {
 public static function validDate(string $date): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$date); return $d && $d->format('Y-m-d')===$date; }
 public static function overlaps(int $a,int $b,int $c,int $d): bool { return $a<$d && $b>$c; }
 public static function fits(int $start,int $end,int $open,int $close,array $blocked): bool {
  if($start<$open || $end>$close) return false;
  foreach($blocked as [$a,$b]) if(self::overlaps($start,$end,$a,$b)) return false;
  return true;
 }
 public function slots(int $tenant,int $service,string $date,int $professional=0,int $ignore=0): array {
  if(!self::validDate($date)) throw new RuntimeException('Data inválida.',422);
  $settings=\query('SELECT s.* FROM establishment_settings s JOIN establishments e ON e.id=s.establishment_id WHERE s.establishment_id=? AND e.active=1',[$tenant])->fetch();
  if(!$settings) throw new RuntimeException('Estabelecimento indisponível.',404);
  $day=strtotime($date); $today=strtotime('today');
  if($day<$today || $day>strtotime('+'.(int)$settings['max_days'].' days',$today)) return [];
  $svc=\query('SELECT * FROM services WHERE establishment_id=? AND id=? AND active=1 AND deleted_at IS NULL',[$tenant,$service])->fetch();
  if(!$svc) throw new RuntimeException('Serviço indisponível.',422);
  $pros=\query('SELECT p.* FROM professionals p JOIN professional_services ps ON ps.establishment_id=p.establishment_id AND ps.professional_id=p.id WHERE p.establishment_id=? AND ps.service_id=? AND p.active=1 AND p.deleted_at IS NULL'.($professional?' AND p.id=?':'').' ORDER BY p.id',$professional?[$tenant,$service,$professional]:[$tenant,$service])->fetchAll();
  $weekday=(int)date('w',$day); $business=\query('SELECT * FROM business_hours WHERE establishment_id=? AND weekday=?',[$tenant,$weekday])->fetch();
  $result=[];
  foreach($pros as $pro) {
   $own=\query('SELECT * FROM professional_working_hours WHERE establishment_id=? AND professional_id=? AND weekday=?',[$tenant,$pro['id'],$weekday])->fetch();
   $biz=$business; $ex=\query('SELECT * FROM schedule_exceptions WHERE establishment_id=? AND date=? AND (professional_id IS NULL OR professional_id=?) ORDER BY id',[$tenant,$date,$pro['id']])->fetchAll();
   // Multiple exceptions intersect; a closure always wins.
   $closed=false; $bizEx=[]; $ownEx=[];
   foreach($ex as $x) { if($x['closed']) {$closed=true;break;} if($x['professional_id']===null) $bizEx[]=$x; else $ownEx[]=$x; }
   if($closed) continue;
   if($bizEx) $biz=['opens'=>max(array_column($bizEx,'opens')),'closes'=>min(array_column($bizEx,'closes')),'closed'=>0];
   if($ownEx) $own=['opens'=>max(array_column($ownEx,'opens')),'closes'=>min(array_column($ownEx,'closes')),'closed'=>0];
   if(!$biz || $biz['closed'] || ($own && $own['closed'])) continue;
   $open=strtotime($date.' '.$biz['opens']); $close=strtotime($date.' '.$biz['closes']);
   if($own) { $open=max($open,strtotime($date.' '.$own['opens'])); $close=min($close,strtotime($date.' '.$own['closes'])); }
   $blocked=[];
   foreach(\query('SELECT starts,ends FROM breaks WHERE establishment_id=? AND weekday=? AND (professional_id IS NULL OR professional_id=?)',[$tenant,$weekday,$pro['id']]) as $b) $blocked[]=[strtotime($date.' '.$b['starts']),strtotime($date.' '.$b['ends'])];
   $sql='SELECT starts_at,busy_until FROM appointments WHERE establishment_id=? AND professional_id=? AND id<>? AND starts_at<? AND busy_until>?';
   if($settings['cancelled_releases']) $sql.=" AND status<>'cancelled'";
   foreach(\query($sql,[$tenant,$pro['id'],$ignore,$date.' 23:59:59',$date.' 00:00:00']) as $a) $blocked[]=[strtotime($a['starts_at']),strtotime($a['busy_until'])];
   $length=((int)$svc['duration']+(int)$svc['buffer'])*60;
   for($t=$open;$t+$length<=$close;$t+=(int)$settings['slot_step']*60) {
    if($t<time()+(int)$settings['min_notice']*60 || !self::fits($t,$t+$length,$open,$close,$blocked)) continue;
    $result[]=['time'=>date('H:i',$t),'professional_id'=>(int)$pro['id'],'professional'=>$pro['name']];
   }
  }
  usort($result,fn($a,$b)=>[$a['time'],$a['professional_id']]<=>[$b['time'],$b['professional_id']]); return $result;
 }
}
