<?php

namespace C\Tools;

use Symfony\Component\Translation\Translator;

/**
 * Custom translation extension class providing translation methods for specified situations.
 *
 * @author Wojciech Brzeziński <wojciech.brzezinski@db-team.pl>
 */
class TranslatorExtension
{
    /** @var Translator */
    protected $translator;

    /**
     * Constructor. Initializes translation service.
     *
     * @param Translator $translator
     */
    public function __construct(Translator $translator)
    {
        $this->translator = $translator;
    }

    /**
     * Returns vendor translation service.
     *
     * @return Translator
     */
    public function getTranslator()
    {
        return $this->translator;
    }

    /**
     * Common translation method.
     *
     * @param string $key
     *
     * @return string
     */
    public function trans($key)
    {
        return $this->translator->trans($key);
    }

    /**
     * Translates given chill's name.
     *
     * @param string $name
     *
     * @return string
     */
    public function transChill($name)
    {
        $key = 'chill.' . str_replace(' ', '', $name);

        return $this->exists($key) ? $this->translator->trans($key) : $name;
    }

    /**
     * Checks if key exists in translation files.
     *
     * @param $key
     *
     * @return bool
     */
    public function exists($key)
    {
        $locale     = $this->translator->getLocale();
        $catalogue  = $this->translator->getCatalogue($locale);

        return $catalogue->defines($key);
    }
}