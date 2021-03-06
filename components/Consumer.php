<?php declare(strict_types=1);

namespace rickcy\rabbitmq\components;

use BadFunctionCallException;
use ErrorException;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Message\AMQPMessage;
use rickcy\rabbitmq\events\RabbitMQConsumerEvent;
use rickcy\rabbitmq\exceptions\RuntimeException;
use Throwable;
use Yii;

/**
 * Service that receives AMQP Messages
 *
 * @package rickcy\rabbitmq\components
 */
class Consumer extends BaseRabbitMQ
{
    protected $deserializer;

    protected $deserializerParams;

    public $withoutSignals = false;

    public $debug = false;

    protected $qos;

    protected $idleTimeout;

    protected $idleTimeoutExitCode;

    protected $queues = [];

    protected $paramsQueue = [];

    protected $memoryLimit = 0;

    protected $proceedOnException;

    protected $name = 'unnamed';

    private $id;

    private $target;

    private $consumed = 0;

    private $forceStop = false;

    /**
     * @param $queueName
     * @param $param
     * @return bool|null|string
     */
    public function getParamQueue($queueName, $param)
    {
        if (isset($this->paramsQueue[$queueName])) {
            return $this->paramsQueue[$queueName][$param];
        }
        return null;
    }

    /**
     * @param $queueName
     * @param array $paramsQueue
     */
    public function setParamsQueue($queueName, array $paramsQueue): void
    {
        $this->paramsQueue[$queueName] = $paramsQueue;
    }


    /**
     * Set the memory limit
     *
     * @param int $memoryLimit
     */
    public function setMemoryLimit($memoryLimit): void
    {
        $this->memoryLimit = $memoryLimit;
    }

    /**
     *
     * /**
     * Get the memory limit
     *
     * @return int
     */
    public function getMemoryLimit(): int
    {
        return $this->memoryLimit;
    }

    /**
     * @param array $queues
     */
    public function setQueues(array $queues): void
    {
        $this->queues = $queues;
    }

    /**
     * @return array
     */
    public function getQueues(): array
    {
        return $this->queues;
    }

    /**
     * @param $idleTimeout
     */
    public function setIdleTimeout($idleTimeout): void
    {
        $this->idleTimeout = $idleTimeout;
    }

    public function getIdleTimeout()
    {
        return $this->idleTimeout;
    }

    /**
     * Set exit code to be returned when there is a timeout exception
     *
     * @param int|null $idleTimeoutExitCode
     */
    public function setIdleTimeoutExitCode($idleTimeoutExitCode): void
    {
        $this->idleTimeoutExitCode = $idleTimeoutExitCode;
    }

    /**
     * Get exit code to be returned when there is a timeout exception
     *
     * @return int|null
     */
    public function getIdleTimeoutExitCode(): ?int
    {
        return $this->idleTimeoutExitCode;
    }

    /**
     * @return mixed
     */
    public function getDeserializer(): callable
    {
        return $this->deserializer;
    }

    /**
     * @param mixed $deserializer
     */
    public function setDeserializer(callable $deserializer): void
    {
        $this->deserializer = $deserializer;
    }

    /**
     * @return mixed
     */
    public function getDeserializerParams()
    {
        return $this->deserializerParams;
    }

    /**
     * @param mixed $deserializerParams
     */
    public function setDeserializerParams($deserializerParams): void
    {
        $this->deserializerParams = $deserializerParams;
    }


    /**
     * @return mixed
     */
    public function getQos(): array
    {
        return $this->qos;
    }

    /**
     * @param mixed $qos
     */
    public function setQos(array $qos): void
    {
        $this->qos = $qos;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Resets the consumed property.
     * Use when you want to call start() or consume() multiple times.
     */
    public function getConsumed(): int
    {
        return $this->consumed;
    }

    /**
     * Resets the consumed property.
     * Use when you want to call start() or consume() multiple times.
     */
    public function resetConsumed(): void
    {
        $this->consumed = 0;
    }

    /**
     * @return mixed
     */
    public function getProceedOnException(): bool
    {
        return $this->proceedOnException;
    }

    /**
     * @param mixed $proceedOnException
     */
    public function setProceedOnException(bool $proceedOnException): void
    {
        $this->proceedOnException = $proceedOnException;
    }

    /**
     * Consume designated number of messages (0 means infinite)
     *
     * @param int $msgAmount
     *
     * @return int
     * @throws BadFunctionCallException
     * @throws RuntimeException
     * @throws AMQPTimeoutException
     * @throws ErrorException
     */
    public function consume($msgAmount = 0): int
    {
        $this->target = $msgAmount;
        $this->setup();
        // At the end of the callback execution
        while (count($this->getChannel()->callbacks)) {
            if ($this->maybeStopConsumer()) {
                break;
            }
            try {
                $this->getChannel()->wait(null, false, $this->getIdleTimeout());
            } catch (AMQPTimeoutException $e) {
                if (null !== $this->getIdleTimeoutExitCode()) {
                    return $this->getIdleTimeoutExitCode();
                }

                throw $e;
            }
            if (defined('AMQP_WITHOUT_SIGNALS') === false) {
                define('AMQP_WITHOUT_SIGNALS', $this->withoutSignals);
            }
            if (defined('AMQP_DEBUG') === false) {
                if ($this->debug === 'false') {
                    $this->debug = false;
                }
                define('AMQP_DEBUG', (bool)$this->debug);
            }
            if (!AMQP_WITHOUT_SIGNALS && extension_loaded('pcntl')) {
                pcntl_signal_dispatch();
            }
        }

        return 0;
    }

    /**
     * Stop consuming messages
     */
    public function stopConsuming(): void
    {
        foreach ($this->queues as $name => $options) {
            $this->getChannel()->basic_cancel($this->getConsumerTag($name), false, true);
        }
    }

    /**
     * Force stop the consumer
     */
    public function stopDaemon(): void
    {
        $this->forceStop = true;
        $this->stopConsuming();
        $this->logger->printInfo("\nConsumer stopped by user.\n");
    }

    /**
     * Force restart the consumer
     */
    public function restartDaemon(): void
    {
        $this->stopConsuming();
        $this->renew();
        $this->setup();
        $this->logger->printInfo("\nConsumer has been restarted.\n");
    }

    /**
     * Sets the qos settings for the current channel
     * This method needs a connection to broker
     */
    protected function setQosOptions(): void
    {
        if (empty($this->qos)) {
            return;
        }
        $prefetchSize = $this->qos['prefetch_size'] ?? null;
        $prefetchCount = $this->qos['prefetch_count'] ?? null;
        $global = $this->qos['global'] ?? null;
        $this->getChannel()->basic_qos($prefetchSize, $prefetchCount, $global);
    }

    /**
     * Start consuming messages
     *
     * @throws RuntimeException
     */
    protected function startConsuming(): void
    {
        $this->id = $this->generateUniqueId();

        foreach ($this->queues as $queue => $callback) {
            $that = $this;
            $this->getChannel()->basic_consume(
                $queue,
                $this->getParamQueue($queue, 'tag') ?? $this->getConsumerTag($queue),
                $this->getParamQueue($queue, 'no_local'),
                $this->getParamQueue($queue, 'no_ack'),
                $this->getParamQueue($queue, 'exclusive'),
                $this->getParamQueue($queue, 'nowait'),
                static function (AMQPMessage $msg) use ($that, $queue, $callback) {
                    // Execute user-defined callback
                    $that->onReceive($msg, $queue, $callback);
                }
            );
        }
    }

    /**
     * Decide whether it's time to stop consuming
     *
     * @throws BadFunctionCallException
     */
    protected function maybeStopConsumer(): bool
    {
        if (extension_loaded('pcntl') && (defined('AMQP_WITHOUT_SIGNALS') ? !AMQP_WITHOUT_SIGNALS : true)) {
            if (!function_exists('pcntl_signal_dispatch')) {
                throw new BadFunctionCallException(
                    "Function 'pcntl_signal_dispatch' is referenced in the php.ini 'disable_functions' and can't be called."
                );
            }
            pcntl_signal_dispatch();
        }
        if ($this->forceStop || ($this->consumed === $this->target && $this->target > 0)) {
            $this->stopConsuming();

            return true;
        }

        if (0 !== $this->getMemoryLimit() && $this->isRamAlmostOverloaded()) {
            $this->stopConsuming();

            return true;
        }

        return false;
    }

    /**
     * Callback that will be fired upon receiving new message
     *
     * @param AMQPMessage $msg
     * @param             $queueName
     * @param             $callback
     *
     * @return bool
     * @throws Throwable
     */
    protected function onReceive(AMQPMessage $msg, string $queueName, callable $callback): bool
    {
        $timeStart = microtime(true);
        Yii::$app->rabbitmq->trigger(
            RabbitMQConsumerEvent::BEFORE_CONSUME,
            new RabbitMQConsumerEvent(
                [
                    'message' => $msg,
                    'consumer' => $this,
                ]
            )
        );

        try {
            // deserialize message back to initial data type
            if ($msg->has('application_headers') &&
                isset($msg->get('application_headers')->getNativeData()['rabbitmq.serialized'])) {
                $msg->setBody(call_user_func($this->getDeserializer(), $msg->getBody(), $this->getDeserializerParams()));
            }
            // process message and return the result code back to broker
            $processFlag = $callback($msg);
            $this->sendResult($msg, $processFlag);
            Yii::$app->rabbitmq->trigger(
                RabbitMQConsumerEvent::AFTER_CONSUME,
                new RabbitMQConsumerEvent(
                    [
                        'message' => $msg,
                        'consumer' => $this,
                    ]
                )
            );

            $this->logger->printResult($queueName, $processFlag, $timeStart);
            $this->logger->log(
                'Queue message processed.',
                $msg,
                [
                    'queue' => $queueName,
                    'processFlag' => $processFlag,
                    'timeStart' => $timeStart,
                    'memory' => true,
                ]
            );
        } catch (Throwable $e) {
            $this->logger->logError($e, $msg);
            if (!$this->proceedOnException) {
                throw $e;
            }
        }
        $this->consumed++;

        return true;
    }

    /**
     * Mark message status based on return code from callback
     *
     * @param AMQPMessage $msg
     * @param             $processFlag
     */
    protected function sendResult(AMQPMessage $msg, $processFlag): void
    {
        // true in testing environment
        if (!isset($msg->delivery_info['channel'])) {
            return;
        }
        /**
         * @var $channel AMQPChannel
         */
        $channel = $msg->delivery_info['channel'];
        // respond to the broker with appropriate reply code
        if ($processFlag === ConsumerInterface::MSG_REQUEUE || false === $processFlag) {
            // Reject and requeue message to RabbitMQ
            $channel->basic_reject($msg->delivery_info['delivery_tag'], true);
        } elseif ($processFlag === ConsumerInterface::MSG_REJECT) {
            // Reject and drop
            $channel->basic_reject($msg->delivery_info['delivery_tag'], false);
        } else {
            // Remove message from queue only if callback return not false
            $channel->basic_ack($msg->delivery_info['delivery_tag']);
        }
    }

    /**
     * Checks if memory in use is greater or equal than memory allowed for this process
     *
     * @return boolean
     */
    protected function isRamAlmostOverloaded(): bool
    {
        return memory_get_usage(true) >= ($this->getMemoryLimit() * 1024 * 1024);
    }

    /**
     * @param string $queueName
     *
     * @return string
     */
    protected function getConsumerTag(string $queueName): string
    {
        return sprintf('%s-%s-%s', $queueName, $this->name, $this->id);
    }

    /**
     * @return string
     */
    protected function generateUniqueId(): string
    {
        return uniqid('rabbitmq_', true);
    }

    protected function setup(): void
    {
        $this->resetConsumed();
        if ($this->autoDeclare) {
            $this->routing->declareAll();
        }
        $this->setQosOptions();
        $this->startConsuming();
    }
}
