<?php

namespace OneSignal\Model\Notification;

class Content
{
    /**
     * @var string
     */
    protected $languageCode;

    /**
     * The notification's content (excluding the title)
     *
     * @var string
     */
    protected $content;

    /**
     * The notification's title
     *
     * @var string
     */
    protected $heading;

    /**
     * @return string
     */
    public function getLanguageCode()
    {
        return $this->languageCode;
    }

    /**
     * @param string $languageCode
     * @return $this
     */
    public function setLanguageCode($languageCode)
    {
        if (!in_array($languageCode, ['en', 'ar', 'ca', 'zh-Hans', 'zh-Hant', 'hr', 'cs', 'da', 'nl', 'et',
            'fi', 'fr', 'ka', 'bg', 'de', 'el', 'hi', 'he', 'hu', 'id', 'it', 'js', 'ko', 'lv', 'lt', 'ms',
            'nb', 'fa', 'pl', 'pt', 'ro', 'ru', 'sr', 'sk', 'es', 'sv', 'th', 'tr', 'uk', 'vi' ])
        ) {
            throw new \InvalidArgumentException("Language code \"$languageCode\" is not valid.");
        }

        $this->languageCode = $languageCode;

        return $this;
    }

    /**
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @param string $content
     * @return $this
     */
    public function setContent($content)
    {
        $this->content = $content;

        return $this;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param string $heading
     * @return $this
     */
    public function setHeading($heading)
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @return bool
     */
    public function isValid()
    {
        return !!$this->languageCode && !!$this->content;
    }
}
