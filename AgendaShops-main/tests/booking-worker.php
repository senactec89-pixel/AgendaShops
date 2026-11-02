<?php
require dirname(__DIR__).'/config/bootstrap.php';
$_ENV['DB_DATABASE']=$argv[1];
try { $r=(new app\Services\Booking)->save((int)$argv[2],(int)$argv[3],(int)$argv[4],$argv[5],$argv[6],['name'=>'Concorrente','phone'=>$argv[7],'email'=>'']);echo 'OK'; }
catch(Throwable $e) {echo $e->getCode()===409?'CONFLICT':'ERROR:'.$e->getMessage();}
