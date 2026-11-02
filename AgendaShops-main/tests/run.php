<?php
declare(strict_types=1);
require dirname(__DIR__).'/config/bootstrap.php';
if(PHP_SAPI!=='cli')exit;
$database='agendashops_test_'.bin2hex(random_bytes(5));
$admin=new PDO('mysql:host='.env('DB_HOST','127.0.0.1').';port='.env('DB_PORT','3306').';charset=utf8mb4',env('DB_USERNAME','root'),env('DB_PASSWORD'),[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$admin->exec("CREATE DATABASE `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$_ENV['DB_DATABASE']=$database;putenv('DB_DATABASE='.$database);
$passed=0;
function check(bool $value,string $label): void { global $passed;if(!$value)throw new RuntimeException('FALHOU: '.$label);echo 'OK · '.$label.PHP_EOL;$passed++; }
function fails(callable $fn,string $label): void { try{$fn();}catch(Throwable){check(true,$label);return;}check(false,$label); }
function available(array $slots,string $time,int $p=0): bool {foreach($slots as $s)if($s['time']===$time && (!$p || $s['professional_id']===$p))return true;return false;}
try {
 db()->exec(file_get_contents(ROOT.'/database/database.sql'));
 foreach([1,2] as $t){query('INSERT INTO establishments(id,name,slug,category,phone) VALUES (?,?,?,?,?)',[$t,'Empresa '.$t,'empresa-'.$t,'Teste','11999999999']);query('INSERT INTO establishment_settings(establishment_id,min_notice,max_days,slot_step,message_template) VALUES (?,0,60,15,?)',[$t,'Olá {cliente}']);for($d=0;$d<7;$d++)query('INSERT INTO business_hours VALUES (?,?,?,?,0)',[$t,$d,'08:00','18:00']);}
 foreach([[1,1],[2,1],[3,2]] as [$id,$t]){query('INSERT INTO professionals(id,establishment_id,name) VALUES (?,?,?)',[$id,$t,'Profissional '.$id]);}
 foreach([[1,1,30],[2,1,60],[3,2,30]] as [$id,$t,$duration])query('INSERT INTO services(id,establishment_id,name,price,duration) VALUES (?,?,?,?,?)',[$id,$t,'Serviço '.$id,50,$duration]);
 foreach([[1,1,1],[1,1,2],[1,2,1],[1,2,2],[2,3,3]] as $row)query('INSERT INTO professional_services VALUES (?,?,?)',$row);
 query("INSERT INTO users(id,establishment_id,name,email,password,role) VALUES (1,1,'Admin','admin@test.local',?,'admin')",[password_hash('testpassword',PASSWORD_DEFAULT)]);
 $actor=query('SELECT * FROM users WHERE id=1')->fetch();$_SESSION=['uid'=>1];$date=date('Y-m-d',strtotime('tomorrow'));$weekday=(int)date('w',strtotime($date));
 query('INSERT INTO breaks(establishment_id,weekday,starts,ends) VALUES (?,?,?,?)',[1,$weekday,'12:00','13:00']);
 $av=new app\Services\Availability;$book=new app\Services\Booking;
 $slots=$av->slots(1,2,$date,1);
 check(!available($slots,'17:15'),'serviço não ultrapassa fechamento');check(!available($slots,'11:30'),'serviço não atravessa almoço');check(available($slots,'11:00'),'serviço termina no início do intervalo');
 fails(fn()=>$av->slots(1,3,$date,1),'serviço de outro estabelecimento rejeitado');check($av->slots(1,1,$date,3)===[],'profissional de outro estabelecimento isolado');
 fails(fn()=>query('INSERT INTO professional_services VALUES (?,?,?)',[1,1,3]),'chave estrangeira impede vínculo entre empresas');
 $client=['name'=>'Cliente','phone'=>'11911111111','email'=>''];$a=$book->save(1,1,1,$date,'09:00',$client);
 fails(fn()=>$book->save(1,2,1,$date,'08:30',$client),'durações diferentes não sobrepõem');fails(fn()=>$book->save(1,1,1,$date,'09:00',$client),'duplicidade recusada');
 $b=$book->save(1,1,2,$date,'09:00',$client);check($b['id']!==$a['id'],'profissionais diferentes atendem simultaneamente');
 check(!available($av->slots(1,2,$date,1),'08:30'),'grade omite conflito parcial');
 fails(fn()=>$book->save(1,1,3,$date,'10:00',$client),'reserva com profissional externo recusada');
 fails(fn()=>$book->save(2,3,3,$date,'10:00',$client,$actor,$a['id']),'reagendamento não acessa outra empresa');
 fails(fn()=>$book->save(1,2,1,$date,'11:30',$client,$actor,$a['id']),'reagendamento revalida almoço');
 $book->save(1,1,1,$date,'10:00',$client,$actor,$a['id']);check(available($av->slots(1,1,$date,1),'09:00'),'reagendamento libera horário anterior');
 fails(fn()=>$book->status(2,$b['id'],'cancelled',$actor),'ator de outra empresa rejeitado no serviço');
 $book->status(1,$a['id'],'cancelled',$actor);check(available($av->slots(1,1,$date,1),'10:00'),'cancelamento libera horário');
 query('UPDATE establishment_settings SET cancelled_releases=0 WHERE establishment_id=1');check(!available($av->slots(1,1,$date,1),'10:00'),'política pode preservar bloqueio cancelado');query('UPDATE establishment_settings SET cancelled_releases=1 WHERE establishment_id=1');
 fails(fn()=>$book->status(1,$a['id'],'confirmed',$actor),'cancelamento não pode reativar conflito');
 query('INSERT INTO schedule_exceptions(establishment_id,professional_id,date,reason) VALUES (1,1,?,?)',[$date,'Folga']);check($av->slots(1,1,$date,1)===[],'folga individual bloqueia');check(available($av->slots(1,1,$date,2),'10:00'),'folga individual preserva outro profissional');query('DELETE FROM schedule_exceptions');
 query('INSERT INTO schedule_exceptions(establishment_id,date,reason) VALUES (1,?,?)',[$date,'Feriado']);check($av->slots(1,1,$date)===[],'feriado bloqueia estabelecimento');query('DELETE FROM schedule_exceptions');
 check(!available($av->slots(1,1,date('Y-m-d'),1),'00:00'),'horário passado hoje recusado');
 check($av->slots(1,1,date('Y-m-d',strtotime('yesterday')))===[],'datas passadas recusadas');check($av->slots(1,1,date('Y-m-d',strtotime('+61 days')))===[],'limite futuro respeitado');
 query('UPDATE services SET buffer=15 WHERE id=1');$c=$book->save(1,1,1,$date,'14:00',$client);check(!available($av->slots(1,1,$date,1),'14:30'),'tempo adicional bloqueia após serviço');check(available($av->slots(1,1,$date,1),'14:45'),'horário após buffer liberado');
 query('INSERT INTO professional_working_hours VALUES (1,1,?,?,?,0)',[$weekday,'10:00','16:00']);check(!available($av->slots(1,1,$date,1),'08:00'),'expediente individual restringe estabelecimento');query('DELETE FROM professional_working_hours');
 query('UPDATE establishments SET active=0 WHERE id=2');fails(fn()=>$book->save(2,3,3,$date,'10:00',$client),'estabelecimento bloqueado não recebe reservas');
 $processes=[];for($i=0;$i<2;$i++){$cmd=[PHP_BINARY,ROOT.'/tests/booking-worker.php',$database,'1','2','1',$date,'15:00','1190000000'.$i];$pipes=[];$proc=proc_open($cmd,[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes,ROOT);fclose($pipes[0]);$processes[]=[$proc,$pipes];}
 $out=[];foreach($processes as [$proc,$pipes]){$out[]=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);proc_close($proc);if($err)echo $err;}sort($out);check($out===['CONFLICT','OK'],'duas conexões concorrentes: exatamente uma reserva');
 check((int)query('SELECT COUNT(*) FROM appointments WHERE establishment_id=2')->fetchColumn()===0,'dados da segunda empresa preservados');
 echo "\n$passed testes aprovados.\n";
}finally{if(db()->inTransaction())db()->rollBack();$admin->exec("DROP DATABASE `$database`");}
