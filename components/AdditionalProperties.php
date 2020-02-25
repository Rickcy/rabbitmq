<?php


namespace rickcy\rabbitmq\components;


use ReflectionClass;
use ReflectionException;
use yii\base\BaseObject;

/**
 * @property mixed $contentType
 * @property mixed $correlationId
 * @property mixed $contentEncoding
 * @property mixed $replyTo
 * @property mixed $appId
 * @property mixed $deliveryMode
 * @property mixed $userId
 * @property mixed $applicationHeaders
 * @property mixed $clusterId
 * @property mixed $messageId
 * @property mixed $properties
 */
abstract class AdditionalProperties extends BaseObject
{
    protected $content_type;

    protected $content_encoding;

    protected $application_headers;

    protected $delivery_mode;

    protected $priority;

    protected $correlation_id;

    protected $reply_to;

    protected $expiration;

    protected $message_id;

    protected $timestamp;

    protected $type;

    protected $user_id;

    protected $app_id;

    protected $cluster_id;


    /**
     * @param mixed $content_type
     *
     * @return AdditionalProperties
     */
    public function setContentType($content_type) : self
    {
        $this->content_type = $content_type;

        return $this;
    }

    /**
     * @param mixed $content_encoding
     *
     * @return AdditionalProperties
     */
    public function setContentEncoding($content_encoding) : self
    {
        $this->content_encoding = $content_encoding;

        return $this;
    }

    /**
     * @param mixed $application_headers
     *
     * @return AdditionalProperties
     */
    public function setApplicationHeaders($application_headers) : self
    {
        $this->application_headers = $application_headers;

        return $this;
    }

    /**
     * @param mixed $delivery_mode
     *
     * @return AdditionalProperties
     */
    public function setDeliveryMode($delivery_mode) : self
    {
        $this->delivery_mode = $delivery_mode;

        return $this;
    }

    /**
     * @param mixed $priority
     *
     * @return AdditionalProperties
     */
    public function setPriority($priority) : self
    {
        $this->priority = $priority;

        return $this;
    }

    /**
     * @param mixed $correlation_id
     *
     * @return AdditionalProperties
     */
    public function setCorrelationId($correlation_id) : self
    {
        $this->correlation_id = $correlation_id;

        return $this;
    }

    /**
     * @param mixed $reply_to
     *
     * @return AdditionalProperties
     */
    public function setReplyTo($reply_to) : self
    {
        $this->reply_to = $reply_to;

        return $this;
    }

    /**
     * @param mixed $expiration
     *
     * @return AdditionalProperties
     */
    public function setExpiration($expiration) : self
    {
        $this->expiration = $expiration;

        return $this;
    }

    /**
     * @param mixed $message_id
     *
     * @return AdditionalProperties
     */
    public function setMessageId($message_id) : self
    {
        $this->message_id = $message_id;

        return $this;
    }

    /**
     * @param mixed $timestamp
     *
     * @return AdditionalProperties
     */
    public function setTimestamp($timestamp) : self
    {
        $this->timestamp = $timestamp;

        return $this;
    }

    /**
     * @param mixed $type
     *
     * @return AdditionalProperties
     */
    public function setType($type) : self
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param mixed $user_id
     *
     * @return AdditionalProperties
     */
    public function setUserId($user_id) : self
    {
        $this->user_id = $user_id;

        return $this;
    }

    /**
     * @param mixed $app_id
     *
     * @return AdditionalProperties
     */
    public function setAppId($app_id) : self
    {
        $this->app_id = $app_id;

        return $this;
    }

    /**
     * @param mixed $cluster_id
     *
     * @return AdditionalProperties
     */
    public function setClusterId($cluster_id) : self
    {
        $this->cluster_id = $cluster_id;

        return $this;
    }


    /**
     * Returns attribute values.
     * @param array $names list of attributes whose value needs to be returned.
     * Defaults to null, meaning all attributes listed in [[attributes()]] will be returned.
     * If it is an array, only the attributes in the array will be returned.
     * @param array $except list of attributes whose value should NOT be returned.
     * @return array attribute values (name => value).
     * @throws ReflectionException
     */
    public function getProperties($names = null, $except = []): array
    {
        $values = [];
        if ($names === null) {
            $names = $this->attributes();
        }
        foreach ($names as $name) {
            $values[$name] = $this->$name;
        }
        foreach ($except as $name) {
            unset($values[$name]);
        }

        return $values;
    }


    /**
     * Returns the list of attribute names.
     * By default, this method returns all public non-static properties of the class.
     * You may override this method to change the default behavior.
     * @return array list of attribute names.
     * @throws ReflectionException
     */
    public function attributes(): array
    {
        $class = new ReflectionClass($this);
        $names = [];
        foreach ($class->getProperties() as $property) {
            if (!$property->isStatic()) {
                $names[] = $property->getName();
            }
        }

        return $names;
    }

    /**
     * @return mixed
     */
    public function getCorrelationId()
    {
        return $this->correlation_id;
    }

    /**
     * @return mixed
     */
    public function getReplyTo()
    {
        return $this->reply_to;
    }




}