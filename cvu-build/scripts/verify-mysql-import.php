<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$db=Illuminate\Support\Facades\DB::connection('mysql');
$tables=$db->select('SHOW TABLES');
if(!$tables){$db->getPdo()->exec(file_get_contents(__DIR__.'/../database/pre_defense_system.sql'));}
$names=array_map(fn($row)=>array_values((array)$row)[0],$db->select('SHOW TABLES'));
$counts=[];foreach($names as $name)$counts[$name]=(int)$db->table($name)->count();
if(count($names)!==18||array_sum($counts)!==0)throw new RuntimeException('Expected 18 empty tables; existing records are preserved.');
$db->beginTransaction();
try{
 $user=App\Models\User::create(['name'=>'Import verification','email'=>'mysql-check@example.test','username'=>'mysql-check','password'=>'TemporaryVerification!123','account_type'=>'student','role'=>'student']);
 $request=App\Models\ServiceRequest::create(['user_id'=>$user->id,'ticket'=>'MYSQL-CHECK','kind'=>'complaint','details'=>['description'=>'Verification only'],'files'=>[]]);
 if($request->fresh()->details['description']!=='Verification only')throw new RuntimeException('JSON round trip failed.');
 if(!$user->fresh())throw new RuntimeException('User persistence failed.');
}finally{$db->rollBack();}
// Rolled-back inserts advance MySQL sequences, so reset only these verified-empty tables.
foreach(['users','service_requests'] as $name)$db->statement('ALTER TABLE `'.$name.'` AUTO_INCREMENT = 1');
foreach($names as $name)if($db->table($name)->count()!==0)throw new RuntimeException('Unexpected retained rows.');
file_put_contents(__DIR__.'/../DATABASE_EMPTY_VERIFICATION.json',json_encode(['connection'=>'mysql','database'=>$db->getDatabaseName(),'tables'=>$counts,'verification'=>'SQL import and transactional user/request JSON round trip passed; all tables zero rows'],JSON_PRETTY_PRINT));
echo "MySQL import verified: 18 tables, zero rows. Transactional persistence passed.\n";
