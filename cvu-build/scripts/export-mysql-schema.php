<?php
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
config(['database.default'=>'mysql']);
$queries=Illuminate\Support\Facades\DB::connection('mysql')->pretend(function(){
 (new Illuminate\Database\Migrations\DatabaseMigrationRepository(app('db'),'migrations'))->createRepository();
 foreach(glob(__DIR__.'/../database/migrations/*.php') as $file){$migration=require $file;$migration->up();}
});
$sql="-- Empty MySQL/MariaDB schema. Select an empty pre_defense_system database before importing.\n-- No DROP, DELETE, INSERT or demo records.\nSET NAMES utf8mb4;\n\n";
foreach($queries as $query){if($query['bindings'])throw new RuntimeException('Unexpected schema bindings.');$sql.=$query['query'].";\n\n";}
file_put_contents(__DIR__.'/../database/pre_defense_system.sql',$sql);
echo 'Generated '.count($queries)." schema statements, zero rows.\n";
