<?php declare(strict_types=1);

namespace rickcy\rabbitmq\components;

use PhpAmqpLib\Connection\AbstractConnection;
use rickcy\rabbitmq\events\RabbitMQPublisherEvent;
use rickcy\rabbitmq\exceptions\RuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

/**
 * Service that sends AMQP Messages
 *
 * @package rickcy\rabbitmq\components
 *
 * @property array $basicProperties
 */
class Producer extends BaseRabbitMQ
{
    protected $contentType;

    protected $deliveryMode;

    protected $serializer;

    protected $safe;

    protected $name = 'unnamed';

    /**
     * Producer constructor.
     * @param AbstractConnection $conn
     * @param Routing $routing
     * @param Logger $logger
     * @param bool $autoDeclare
     * @throws \yii\base\InvalidConfigException
     */
    public function __construct(AbstractConnection $conn, Routing $routing, Logger $logger, bool $autoDeclare)
    {
        parent::__construct($conn, $routing, $logger, $autoDeclare);

        $this->additionalProperties = \Yii::createObject([
            'class' => AdditionalProperties::class,
        ]);
    }


    public function getAdditionalProperties()
    {
        return $this->additionalProperties;
    }

    /**
     * @var AdditionalProperties
     */
    protected $additionalProperties;

    /**
     * @param $contentType
     */
    public function setContentType($contentType)
    {
        $this->contentType = $contentType;
    }

    /**
     * @param $deliveryMode
     */
    public function setDeliveryMode($deliveryMode)
    {
        $this->deliveryMode = $deliveryMode;
    }

    /**
     * @param callable $serializer
     */
    public function setSerializer(callable $serializer)
    {
        $this->serializer = $serializer;
    }

    /**
     * @return callable
     */
    public function getSerializer(): callable
    {
        return $this->serializer;
    }

    /**
     * @return array
     */
    public function getBasicProperties(): array
    {
        return [
            'content_type' => $this->contentType,
            'delivery_mode' => $this->deliveryMode,
        ];
    }

    /**
     * @return mixed
     */
    public function getSafe(): bool
    {
        return $this->safe;
    }

    /**
     * @param mixed $safe
     */
    public function setSafe(bool $safe)
    {
        $this->safe = $safe;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * Publishes the message and merges additional properties with basic properties
     *
     * @param mixed $msgBody
     * @param string $exchangeName
     * @param string $routingKey
     * @param array $headers
     */
    public function publish(
        $msgBody,
        string $exchangeName,
        string $routingKey = '',
        array $headers = null
    )
    {
        if ($this->autoDeclare) {
            $this->routing->declareAll();
        }
        if ($this->safe && !$this->routing->isExchangeExists($exchangeName)) {
            throw new RuntimeException(
                "Exchange `{$exchangeName}` does not declared in broker (You see this message because safe mode is ON)."
            );
        }
        $serialized = false;
        if (!is_string($msgBody)) {
            $msgBody = call_user_func($this->serializer, $msgBody);
            $serialized = true;
        }
        $msg = new AMQPMessage($msgBody, array_merge($this->getBasicProperties(), $this->additionalProperties->properties));

        if (!empty($headers) || $serialized) {
            if ($serialized) {
                $headers['rabbitmq.serialized'] = 1;
            }
            $headersTable = new AMQPTable($headers);
            $msg->set('application_headers', $headersTable);
        }

        \Yii::$app->rabbitmq->trigger(
            RabbitMQPublisherEvent::BEFORE_PUBLISH,
            new RabbitMQPublisherEvent(
                [
                    'message' => $msg,
                    'producer' => $this,
                ]
            )
        );

        $this->getChannel()->basic_publish($msg, $exchangeName, $routingKey);

        \Yii::$app->rabbitmq->trigger(
            RabbitMQPublisherEvent::AFTER_PUBLISH,
            new RabbitMQPublisherEvent(
                [
                    'message' => $msg,
                    'producer' => $this,
                ]
            )
        );

        $this->logger->log(
            'AMQP message published',
            $msg,
            [
                'exchange' => $exchangeName,
                'routing_key' => $routingKey,
            ]
        );
    }
}
