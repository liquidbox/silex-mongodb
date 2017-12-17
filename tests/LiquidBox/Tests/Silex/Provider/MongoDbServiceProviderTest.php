<?php

namespace LiquidBox\Tests\Silex\Provider;

use Silex\WebTestCase;

/**
 * @author Jonathan-Paul Marois <jonathanpaul.marois@gmail.com>
 */
class MongoDbServiceProviderTest extends WebTestCase
{
    public function createApplication()
    {
        $app = new \Silex\Application();

        return $app;
    }

    public function parameterProvider()
    {
        return [
            '(string) Username, password, host, database, & options URI' => [[
                'mongodb.uri' => "mongodb://liquidbox:liquidbox@localhost/test?connectTimeoutMS=3000",
            ]],
            '(string) Username, password, host, database, & options connection' => [[
                'mongodb.connection' => "liquidbox:liquidbox@localhost/test?connectTimeoutMS=3000",
            ]],
            '(string) Dirty host only connection' => [[
                'mongodb.connection' => "//localhost",
            ]],
            '(array) Username, password, database, & options connection' => [[
                'mongodb.connection' => [
                    'user'    => "liquidbox",
                    'pwd'     => "liquidbox",
                    'db'      => "test",
                    'options' => ['connectTimeoutMS' => 3000],
                ],
            ]],
            '(array) Multiple hosts connection' => [[
                'mongodb.connection' => [
                    'hosts' => [
                        "localhost",
                        ['host' => "localhost", 'port' => 27017],
                        "127.0.0.1",
                        ['port' => 27017]
                    ],
                ],
            ]],
            '(array) URI & driver options only' => [[
                'mongodb.uri_options'    => ['connectTimeoutMS' => 3000],
                'mongodb.driver_options' => ['allow_invalid_hostname' => true, 'weak_cert_validation' => true],
            ]],
        ];
    }

    /**
     * @dataProvider parameterProvider
     */
    public function testRegister(array $properties)
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), $properties);

        // required
        $this->app['mongodb']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['mongodb']);
    }

    public function testRegisterAndMongoDbClient()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider());

        // required
        $this->app['mongodb']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $mongodb = $this->app['mongodb.client']();

        $this->assertInstanceOf('\\MongoDB\\Client', $mongodb);
        $this->assertEquals('localhost', $this->app['mongodb']->getManager()->getServers()[0]->getHost());
        $this->assertEquals('27017', $this->app['mongodb']->getManager()->getServers()[0]->getPort());
    }

    public function testRegisterAsDoctrineService()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider('db', 'dbs'), [
            'mongodb.uri' => 'mongodb://127.0.0.1',
        ]);

        // required
        $this->app['db']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['db']);
        $this->assertEquals('127.0.0.1', $this->app['db']->getManager()->getServers()[0]->getHost());
        $this->assertEquals('27017', $this->app['db']->getManager()->getServers()[0]->getPort());

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['dbs'][0]);

        $this->assertEquals(
            $this->app['db']->getManager()->getServers()[0]->getHost(),
            $this->app['dbs'][0]->getManager()->getServers()[0]->getHost()
        );
        $this->assertEquals(
            $this->app['db']->getManager()->getServers()[0]->getPort(),
            $this->app['dbs'][0]->getManager()->getServers()[0]->getPort()
        );
    }

    public function testRegisterAsReadOnlyUser()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), [
            'mongodb.connection' => ['user' => "ro_user", 'pwd' => "p%:@/?=&", 'db' => "test"]
        ]);

        // required
        $this->app['mongodb']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['mongodb']);
        // test find()
        // test false insertOne()
    }

    public function testRegisterWithRoleReadWrite()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), [
            'mongodb.connection' => ['user' => "liquidbox", 'pwd' => "liquidbox", 'db' => "test"]
        ]);

        // required
        $this->app['mongodb']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['mongodb']);
        // test true insertOne()
    }

    public function testRegisterWithMulipleMongoDbClients()
    {
        $this->app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), [
            'mongodb.uri' => [
                'read'  => ['connection' => ['options' => ['readPreference' => "secondary"]]],
                'write' => ['connection' => ['options' => ['w' => 2, 'wtimeoutMS' => 2000]]]
            ],
            'mongodb.uri_options'    => ['connectTimeoutMS' => 3000],
            'mongodb.driver_options' => ['allow_invalid_hostname' => true, 'weak_cert_validation' => true]
        ]);

        // required
        $this->app['mongodb']->getManager()->executeCommand('test', new \MongoDB\Driver\Command(['ping' => 1]));

        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['mongodb.clients']['read']);
        $this->assertInstanceOf('\\MongoDB\\Client', $this->app['mongodb.clients']['write']);

        $this->assertEquals(
            $this->app['mongodb.clients']['read']->getManager()->getServers()[0]->getHost(),
            $this->app['mongodb.clients']['write']->getManager()->getServers()[0]->getHost()
        );
    }
}
