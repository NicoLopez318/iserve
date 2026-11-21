<?php
// Additive, idempotent compatibility update. Never recreates or clears a table.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/vendor/autoload.php';
$app=require __DIR__.'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
if(DB::getDriverName()!=='mysql')throw new RuntimeException('Use the configured MySQL database.');
$column=DB::selectOne("SHOW COLUMNS FROM points WHERE Field = 'amount'");
if(!$column)throw new RuntimeException('Existing points table is missing. Do not import over an existing database.');
if(str_contains(strtolower($column->Type),'unsigned')){
 if((int)DB::table('points')->max('amount')>2147483647)throw new RuntimeException('Existing amount exceeds signed integer range; update stopped.');
 $ddl=(array)DB::selectOne('SHOW CREATE TABLE points');file_put_contents(__DIR__.'/database/points-schema-before.sql',array_values($ddl)[1].";\n");
 $before=DB::table('points')->count();DB::statement('ALTER TABLE points MODIFY amount INT NOT NULL');
 if(DB::table('points')->count()!==$before)throw new RuntimeException('Row count changed unexpectedly.');
 echo "Points now supports awards and deductions; existing rows preserved.\n";
}else echo "Shared database already supports signed points. No change needed.\n";
