<?php declare(strict_types=1);

namespace rickcy\rabbitmq\events;

use yii\base\Event;

class RabbitMQPublisherEvent extends Event
{
    const BEFORE_PUBLISH = 'before_publish';
    const AFTER_PUBLISH  = 'after_publish';

    /**
     * @var AMQPMessage
     */
    public $message;

    /**
     * @var Consumer
     */
    public $producer;
}
