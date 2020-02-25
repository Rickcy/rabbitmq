<?php
/**
 * Date: 08.11.19
 * Time: 1:01
 */

namespace rickcy\rabbitmq\components;


class Properties extends AdditionalProperties
{

    /**
     * Properties constructor.
     */
    public function __construct($config = [])
    {
        foreach ($config as $name => $value) {
            $this->$name = $value;
        }
    }
}