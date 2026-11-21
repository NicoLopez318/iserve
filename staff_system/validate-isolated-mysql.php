<?php
if(PHP_SAPI!=='cli')exit(1);
$name='staff_validation_'.bin2hex(random_bytes(6));
$pdo=new PDO('mysql:host=127.0.0.1;port=3306','root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$created=false;$code=1;
try{$pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');$created=true;$env=getenv();$env['DB_CONNECTION']='mysql';$env['DB_DATABASE']=$name;$env['DB_HOST']='127.0.0.1';$env['DB_PORT']='3306';$env['DB_USERNAME']='root';$env['DB_PASSWORD']='';$env['DB_URL']='';$env['APP_ENV']='testing';$proc=proc_open([PHP_BINARY,'artisan','test'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,__DIR__,$env);$code=proc_close($proc);}finally{if($created)$pdo->exec('DROP DATABASE `'.$name.'`');}
exit($code);
