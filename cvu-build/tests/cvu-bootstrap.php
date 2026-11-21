<?php
$loader=require dirname(__DIR__).'/vendor/autoload.php';
$loader->setPsr4('App\\',dirname(__DIR__).'/app');
$loader->setPsr4('Tests\\',__DIR__);
$loader->setPsr4('Database\\Seeders\\',dirname(__DIR__).'/database/seeders');
putenv('APP_BASE_PATH='.dirname(__DIR__));$_ENV['APP_BASE_PATH']=dirname(__DIR__);$_SERVER['APP_BASE_PATH']=dirname(__DIR__);
putenv('APP_KEY=base64:'.base64_encode(str_repeat('c',32)));$_ENV['APP_KEY']='base64:'.base64_encode(str_repeat('c',32));
