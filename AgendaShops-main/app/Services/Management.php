<?php
namespace app\Services;
use RuntimeException;
final class Management {
 public static function professional(int $t,int $id): void { if($id && !\query('SELECT id FROM professionals WHERE establishment_id=? AND id=?',[$t,$id])->fetch()) throw new RuntimeException('Profissional inválido.',422); }
 public static function hours(string $open,string $close): void { if(!preg_match('/^\d{2}:\d{2}$/D',$open) || !preg_match('/^\d{2}:\d{2}$/D',$close) || $open>=$close || $close>'23:59') throw new RuntimeException('Intervalo de horários inválido.',422); }
 public function save(int $t,string $action): void {
  \auth(true); \db()->beginTransaction();
  try {
   \query('SELECT id FROM establishments WHERE id=? FOR UPDATE',[$t]);
   $id=(int)($_POST['id']??0);
   switch($action) {
    case 'service':
     $name=\required('name'); $price=filter_var($_POST['price']??'',FILTER_VALIDATE_FLOAT); $duration=(int)($_POST['duration']??0); $buffer=(int)($_POST['buffer']??0);
     if($price===false || $price<0 || $price>99999999 || $duration<5 || $duration>720 || $buffer<0 || $buffer>240) throw new RuntimeException('Preço ou duração inválidos.',422);
     $vals=[$name,trim($_POST['description']??''),$price,$duration,$buffer,isset($_POST['active'])?1:0];
     if($id) { $this->exists('services',$t,$id); \query('UPDATE services SET name=?,description=?,price=?,duration=?,buffer=?,active=? WHERE establishment_id=? AND id=?',[...$vals,$t,$id]); }
     else { \query('INSERT INTO services(name,description,price,duration,buffer,active,establishment_id) VALUES (?,?,?,?,?,?,?)',[...$vals,$t]); $id=(int)\db()->lastInsertId(); }
     \query('DELETE FROM professional_services WHERE establishment_id=? AND service_id=?',[$t,$id]);
     foreach(array_unique((array)($_POST['professionals']??[])) as $pid) { self::professional($t,(int)$pid); if(!(int)$pid) continue; \query('INSERT INTO professional_services VALUES (?,?,?)',[$t,(int)$pid,$id]); }
     $image=$this->upload('image'); if($image) \query('UPDATE services SET image=? WHERE establishment_id=? AND id=?',[$image,$t,$id]);
     break;
    case 'professional':
     $vals=[\required('name'),trim($_POST['specialty']??''),isset($_POST['active'])?1:0];
     if($id) { $this->exists('professionals',$t,$id); \query('UPDATE professionals SET name=?,specialty=?,active=? WHERE establishment_id=? AND id=?',[...$vals,$t,$id]); }
     else { \query('INSERT INTO professionals(name,specialty,active,establishment_id) VALUES (?,?,?,?)',[...$vals,$t]); $id=(int)\db()->lastInsertId(); }
     if(!empty($_POST['email'])) {
      $email=\email_value('email'); $hash=\password_value();
      $existing=\query('SELECT id FROM users WHERE establishment_id=? AND professional_id=?',[$t,$id])->fetchColumn();
      if($existing) \query('UPDATE users SET email=?,password=?,name=?,active=? WHERE id=? AND establishment_id=?',[$email,$hash,$vals[0],$vals[2],$existing,$t]);
      else \query("INSERT INTO users(establishment_id,professional_id,name,email,password,role,active) VALUES (?,?,?,?,?,'professional',?)",[$t,$id,$vals[0],$email,$hash,$vals[2]]);
     }
     \query('UPDATE users SET active=? WHERE establishment_id=? AND professional_id=?',[$vals[2],$t,$id]); break;
    case 'delete_service': case 'delete_professional':
     $table=$action==='delete_service'?'services':'professionals'; $this->exists($table,$t,$id);
     \query("UPDATE $table SET active=0,deleted_at=NOW() WHERE establishment_id=? AND id=?",[$t,$id]);
     if($table==='professionals') \query('UPDATE users SET active=0 WHERE establishment_id=? AND professional_id=?',[$t,$id]); break;
    case 'hours':
     $pid=(int)($_POST['professional_id']??0); self::professional($t,$pid);
     for($d=0;$d<7;$d++) {
      $open=$_POST['opens'][$d]??'08:00'; $close=$_POST['closes'][$d]??'18:00'; self::hours($open,$close); $closed=isset($_POST['closed'][$d])?1:0;
      if($pid) \query('INSERT INTO professional_working_hours VALUES (?,?,?,?,?,?) ON DUPLICATE KEY UPDATE opens=VALUES(opens),closes=VALUES(closes),closed=VALUES(closed)',[$t,$pid,$d,$open,$close,$closed]);
      else \query('INSERT INTO business_hours VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE opens=VALUES(opens),closes=VALUES(closes),closed=VALUES(closed)',[$t,$d,$open,$close,$closed]);
     } break;
    case 'break':
     $pid=(int)($_POST['professional_id']??0); self::professional($t,$pid); $d=(int)($_POST['weekday']??-1); if($d<0 || $d>6) throw new RuntimeException('Dia inválido.',422);
     self::hours($_POST['starts']??'',$_POST['ends']??''); \query('INSERT INTO breaks(establishment_id,professional_id,weekday,starts,ends) VALUES (?,?,?,?,?)',[$t,$pid?:null,$d,$_POST['starts'],$_POST['ends']]); break;
    case 'exception':
     $pid=(int)($_POST['professional_id']??0); self::professional($t,$pid); $date=\required('date'); if(!Availability::validDate($date)) throw new RuntimeException('Data inválida.',422);
     $closed=isset($_POST['closed'])?1:0; if(!$closed) self::hours($_POST['opens']??'',$_POST['closes']??'');
     \query('INSERT INTO schedule_exceptions(establishment_id,professional_id,date,closed,opens,closes,reason) VALUES (?,?,?,?,?,?,?)',[$t,$pid?:null,$date,$closed,$closed?null:$_POST['opens'],$closed?null:$_POST['closes'],trim($_POST['reason']??'')]); break;
    case 'delete_break': case 'delete_exception':
     $table=$action==='delete_break'?'breaks':'schedule_exceptions'; \query("DELETE FROM $table WHERE establishment_id=? AND id=?",[$t,$id]); break;
    case 'settings':
     $notice=(int)($_POST['min_notice']??-1);$days=(int)($_POST['max_days']??0);$step=(int)($_POST['slot_step']??0);
     if($notice<0 || $notice>525600 || $days<1 || $days>365 || $step<5 || $step>120) throw new RuntimeException('Configuração de disponibilidade inválida.',422);
     \query('UPDATE establishment_settings SET min_notice=?,max_days=?,slot_step=?,auto_confirm=?,cancelled_releases=?,message_template=? WHERE establishment_id=?',[$notice,$days,$step,isset($_POST['auto_confirm'])?1:0,isset($_POST['cancelled_releases'])?1:0,\required('message_template',2000),$t]);
     \query('UPDATE establishments SET name=?,category=?,phone=?,address=?,description=? WHERE id=?',[\required('name'),\required('category',100),\phone_value(),trim($_POST['address']??''),trim($_POST['description']??''),$t]);
     $logo=$this->upload('logo'); if($logo) \query('UPDATE establishments SET logo=? WHERE id=?',[$logo,$t]); break;
    default: throw new RuntimeException('Operação inválida.',422);
   }
   \db()->commit();
  } catch(\Throwable $e) { if(\db()->inTransaction()) \db()->rollBack(); throw $e; }
 }
 private function exists(string $table,int $t,int $id): void { if(!\query("SELECT id FROM $table WHERE establishment_id=? AND id=? AND deleted_at IS NULL",[$t,$id])->fetch()) throw new RuntimeException('Registro não encontrado.',404); }
 private function upload(string $key): ?string {
  $f=$_FILES[$key]??null; if(!$f || $f['error']===UPLOAD_ERR_NO_FILE) return null;
  if($f['error']!==UPLOAD_ERR_OK || $f['size']>2097152) throw new RuntimeException('A imagem deve ter até 2 MB.',422);
  $info=getimagesize($f['tmp_name']); if(!$info || $info[0]>4096 || $info[1]>4096) throw new RuntimeException('Imagem inválida ou muito grande.',422);
  $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp']; $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']); if(!isset($types[$mime])) throw new RuntimeException('Use imagem JPG, PNG ou WebP.',422);
  $dir=(defined('PUBLIC_ROOT')?PUBLIC_ROOT:ROOT.'/public').'/uploads'; if(!is_dir($dir)) mkdir($dir,0755,true); $name=bin2hex(random_bytes(20)).'.'.$types[$mime];
  if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$name)) throw new RuntimeException('Não foi possível salvar a imagem.'); return 'uploads/'.$name;
 }
}
