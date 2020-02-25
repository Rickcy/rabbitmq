<?php declare(strict_types=1);

namespace rickcy\rabbitmq\components;

use Exception;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AbstractConnection;
use yii\base\BaseObject;


/**
 *
 * @property AMQPChannel $channel
 */
abstract class BaseRabbitMQ extends BaseObject
{
    /**
     * @var AbstractConnection
     */
    protected $conn;
    protected $autoDeclare;

    /**
     * @var AMQPChannel
     */
    protected $ch;

    /**
     * @var $logger Logger
     */
    protected $logger;

    /**
     * @var $routing Routing
     */
    protected $routing;

    /**
     * @param AbstractConnection $conn
     * @param Routing $routing
     * @param Logger $logger
     * @param bool $autoDeclare
     */
    public function __construct(AbstractConnection $conn, Routing $routing, Logger $logger, bool $autoDeclare)
    {
        parent::__construct();
        $this->conn = $conn;
        $this->routing = $routing;
        $this->logger = $logger;
        $this->autoDeclare = $autoDeclare;
        if ($conn->connectOnConstruct()) {
            $this->getChannel();
        }
    }

    public function __destruct()
    {
        $this->close();
    }

    /**
     * @param AMQPChannel $channel
     */
    public function setChannel(AMQPChannel $channel): void
    {
        $this->ch = $channel;
    }

    /**
     *
     */
    public function close(): void
    {
        if ($this->ch) {
            try {
                $this->ch->close();
            } catch (Exception $e) {
                // ignore on shutdown
            }
        }
        if ($this->conn && $this->conn->isConnected()) {
            try {
                $this->conn->close();
            } catch (Exception $e) {
                // ignore on shutdown
            }
        }
    }

    public function renew(): void
    {
        if (!$this->conn->isConnected()) {
            return;
        }
        $this->conn->reconnect();
    }

    /**
     * @return AMQPChannel
     */
    public function getChannel(): AMQPChannel
    {
        if (empty($this->ch) || null === $this->ch->getChannelId()) {
            $this->ch = $this->conn->channel();
        }

        return $this->ch;
    }
}
