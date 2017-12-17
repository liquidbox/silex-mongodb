<?php
/**
 * MongoDB service provider for the Silex micro-framework.
 *
 * @see http://php.net/manual/set.mongodb.php
 */

namespace LiquidBox\Silex\Provider;

use MongoDB;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 * MongoDb Provider.
 *
 * @author Jonathan-Paul Marois <jonathanpaul.marois@gmail.com>
 */
class MongoDbServiceProvider implements ServiceProviderInterface
{
    /**
     * @var array Buffer.
     */
    private $args = array();

    /**
     * @var array
     */
    private $defaultArgs = array();

    /**
     * @var int|string
     */
    private $defaultConnection = 0;

    /**
     * @var string
     */
    private $id = 'mongodb';

    /**
     * @var string
     */
    private $instances = 'mongodb.clients';

    /**
     * @var bool
     */
    private $isLoaded = false;

    /**
     * @var array
     */
    private $parameters = array();

    /**
     * @param string $id
     * @param string $instances
     */
    public function __construct($id = null, $instances = null)
    {
        if (strlen($id)) {
            $this->id = $id;
        }
        if (strlen($instances)) {
            $this->instances = $instances;
        }
    }

    /**
     * @return string
     */
    private function buildConnectionString(array $connection)
    {
        $uri = $this->getArgConnectionAuthority($connection) . '/';

        if (!empty($connection['db'])) {
            $uri .= $connection['db'];
        }
        if (!empty($connection['options'])) {
            $uri .= '?' . http_build_query($connection['options']);
        }

        return rtrim($uri, '/');
    }

    /**
     * @param string|array $connection
     *
     * @return string
     */
    private function buildUri($connection)
    {
        if (!is_array($connection)) {
            if ('mongodb' == substr($connection, 0, 7)) {
                return $connection;
            }

            $connection = array('host' => ltrim($connection, '/'));
        }
        
        return 'mongodb://' . $this->buildConnectionString($connection);
    }

    /**
     * @param \Pimple\Container $app
     * @param string            $name
     *
     * @return string
     */
    private function getArg(Container $app, $name)
    {
        return isset($this->args[$name]) ? $this->args[$name] : $this->getDefaultArg($app, $name);
    }

    private function getArgConnectionAuthority(array $connection)
    {
        if (empty($connection['user'])) {
            return $this->getArgConnectionHost($connection);
        }

        return $connection['user'] . ':' . rawurlencode($connection['pwd']) . '@' .
            $this->getArgConnectionHost($connection);
    }

    /**
     * @return string
     */
    private function getArgConnectionHost(array $connection = array())
    {
        if (!empty($connection['hosts'])) {
            return $this->getArgConnectionHosts($connection);
        }
        if (empty($connection['host'])) {
            $connection['host'] = 'localhost';
        }

        return empty($connection['port']) ? $connection['host'] : $connection['host'] . ':' . $connection['port'];
    }

    private function getArgConnectionHosts(array $connection)
    {
        return implode(',', array_map(
            function ($hosts) {
                return $this->getArgConnectionHost(is_array($hosts) ? $hosts : array('host' => $hosts));
            },
            $connection['hosts']
        ));
    }

    /**
     * @return string|array
     */
    private function getArgUri()
    {
        if (!empty($this->args['uri'])) {
            return $this->args['uri'];
        }
        if (!empty($this->args['connection'])) {
            return is_array($this->args['connection']) ? $this->args['connection'] : $this->args['connection'];
        }
    }

    /**
     * @param \Pimple\Container $app
     * @param string            $name
     *
     * @return string|array
     */
    private function getDefaultArg(Container $app, $name)
    {
        if (!isset($this->defaultArgs[$name])) {
            $this->defaultArgs[$name] = empty($app['mongodb.' . $name]) ?
                array('uri_options' => array(), 'driver_options' => array())[$name] :
                $app['mongodb.' . $name];
        }

        return $this->defaultArgs[$name];
    }

    private function loadParameters(Container $app)
    {
        if ($this->isLoaded) {
            return;
        }

        $this->isLoaded = true;

        if (empty($app['mongodb.uri']) || !is_array($app['mongodb.uri'])) {
            $this->loadSingletonParameters($app);
        } else {
            $this->parameters = $app['mongodb.uri'];
            $this->defaultConnection = array_keys($this->parameters)[0];
        }
    }

    private function loadSingletonParameters(Container $app)
    {
        $this->parameters[0] = array();

        if (!empty($app['mongodb.uri'])) {
            $this->parameters[0]['uri'] = $app['mongodb.uri'];
        } elseif (!empty($app['mongodb.connection'])) {
            $this->parameters[0]['connection'] = $app['mongodb.connection'];
        }

        if (!empty($app['mongodb.uri_options'])) {
            $this->parameters[0]['uri_options'] = $app['mongodb.uri_options'];
        }
        if (!empty($app['mongodb.driver_options'])) {
            $this->parameters[0]['driver_options'] = $app['mongodb.driver_options'];
        }
    }

    public function register(Container $app)
    {
        $app[$this->id] = function () use ($app) {
            $this->loadParameters($app);

            return $app[$this->instances][$this->defaultConnection];
        };
        $app[$this->instances] = function () use ($app) {
            $this->loadParameters($app);

            $instances = new Container();
            foreach ($this->parameters as $client => $this->args) {
                $instances[$client] = function () use ($app) {
                    return $app['mongodb.client'](
                        $this->getArgUri(),
                        $this->getArg($app, 'uri_options'),
                        $this->getArg($app, 'driver_options')
                    );
                };
            }

            return $instances;
        };

        $app['mongodb.client'] = $app->protect(
            function ($uri = '', array $uriOptions = array(), array $driverOptions = array()) {
                return new MongoDB\Client($this->buildUri($uri), $uriOptions, $driverOptions);
            }
        );
    }
}
