<?php
declare(strict_types=1);
require dirname(__DIR__, 2).'/config/bootstrap.php';
$db=new \AgendaShops\Infrastructure\Database($argv[1]);
$catalog=new \AgendaShops\Modules\Catalog\CatalogService($db);
$bookings=new \AgendaShops\Modules\Bookings\BookingService($db,$catalog);
$user=$db->one('SELECT * FROM users WHERE id=?',[$argv[2]]);
try {$bookings->transition($user,$argv[3],'CONFIRMED','Concurrent test');echo 'OK';}
catch(\AgendaShops\Domain\Problem $error) {echo $error->status;}
