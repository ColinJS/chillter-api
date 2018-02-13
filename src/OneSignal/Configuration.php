<?php

namespace OneSignal;

use Doctrine\Common\Util\Inflector;

class Configuration
{
    /**
     * Protocol - http or https
     *
     * @var string
     */
    protected $protocol = 'https';

    /**
     * Fully qualified domain name
     *
     * @var string
     */
    protected $host = 'onesignal.com';

    /**
     * API version number
     *
     * @var int
     */
    protected $version = 1;

    /**
     * REST API key
     *
     * @var string
     */
    protected $key;

    /**
     * OneSignal App ID
     *
     * @var string
     */
    protected $applicationId;

    /**
     * Configuration constructor
     *
     * @param array $configuration
     */
    public function __construct(array $configuration = array())
    {
        foreach ($configuration as $property => $value) {
            $methodName = 'set' . ucfirst(Inflector::camelize($property));

            if (!method_exists($this, $methodName)) {
                throw new \LogicException("Method \"$methodName\" does not exist in class " . get_class($this) . ".");
            }

            $this->$methodName($value);
        }
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        return $this->protocol;
    }

    /**
     * @param string $protocol
     * @return Configuration
     */
    public function setProtocol($protocol)
    {
        $this->protocol = $protocol;
        return $this;
    }

    /**
     * @return string
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @param string $host
     * @return Configuration
     */
    public function setHost($host)
    {
        $this->host = $host;
        return $this;
    }

    /**
     * @return int
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @param int $version
     * @return Configuration
     */
    public function setVersion($version)
    {
        $this->version = $version;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param mixed $key
     * @return Configuration
     */
    public function setKey($key)
    {
        $this->key = $key;
        return $this;
    }

    /**
     * @return string
     */
    public function getApplicationId()
    {
        return $this->applicationId;
    }

    /**
     * @param string $applicationId
     * @return Configuration
     */
    public function setApplicationId($applicationId)
    {
        $this->applicationId = $applicationId;
        return $this;
    }
}
