<?php declare(strict_types=1);

namespace rickcy\rabbitmq\events;

use PhpAmqpLib\Message\AMQPMessage;
use rickcy\rabbitmq\components\Consumer;
use yii\base\Event;

class RabbitMQConsumerEvent extends Event
{
    const BEFORE_CONSUME = 'before_consume';
    const AFTER_CONSUME  = 'after_consume';

    /**
     * @var AMQPMessage
     */
    public $message;

    /**
     * @var Consumer
     */
    public $consumer;
}
