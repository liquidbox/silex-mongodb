[![GitHub release](https://img.shields.io/github/release/liquidbox/silex-mongodb.svg)](https://github.com/liquidbox/silex-mongodb/releases)
[![license](https://img.shields.io/github/license/liquidbox/silex-mongodb.svg)](LICENSE)
[![Build Status](https://travis-ci.org/liquidbox/silex-mongodb.svg?branch=master)](https://travis-ci.org/liquidbox/silex-mongodb)
[![Code Coverage](https://scrutinizer-ci.com/g/liquidbox/silex-mongodb/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/liquidbox/silex-mongodb/?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/liquidbox/silex-mongodb/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/liquidbox/silex-mongodb/?branch=master)
[![Packagist](https://img.shields.io/packagist/dt/liquidbox/silex-mongodb.svg)](https://packagist.org/packages/liquidbox/silex-mongodb)

You are reading the documentation for Silex 2.x. Switch to the documentation for Silex [1.x](../v1.0.0/README.md).

# MongoDB

The <em>MongoDbServiceProvider</em> provides integration with the [MongoDB](http://php.net/manual/set.mongodb.php) extension.

## Parameters

* <strong>mongodb.uri</strong> (optional): A [MongoDB](https://docs.mongodb.com/manual/reference/connection-string) connection URI.
* <strong>mongodb.connection</strong> (optional): A collection of parameters for specifying the connection string.
  * <strong>host</strong> (optional): The server address to connect to. Identifies either a hostname, IP address, or UNIX domain socket.
  * <strong>port</strong> (optional): The default value is 27017.
  * <strong>username</strong> (optional): The username for the connection string.
  * <strong>password</strong> (optional): The password for the connection string.
  * <strong>database</strong> (optional): The name of the database.
  * <strong>options</strong> (optional): A collection of connection specific options. See [Connection String Options](https://docs.mongodb.com/manual/reference/connection-string/index.html#connections-connection-options) for a full description of these options.
* <strong>mongodb.uri_options</strong> (optional): Additional connection string options, which will overwrite any options with the same name in the <code>uri</code> or <code>connection</code> parameter.
* <strong>mongodb.driver_options</strong> (optional): An array of options for the MongoDB driver.

The <code>uri</code> parameter overrides <code>connection</code>.

## Services

* <strong>mongodb</strong>: The [<code>MongoDB\Client</code>](https://docs.mongodb.com/php-library/current/reference/class/MongoDBClient) connection instance. The main way of interacting with MongoDB.
* <strong>mongodb.clients</strong>: The collection of MongoDB client instances. See section on [using multiple clients](#using-multiple-clients) for details.
* <strong>mongodb.client</strong>: Factory for <code>MongoDB\Client</code> connection instances.

## Registering

<strong>Example #1 Connecting to a replica set named <i>test</i></strong>

```php
$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.uri' => "mongodb://db1.example.net:27017,db2.example.net:2500/?replicaSet=test",
));

// or

$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.connection' => "db1.example.net:27017,db2.example.net:2500/?replicaSet=test",
));

// or

$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.connection' => array(
        'hosts' => array(
            "db1.example.net:27017",
            array(
                'host' => "db2.example.net",
                'port' => 2500
            ),
        ),
        'options' => array(
            'replicaSet' => "test",
        )
    ),
));
```

All the registered connections above are equivalent.

<strong>Example #2 Connecting to a sharded cluster</strong>

```php
$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.connection' => "r1.example.net:27017,r2.example.net:27017",
));

// or

$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.connection' => array(
        'hosts' => array(
            array('host' => "r1.example.net", 'port' => 27017),
            array('host' => "r2.example.net", 'port' => 27017),
        ),
    ),
));
```

<strong>Example #3 Connecting to a UNIX domain socket with file path <i>/tmp/mongodb-27017.sock</i></strong>

```php
$app->register(new \LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.connection' => rawurlencode("/tmp/mongodb-27017.sock"),
));
```

Add MongoDB as a dependency:

```shell
composer require liquidbox/silex-mongodb:^2.0
```

## Usage

<strong>Example #1: Inserting a document into the <i>beers</i> collection of the <i>demo</i> database</strong>

```php
$collection = $app['mongodb']->demo->beers;

$result = $collection->insertOne(array(
    'name'    => "Hinterland",
    'brewery' => "BrewDog",
));

echo "Inserted with Object ID '{$result->getInsertedId()}'";
```

<strong>Example #2: Using the find method</strong>

```php
$collection = $app['mongodb']->demo->beers;

$results = $collection->find(array(
    'name'    => "Hinterland",
    'brewery' => "BrewDog",
));

foreach ($results as $entry) {
    printf('%d: %s' . PHP_EOL, $entry['_id'], $entry['name']);
}
```

## Using multiple clients

The MongoDB provider can allow the use of multiple clients. In order to configure the URIs, use <strong>mongodb.uri</strong> as an array of configurations where keys are connection names and values are parameters:

```php
$config['mongodb']['replica_name']    = "test";
$config['mongodb']['replica_cluster'] = array(
    "example1.com",
    "example2.com",
    "example3.com",
);

// ...

$app->register(new LiquidBox\Silex\Provider\MongoDbServiceProvider(), array(
    'mongodb.uri' => array(
        'mongo_read' => array(
            'connection' => array(
                'hosts'   => $config['mongodb']['replica_cluster'],
                'options' => array(
                    'replicaSet'     => $config['mongodb']['replica_name'],
                    'readPreference' => "secondary",
                )
            ),
        ),
        'mongo_write' => array(
            'connection' => array(
                'hosts'   => $config['mongodb']['replica_cluster'],
                'options' => array(
                    'replicaSet' => $config['mongodb']['replica_name'],
                    'w'          => 2
                    'wtimeoutMS' => 2000,
                )
            ),
        ),
    ),
));
```

The first registered connection is the default and can simply be accessed as you would if there was only one connection. Given the above configuration, these two lines are equivalent:

```php
$app['mongodb']->zips->find(array('city' => "JERSEY CITY", 'state' => "NJ"));

$app['mongodb.clients']['mongo_read']->zips->find(array('city' => "JERSEY CITY", 'state' => "NJ"));
```

For more information, check out the [official MongoDB documentation](https://docs.mongodb.com/).
