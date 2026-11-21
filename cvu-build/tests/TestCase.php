<?php
namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        // Never load a cached local/production database configuration in tests.
        foreach (['APP_ENV'=>'testing','APP_CONFIG_CACHE'=>sys_get_temp_dir().'/iserve-testing-no-config-'.getmypid().'.php','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','DB_URL'=>'','MAIL_MAILER'=>'array','SESSION_DRIVER'=>'array','CACHE_STORE'=>'array','QUEUE_CONNECTION'=>'sync'] as $key=>$value) {
            putenv($key.'='.$value); $_ENV[$key]=$value; $_SERVER[$key]=$value;
        }
        $app = parent::createApplication();
        if ($app['config']['database.default'] !== 'sqlite' || $app['config']['database.connections.sqlite.database'] !== ':memory:') {
            throw new \RuntimeException('Tests refuse to run against a persistent database.');
        }
        return $app;
    }
}
