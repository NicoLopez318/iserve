<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/vendor/autoload.php';$app=require __DIR__.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$tables=Illuminate\Support\Facades\DB::select('SHOW TABLES');$counts=[];foreach($tables as $row){$table=array_values((array)$row)[0];$counts[$table]=Illuminate\Support\Facades\DB::table($table)->count();}
echo json_encode(['database'=>config('database.connections.mysql.database'),'tables'=>$counts,'shared_private_root'=>config('filesystems.disks.local.root'),'shared_uploads_directory_exists'=>is_dir(config('filesystems.disks.local.root'))],JSON_PRETTY_PRINT).PHP_EOL;
