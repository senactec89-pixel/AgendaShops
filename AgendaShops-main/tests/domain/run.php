<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/config/bootstrap.php';
use AgendaShops\Infrastructure\Database;
use AgendaShops\Modules\Identity\IdentityService;
use AgendaShops\Modules\Catalog\CatalogService;
use AgendaShops\Modules\Bookings\BookingService;
use AgendaShops\Modules\Community\CommunityService;
use AgendaShops\Modules\Operations\OperationsService;
use AgendaShops\Domain\Problem;

$checks=0;
function check(bool $condition,string $message): void { global $checks; $checks++; if(!$condition) throw new RuntimeException($message); }
function rejects(callable $work,int $status,string $message): void { try {$work();} catch(Problem $e) {check($e->status===$status,$message . ': status ' . $e->status);return;} throw new RuntimeException($message . ': unexpectedly allowed'); }
$path=dirname(__DIR__, 2) . '/storage/test-' . Database::id() . '.sqlite';
$dsn=getenv('TEST_DB_DSN') ?: 'sqlite:' . $path;
$db=new Database($dsn); $db->migrate();
$identity=new IdentityService($db); $catalog=new CatalogService($db); $bookings=new BookingService($db,$catalog); $community=new CommunityService($db,$catalog,$bookings); $operations=new OperationsService($db,$catalog);
function account(string $name,string $phone): array { global $identity; return $identity->register(['name'=>$name,'email'=>$name.'@example.test','phone'=>$phone,'password'=>'Test-password-123','password_confirmation'=>'Test-password-123','consent'=>'1'],'automated-test'); }
try {
$owner=account('owner','11999990001');$customer=account('customer','11999990002');$other=account('other','11999990003');$staff=account('staff','11999990004');$admin=account('admin','11999990005');$db->execute("UPDATE users SET role='ADMIN' WHERE id=?",[$admin['id']]);$admin['role']='ADMIN';
check(password_verify('Test-password-123',$customer['password_hash']),'Password hash verifies');
check($customer['password_hash']!=='Test-password-123','Password never stored as plain text');
check($customer['terms_version']==='2026-10-23' && $customer['consent_origin']==='automated-test','Consent version and origin stored');
rejects(fn()=>account('owner','11999990006'),422,'Duplicate email denied');
rejects(fn()=>account('duplicate','11999990002'),422,'Duplicate phone denied');
rejects(fn()=>$identity->register(['name'=>'No consent','consent'=>'0'],'test'),422,'Consent required');
check($identity->login(['email'=>'customer@example.test','password'=>'Test-password-123'],'test')['id']===$customer['id'],'Password login');
rejects(fn()=>$identity->login(['email'=>'customer@example.test','password'=>'wrong'],'test'),401,'Incorrect password');
for($i=0;$i<2;$i++) $identity->limit('test-limit',2,60);
rejects(fn()=>$identity->limit('test-limit',2,60),429,'Rate limiting enforced');
$operations->category($admin,['name'=>'Hair','sort_order'=>'0','active'=>'1']);$category=$catalog->categories()[0]['id'];
rejects(fn()=>$operations->category($customer,['name'=>'Fake','sort_order'=>'0']),403,'Customer cannot administer categories');
$business=$catalog->createBusiness($owner,['name'=>'Test Salon','description'=>'Testing beauty appointments','address'=>'Rua teste 123','city'=>'Sao Paulo','phone'=>'1133334444','timezone'=>'America/Sao_Paulo','policy'=>'Cancelamentos devem ser comunicados pelo chat.']);
check($catalog->search([])===[],'Unapproved business not public');
$community->moderate($admin,['type'=>'business','id'=>$business,'value'=>'APPROVED']);
$catalog->updateBusiness($owner,['business_id'=>$business,'name'=>'Test Salon','description'=>'Testing beauty appointments','address'=>'Rua teste 123','city'=>'Sao Paulo','phone'=>'1133334444','policy'=>'Cancelamentos devem ser comunicados pelo chat.','opens'=>'08:00','closes'=>'19:00','weekdays'=>['1','2','3','4','5','6','7'],'pending_minutes'=>'0']);
$professional=$catalog->saveProfessional($owner,['business_id'=>$business,'name'=>'Professional','opens'=>'09:00','closes'=>'18:00','weekdays'=>['1','2','3','4','5','6','7'],'active'=>'1']);
$service=$catalog->saveService($owner,['business_id'=>$business,'category_id'=>$category,'name'=>'Haircut','description'=>'Haircut service','duration_minutes'=>'60','price'=>'79,90','professionals'=>[$professional],'active'=>'1']);
check($catalog->publicService($service)['price_cents']===7990,'Decimal input persisted as integer cents');
check(count($catalog->search(['city'=>'Sao','q'=>'Hair']))===1,'Manual location and search');
check(count($catalog->search(['max_price'=>'50']))===0,'Price filter');
rejects(fn()=>$catalog->membership($other,$business),403,'Cross-business access denied');
rejects(fn()=>$catalog->saveProfessional($other,['business_id'=>$business]),403,'Cannot create professional in other business');
$operations->staff($owner,['business_id'=>$business,'email'=>$staff['email']]);
check($catalog->membership($staff,$business)['role']==='PARTNER_STAFF','Staff membership');
rejects(fn()=>$catalog->membership($staff,$business,true),403,'Staff cannot perform owner actions');
$tomorrow=(new DateTimeImmutable('tomorrow',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
$slots=$bookings->slots($service,$tomorrow);
check(count($slots)===33,'Slots respect professional shift and service duration');
check($slots[0]['time']==='09:00' && end($slots)['time']==='17:00','No slot extends past shift');
rejects(fn()=>$bookings->slots($service,'2030-02-30'),422,'Invalid date is rejected');
$operations->block($owner,['professional_id'=>$professional,'starts_at'=>$tomorrow.'T12:00','ends_at'=>$tomorrow.'T13:00','reason'=>'Lunch break']);
$slots=$bookings->slots($service,$tomorrow);
check(!in_array('11:15',array_column($slots,'time'),true) && !in_array('12:00',array_column($slots,'time'),true),'Slots crossing lunch excluded');
$key=Database::id();$request=['service_id'=>$service,'professional_id'=>$professional,'starts_at'=>$tomorrow.'T10:00','request_key'=>$key];
$first=$bookings->request($customer,$request);$second=$bookings->request($other,array_merge($request,['request_key'=>Database::id()]));
check($db->one('SELECT status FROM bookings WHERE id=?',[$first])['status']==='PENDING_PARTNER_APPROVAL','Request begins pending');
check($bookings->request($customer,$request)===$first,'Request creation idempotent');
rejects(fn()=>$bookings->request($customer,array_merge($request,['starts_at'=>$tomorrow.'T14:00'])),409,'Idempotency payload cannot change');
rejects(fn()=>$bookings->transition($customer,$first,'CONFIRMED',''),403,'Customer cannot approve own booking');
rejects(fn()=>$bookings->transition($other,$first,'CONFIRMED',''),403,'Other customer cannot access booking');
rejects(fn()=>$community->review($customer,['booking_id'=>$first,'rating'=>'5','body'=>'Great']),403,'Cannot review pending attendance');
$conversation=$db->one('SELECT id FROM conversations WHERE booking_id=?',[$first])['id'];
$community->message($customer,['conversation_id'=>$conversation,'body'=>'Hello partner']);
check(count($community->read($owner,$conversation))===2,'Contextual chat plus system event');
rejects(fn()=>$community->read($other,$conversation),403,'Conversation authorization');
$community->moderate($admin,['type'=>'conversation','id'=>$conversation,'value'=>'1']);
rejects(fn()=>$community->message($customer,['conversation_id'=>$conversation,'body'=>'Blocked']),403,'Blocked chat prevents sending');
$community->moderate($admin,['type'=>'conversation','id'=>$conversation,'value'=>'0']);
$community->report($customer,['target_type'=>'conversation','target_id'=>$conversation,'reason'=>'Testing moderation report']);
check(count($db->all('SELECT * FROM reports'))===1,'Report stored');
$community->favorite($customer,$business);check(count($db->all('SELECT * FROM favorites'))===1,'Favorite added');$community->favorite($customer,$business);check(count($db->all('SELECT * FROM favorites'))===0,'Favorite removed');
$start=(new DateTimeImmutable('-1 hour',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i');$end=(new DateTimeImmutable('+1 day',new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d\TH:i');
$catalog->promotion($owner,['service_id'=>$service,'percent'=>'15','starts_at'=>$start,'ends_at'=>$end]);
check($catalog->publicService($service)['effective_price']===6792,'Promotion integer rounding');
rejects(fn()=>$catalog->promotion($owner,['service_id'=>$service,'percent'=>'101','starts_at'=>$start,'ends_at'=>$end]),422,'Invalid promotion rejected');
// Two independent processes race to accept overlapping pending requests.
$processes=[];
foreach([$first,$second] as $id) {
 $pipes=[];$proc=proc_open([PHP_BINARY,__DIR__.'/concurrency-worker.php',$dsn,$owner['id'],$id],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
 if(!is_resource($proc)) throw new RuntimeException('Cannot launch concurrency worker');fclose($pipes[0]);$processes[]=[$proc,$pipes];
}
$outcomes=[];foreach($processes as [$proc,$pipes]) { $outcomes[]=trim(stream_get_contents($pipes[1]));$errors=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);check(proc_close($proc)===0,'Worker finished: '.$errors); }
sort($outcomes);check($outcomes===['409','OK'],'Exactly one concurrent approval succeeds');
$confirmed=$db->one("SELECT * FROM bookings WHERE status='CONFIRMED'");$loser=$confirmed['id']===$first ? $second : $first;
$bookings->transition($owner,$confirmed['id'],'CONFIRMED','retry');
check(count($db->all("SELECT * FROM booking_history WHERE booking_id=? AND status='CONFIRMED'",[$confirmed['id']]))===1,'Approval retry does not duplicate history');
rejects(fn()=>$bookings->transition($owner,$confirmed['id'],'IN_PROGRESS',''),422,'Cannot start before appointment');
check($confirmed['price_cents']===6792 && $confirmed['professional_name']==='Professional','Confirmation snapshots commercial data');
$catalog->saveService($owner,['business_id'=>$business,'service_id'=>$service,'category_id'=>$category,'name'=>'Haircut updated','description'=>'Updated description','duration_minutes'=>'60','price'=>'100,00','professionals'=>[$professional],'active'=>'1']);
$persisted=$db->one('SELECT * FROM bookings WHERE id=?',[$confirmed['id']]);
check($persisted['price_cents']===6792 && $persisted['service_name']==='Haircut','Catalog edits do not rewrite confirmed snapshots');
rejects(fn()=>$operations->block($owner,['professional_id'=>$professional,'starts_at'=>$tomorrow.'T10:00','ends_at'=>$tomorrow.'T11:00','reason'=>'Invalid block']),409,'Cannot block confirmed time');
rejects(fn()=>$bookings->request($staff,array_merge($request,['request_key'=>Database::id()])),409,'Confirmed time cannot be requested');
rejects(fn()=>$identity->delete($customer),422,'Cannot delete with active appointments');
$winningUser=$confirmed['customer_id']===$customer['id'] ? $customer : $other;
$bookings->transition($winningUser,$confirmed['id'],'CANCELED_BY_CUSTOMER','Cancel');
$bookings->transition($staff,$loser,'CONFIRMED','Staff acceptance');
check($db->one('SELECT status FROM bookings WHERE id=?',[$loser])['status']==='CONFIRMED','Staff can operate schedule');
$losingUser=$loser===$first ? $customer : $other;
$replacement=$bookings->reschedule($losingUser,$loser,['professional_id'=>$professional,'starts_at'=>$tomorrow.'T14:00','request_key'=>Database::id()]);
check($db->one('SELECT status FROM bookings WHERE id=?',[$loser])['status']==='RESCHEDULED','Original retired atomically');
check($db->one('SELECT status FROM bookings WHERE id=?',[$replacement])['status']==='PENDING_PARTNER_APPROVAL','Reschedule requires new approval');
rejects(fn()=>$bookings->reschedule($losingUser,$replacement,['professional_id'=>$professional,'starts_at'=>$tomorrow.'T12:00','request_key'=>Database::id()]),409,'Invalid reschedule rejected');
check($db->one('SELECT status FROM bookings WHERE id=?',[$replacement])['status']==='PENDING_PARTNER_APPROVAL','Failed reschedule preserves original');
// Simulate passage of time for lifecycle testing, without changing production clock.
$db->execute('UPDATE bookings SET starts_at=?,ends_at=? WHERE id=?',[(new DateTimeImmutable('-2 hours'))->format('Y-m-d\TH:i:s.u\Z'),(new DateTimeImmutable('-1 hour'))->format('Y-m-d\TH:i:s.u\Z'),$replacement]);
$db->execute("UPDATE bookings SET status='CONFIRMED' WHERE id=?",[$replacement]);
$bookings->transition($owner,$replacement,'IN_PROGRESS','Started');$bookings->transition($owner,$replacement,'COMPLETED','Completed');
$community->review($losingUser,['booking_id'=>$replacement,'rating'=>'5','body'=>'Excellent appointment']);
check(count($db->all('SELECT * FROM reviews'))===1,'Completed attendance can be reviewed');
rejects(fn()=>$community->review($losingUser,['booking_id'=>$replacement,'rating'=>'4','body'=>'Duplicate']),422,'Only one review per attendance');
$expired=$bookings->request($customer,['service_id'=>$service,'professional_id'=>$professional,'starts_at'=>$tomorrow.'T15:00','request_key'=>Database::id()]);
$db->execute('UPDATE bookings SET expires_at=? WHERE id=?',[(new DateTimeImmutable('-1 minute'))->format('Y-m-d\TH:i:s.u\Z'),$expired]);$bookings->expire();
check($db->one('SELECT status FROM bookings WHERE id=?',[$expired])['status']==='EXPIRED','Configured expiry transitions and logs');
$identity->delete($staff);check((int)$db->one('SELECT active FROM users WHERE id=?',[$staff['id']])['active']===0,'Account anonymization');
check(count($db->all('SELECT * FROM notifications'))>0,'Transactional notifications persisted');
check(count($db->all('SELECT * FROM audit_events'))>0,'Critical operations audited');
$rejected=$bookings->request($customer,['service_id'=>$service,'professional_id'=>$professional,'starts_at'=>$tomorrow.'T16:00','request_key'=>Database::id()]);
$bookings->transition($owner,$rejected,'REJECTED_BY_PARTNER','No availability');
rejects(fn()=>$bookings->transition($owner,$rejected,'CONFIRMED','Invalid reversal'),409,'Rejected bookings cannot be silently confirmed');
$canceled=$bookings->request($customer,['service_id'=>$service,'professional_id'=>$professional,'starts_at'=>$tomorrow.'T16:00','request_key'=>Database::id()]);
$bookings->transition($owner,$canceled,'CONFIRMED','Confirm');
$bookings->transition($owner,$canceled,'CANCELED_BY_PARTNER','Unavailable');
check($db->one('SELECT status FROM bookings WHERE id=?',[$canceled])['status']==='CANCELED_BY_PARTNER','Partner cancellation');
$noShow=$bookings->request($customer,['service_id'=>$service,'professional_id'=>$professional,'starts_at'=>$tomorrow.'T16:00','request_key'=>Database::id()]);
$bookings->transition($owner,$noShow,'CONFIRMED','Confirm');
rejects(fn()=>$bookings->transition($owner,$noShow,'NO_SHOW','Too soon'),422,'No-show cannot precede appointment');
$db->execute('UPDATE bookings SET starts_at=?,ends_at=? WHERE id=?',[(new DateTimeImmutable('-2 hours'))->format('Y-m-d\TH:i:s.u\Z'),(new DateTimeImmutable('-1 hour'))->format('Y-m-d\TH:i:s.u\Z'),$noShow]);
$bookings->transition($owner,$noShow,'NO_SHOW','Customer absent');
check($db->one('SELECT status FROM bookings WHERE id=?',[$noShow])['status']==='NO_SHOW','No-show after appointment time');
$community->moderate($admin,['type'=>'business','id'=>$business,'value'=>'SUSPENDED']);
check($catalog->search([])===[],'Suspended establishment hidden from public catalog');
rejects(fn()=>$catalog->publicService($service),404,'Suspended service cannot be booked');
rejects(fn()=>$identity->delete($admin),422,'Last administrator cannot be deleted');
check(e('<script>"&')==='&lt;script&gt;&quot;&amp;','XSS escaping');
echo "OK: {$checks} domain/integration checks, including real concurrent approval.\n";
} finally {
 if($db->sqlite) {unset($bookings,$community,$operations,$catalog,$identity,$db);gc_collect_cycles();foreach([$path,$path.'-wal',$path.'-shm'] as $file) if(is_file($file)) @unlink($file);}
}
