<?php declare(strict_types=1);

namespace rickcy\rabbitmq;

use Exception;
use rickcy\rabbitmq\components\Consumer;
use rickcy\rabbitmq\components\Producer;
use rickcy\rabbitmq\components\Routing;
use rickcy\rabbitmq\exceptions\InvalidConfigException;
use PhpAmqpLib\Connection\AbstractConnection;
use PhpAmqpLib\Connection\AMQPLazyConnection;
use Yii;
use yii\base\Component;
use yii\helpers\ArrayHelper;

/**
 *
 * @property Configuration $config
 */
class Configuration extends Component
{

    public const CONNECTION_SERVICE_NAME = 'rabbit_mq.connection.%s';
    public const CONSUMER_SERVICE_NAME = 'rabbit_mq.consumer.%s';
    public const PRODUCER_SERVICE_NAME = 'rabbit_mq.producer.%s';
    public const ROUTING_SERVICE_NAME = 'rabbit_mq.routing';
    public const LOGGER_SERVICE_NAME = 'rabbit_mq.logger';

    public const DEFAULT_CONNECTION_NAME = 'default';
    public const EXTENSION_CONTROLLER_ALIAS = 'rabbitmq';

    /**
     * Extension configuration default values
     * @var array
     */
    public const DEFAULTS = [
        'auto_declare' => false,
        'connections' => [
            [
                'name' => self::DEFAULT_CONNECTION_NAME,
                'type' => AMQPLazyConnection::class,
                'url' => null,
                'host' => null,
                'port' => 5672,
                'user' => 'guest',
                'password' => 'guest',
                'vhost' => '/',
                'connection_timeout' => 3,
                'read_write_timeout' => 3,
                'ssl_context' => null,
                'keepalive' => false,
                'heartbeat' => 0,
                'channel_rpc_timeout' => 0.0,
            ],
        ],
        'exchanges' => [
            [
                'name' => null,
                'type' => null,
                'passive' => false,
                'durable' => true,
                'auto_delete' => false,
                'internal' => false,
                'nowait' => false,
                'arguments' => null,
                'ticket' => null,
                'declare' => true,
            ],
        ],
        'queues' => [
            [
                'name' => '',
                'passive' => false,
                'durable' => true,
                'exclusive' => false,
                'auto_delete' => false,
                'nowait' => false,
                'arguments' => null,
                'ticket' => null,
                'declare' => true,
            ],
        ],
        'bindings' => [
            [
                'exchange' => null,
                'queue' => null,
                'to_exchange' => null,
                'routing_keys' => [],
            ],
        ],
        'producers' => [
            [
                'name' => null,
                'connection' => self::DEFAULT_CONNECTION_NAME,
                'safe' => true,
                'content_type' => 'text/plain',
                'delivery_mode' => 2,
                'serializer' => 'json_encode',
                'serializerParams' => null,
            ],
        ],
        'consumers' => [
            [
                'name' => null,
                'connection' => self::DEFAULT_CONNECTION_NAME,
                'callbacks' => [],
                'qos' => [
                    'prefetch_size' => 0,
                    'prefetch_count' => 0,
                    'global' => false,
                ],
                'idle_timeout' => 0,
                'idle_timeout_exit_code' => null,
                'proceed_on_exception' => false,
                'deserializer' => 'json_decode',
                'deserializerParams' => true,
            ],
        ],
        'logger' => [
            'log' => false,
            'category' => 'application',
            'print_console' => true,
            'system_memory' => false,
        ],
    ];

    public $auto_declare;
    public $connections = [];
    public $producers = [];
    public $consumers = [];
    public $queues = [];
    public $exchanges = [];
    public $bindings = [];
    public $logger = [];

    protected $isLoaded = false;

    /**
     * Get passed configuration
     * @return Configuration
     * @throws Exception
     */
    public function getConfig(): Configuration
    {
        if (!$this->isLoaded) {
            $this->normalizeConnections();
            $this->validate();
            $this->completeWithDefaults();
            $this->isLoaded = true;
        }

        return $this;
    }

    /**
     * Get connection service
     * @param string $connectionName
     * @return object|AbstractConnection
     * @throws Exception
     */
    public function getConnection(string $connectionName = '')
    {
        if ('' === $connectionName) {
            $connectionName = static::DEFAULT_CONNECTION_NAME;
        }

        return Yii::$container->get(sprintf(static::CONNECTION_SERVICE_NAME, $connectionName));
    }

    /**
     * Get producer service
     * @param string $producerName
     * @return object|Producer
     * @throws Exception
     */
    public function getProducer(string $producerName)
    {
        return Yii::$container->get(sprintf(static::PRODUCER_SERVICE_NAME, $producerName));
    }

    /**
     * Get consumer service
     * @param string $consumerName
     * @return object|Consumer
     * @throws Exception
     */
    public function getConsumer(string $consumerName)
    {
        return Yii::$container->get(sprintf(static::CONSUMER_SERVICE_NAME, $consumerName));
    }

    /**
     * Get routing service
     * @param AbstractConnection $connection
     * @return object|Routing
     * @throws Exception
 */
    public function getRouting(AbstractConnection $connection)
    {
        return Yii::$container->get(static::ROUTING_SERVICE_NAME, ['conn' => $connection]);
    }

    /**
     * Config validation
     * @throws Exception
     */
    protected function validate(): void
    {
        $this->validateTopLevel();
        $this->validateMultidimensional();
        $this->validateRequired();
        $this->validateDuplicateNames(['connections', 'exchanges', 'queues', 'producers', 'consumers']);
    }

    /**
     * Validate multidimensional entries names
     * @throws Exception
     */
    protected function validateMultidimensional(): void
    {
        $multidimensional = [
            'connection' => $this->connections,
            'exchange' => $this->exchanges,
            'queue' => $this->queues,
            'binding' => $this->bindings,
            'producer' => $this->producers,
            'consumer' => $this->consumers,
        ];

        foreach ($multidimensional as $configName => $configItem) {
            if (!is_array($configItem)) {
                throw new InvalidConfigException("Every {$configName} entry should be of type array.");
            }
            foreach ($configItem as $key => $value) {
                if (!is_int($key)) {
                    throw new InvalidConfigException("Invalid key: `{$key}`. There should be a list of {$configName}s in the array.");
                }
            }
        }
    }

    /**
     * Validate top level options
     * @throws Exception
     */
    protected function validateTopLevel(): void
    {
        if (($this->auto_declare !== null) && !is_bool($this->auto_declare)) {
            throw new InvalidConfigException('Option `auto_declare` should be of type boolean.');
        }

        if (!is_array($this->logger)) {
            throw new InvalidConfigException('Option `logger` should be of type array.');
        }

        $this->validateArrayFields($this->logger, static::DEFAULTS['logger']);
    }

    /**
     * Validate required options
     * @throws Exception
     */
    protected function validateRequired(): void
    {
        foreach ($this->connections as $connection) {
            $this->validateArrayFields($connection, static::DEFAULTS['connections'][0]);
            if (!isset($connection['url']) && !isset($connection['host'])) {
                throw new InvalidConfigException('Either `url` or `host` options required for configuring connection.');
            }
            if (isset($connection['url']) && (isset($connection['host']) || isset($connection['port']))) {
                throw new InvalidConfigException('Connection options `url` and `host:port` should not be both specified, configuration is ambigious.');
            }
            if (!isset($connection['name'])) {
                throw new InvalidConfigException('Connection name is required when multiple connections is specified.');
            }
            if (isset($connection['type']) && !is_subclass_of($connection['type'], AbstractConnection::class)) {
                throw new InvalidConfigException('Connection type should be a subclass of PhpAmqpLib\Connection\AbstractConnection.');
            }
        }

        foreach ($this->exchanges as $exchange) {
            $this->validateArrayFields($exchange, static::DEFAULTS['exchanges'][0]);
            if (!isset($exchange['name'])) {
                throw new InvalidConfigException('Exchange name should be specified.');
            }
            if (!isset($exchange['type'])) {
                throw new InvalidConfigException('Exchange type should be specified.');
            }
            $allowed = ['direct', 'topic', 'fanout', 'headers'];
            if (!in_array($exchange['type'], $allowed, true)) {
                $allowed = implode(', ', $allowed);
                throw new InvalidConfigException("Unknown exchange type `{$exchange['type']}`. Allowed values are: {$allowed}");
            }
        }
        foreach ($this->queues as $queue) {
            $this->validateArrayFields($queue, static::DEFAULTS['queues'][0]);
        }
        foreach ($this->bindings as $binding) {
            $this->validateArrayFields($binding, static::DEFAULTS['bindings'][0]);
            if (!isset($binding['exchange'])) {
                throw new InvalidConfigException('Exchange name is required for binding.');
            }
            if (!$this->isNameExist($this->exchanges, $binding['exchange'])) {
                throw new InvalidConfigException("`{$binding['exchange']}` defined in binding doesn't configured in exchanges.");
            }
            if (isset($binding['routing_keys']) && !is_array($binding['routing_keys'])) {
                throw new InvalidConfigException('Option `routing_keys` should be an array.');
            }
            if ((!isset($binding['queue']) && !isset($binding['to_exchange'])) || isset($binding['queue'], $binding['to_exchange'])) {
                throw new InvalidConfigException('Either `queue` or `to_exchange` options should be specified to create binding.');
            }
            if (isset($binding['queue']) && !$this->isNameExist($this->queues, $binding['queue'])) {
                throw new InvalidConfigException("`{$binding['queue']}` defined in binding doesn't configured in queues.");
            }
        }
        foreach ($this->producers as $producer) {
            $this->validateArrayFields($producer, static::DEFAULTS['producers'][0]);
            if (!isset($producer['name'])) {
                throw new InvalidConfigException('Producer name is required.');
            }
            if (isset($producer['connection']) && !$this->isNameExist($this->connections, $producer['connection'])) {
                throw new InvalidConfigException("Connection `{$producer['connection']}` defined in producer doesn't configured in connections.");
            }
            if (isset($producer['safe']) && !is_bool($producer['safe'])) {
                throw new InvalidConfigException('Producer option safe should be of type boolean.');
            }
            if (!isset($producer['connection']) && !$this->isNameExist($this->connections, static::DEFAULT_CONNECTION_NAME)) {
                throw new InvalidConfigException("Connection for producer `{$producer['name']}` is required.");
            }
            if (isset($producer['serializer']) && !is_callable($producer['serializer'])) {
                throw new InvalidConfigException('Producer `serializer` option should be a callable.');
            }
        }
        foreach ($this->consumers as $consumer) {
            $this->validateArrayFields($consumer, static::DEFAULTS['consumers'][0]);
            if (!isset($consumer['name'])) {
                throw new InvalidConfigException('Consumer name is required.');
            }
            if (isset($consumer['connection']) && !$this->isNameExist($this->connections, $consumer['connection'])) {
                throw new InvalidConfigException("Connection `{$consumer['connection']}` defined in consumer doesn't configured in connections.");
            }
            if (!isset($consumer['connection']) && !$this->isNameExist($this->connections, static::DEFAULT_CONNECTION_NAME)) {
                throw new InvalidConfigException("Connection for consumer `{$consumer['name']}` is required.");
            }
            if (!isset($consumer['callbacks']) || empty($consumer['callbacks'])) {
                throw new InvalidConfigException("No callbacks specified for consumer `{$consumer['name']}`.");
            }
            if (isset($consumer['qos']) && !is_array($consumer['qos'])) {
                throw new InvalidConfigException('Consumer option `qos` should be of type array.');
            }
            if (isset($consumer['proceed_on_exception']) && !is_bool($consumer['proceed_on_exception'])) {
                throw new InvalidConfigException('Consumer option `proceed_on_exception` should be of type boolean.');
            }
            foreach ($consumer['callbacks'] as $queue => $callback) {
                if (!$this->isNameExist($this->queues, $queue)) {
                    throw new InvalidConfigException("Queue `{$queue}` from {$consumer['name']} is not defined in queues.");
                }
                if (!is_string($callback)) {
                    throw new InvalidConfigException('Consumer `callback` parameter value should be a class name or service name in DI container.');
                }
            }
            if (isset($consumer['deserializer']) && !is_callable($consumer['deserializer'])) {
                throw new InvalidConfigException('Consumer `deserializer` option should be a callable.');
            }
        }
    }

    /**
     * Validate config entry value
     * @param array $passed
     * @param array $required
     * @throws Exception
     */
    protected function validateArrayFields(array $passed, array $required): void
    {
        $undeclaredFields = array_diff_key($passed, $required);
        if (!empty($undeclaredFields)) {
            $asString = json_encode($undeclaredFields);
            throw new InvalidConfigException("Unknown options: {$asString}");
        }
    }

    /**
     * Check entrees for duplicate names
     * @param array $keys
     * @throws Exception
     */
    protected function validateDuplicateNames(array $keys): void
    {
        foreach ($keys as $key) {
            $names = [];
            foreach ($this->$key as $item) {
                if (!isset($item['name'])) {
                    $item['name'] = '';
                }
                if (isset($names[$item['name']])) {
                    throw new InvalidConfigException("Duplicate name `{$item['name']}` in {$key}");
                }
                $names[$item['name']] = true;
            }
        }
    }

    /**
     * Allow certain flexibility on connection configuration
     * @throws Exception
     */
    protected function normalizeConnections(): void
    {
        if (empty($this->connections)) {
            throw new InvalidConfigException('Option `connections` should have at least one entry.');
        }
        if (ArrayHelper::isAssociative($this->connections)) {
            $this->connections[0] = $this->connections;
        }
        if ((count($this->connections) === 1) && !isset($this->connections[0]['name'])) {
            $this->connections[0]['name'] = static::DEFAULT_CONNECTION_NAME;
        }
    }

    /**
     * Merge passed config with extension defaults
     */
    protected function completeWithDefaults(): void
    {
        $defaults = static::DEFAULTS;
        if (null === $this->auto_declare) {
            $this->auto_declare = $defaults['auto_declare'];
        }
        if (empty($this->logger)) {
            $this->logger = $defaults['logger'];
        } else {
            foreach ($defaults['logger'] as $key => $option) {
                if (!isset($this->logger[$key])) {
                    $this->logger[$key] = $option;
                }
            }
        }
        $multi = ['connections', 'bindings', 'exchanges', 'queues', 'producers', 'consumers'];
        foreach ($multi as $key) {
            foreach ($this->$key as &$item) {
                $item = array_replace_recursive($defaults[$key][0], $item);
            }
        }
    }

    /**
     * Check if an entry with specific name exists in array
     * @param array $multidimentional
     * @param string $name
     * @return bool
     */
    private function isNameExist(array $multidimentional, string $name): bool
    {
        if ($name === '') {
            foreach ($multidimentional as $item) {
                if (!isset($item['name'])) {
                    return true;
                }
            }
            return false;
        }
        $key = array_search($name, array_column($multidimentional, 'name'), true);
        if (is_int($key)) {
            return true;
        }

        return false;
    }
}
