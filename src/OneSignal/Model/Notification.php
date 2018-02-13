<?php

namespace OneSignal\Model;

use C\Event\AbstractEvent;
use OneSignal\Model\Notification\Content;

class Notification
{
    /**
     * Specific recipients to send your notification to.
     *
     * @var array
     */
    protected $recipients = [];

    /**
     * @var Content[]
     */
    protected $contents = [];

    /**
     * Delivery priority through the push server (example GCM/FCM). Pass 10 for high priority.Defaults to normal
     * priority for Android and high for iOS. For Android 6.0+ devices setting priority to high will wake the
     * device out of doze mode.
     *
     * @var int
     */
    protected $priority = 10;

    /**
     * Time To Live - In seconds. The notification will be expired if the device does not come back online within
     * this time. The default is 259,200 seconds (3 days).
     *
     * @var int
     */
    protected $ttl = 259200;

    /**
     * @var AbstractEvent
     */
    protected $event;

    /**
     * @return array
     */
    public function getRecipients()
    {
        return $this->recipients;
    }

    /**
     * @param $recipient string
     * @return $this
     */
    public function addRecipient($recipient)
    {
        $this->recipients[] = $recipient;

        return $this;
    }

    /**
     * @param array $recipients
     * @return Notification
     */
    public function setRecipients($recipients)
    {
        $this->recipients = $recipients;

        return $this;
    }

    /**
     * @return Content[]
     */
    public function getContents()
    {
        return $this->contents;
    }

    /**
     * @param Content[] $contents
     * @return Notification
     */
    public function setContents($contents)
    {
        $this->contents = $contents;
        return $this;
    }

    /**
     * @param Content $content
     * @return $this
     */
    public function addContent(Content $content)
    {
        $this->contents[] = $content;

        return $this;
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @param int $priority
     * @return Notification
     */
    public function setPriority($priority)
    {
        $this->priority = $priority;
        return $this;
    }

    /**
     * @return int
     */
    public function getTtl()
    {
        return $this->ttl;
    }

    /**
     * @param int $ttl
     * @return Notification
     */
    public function setTtl($ttl)
    {
        $this->ttl = $ttl;
        return $this;
    }

    /**
     * @return AbstractEvent
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @param AbstractEvent $event
     * @return Notification
     */
    public function setEvent($event)
    {
        $this->event = $event;
        return $this;
    }
}
