<?php

namespace C\Provider;

use C\Resolver\TranslationResolver;
use C\Tools\TranslatorExtension;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;

class TranslationProvider implements ServiceProviderInterface
{
    /**
     * @var array   List of locales available for translation service
     */
    private static $availableLocales = ['fr', 'en'];

    /**
     * @var array
     */
    protected $files = [];

    public function register(Container $app)
    {
        $app['available_translations'] = function () {
            $availableTranslations = [];

            foreach ($this->getTranslationFilesList() as $file) {
                $availableTranslations[] = explode('.', pathinfo($file, PATHINFO_BASENAME))[1];
            }

            return $availableTranslations;
        };

        $app->extend('translator', function (Translator $translator) use ($app) {
            $translator->addLoader('yaml', new YamlFileLoader());

            foreach ($this->getTranslationFilesList() as $file) {
                $pathInfo = pathinfo($file);
                list($domain, $locale) = explode('.', $pathInfo['basename']);
                $translator->addResource('yaml', $file, $locale, $domain);
            }

            return $translator;
        });

        // Investigate 'Accept-Langauge' header in order to set translation service locale.
        //=================================================================================

        /** @var Application $app */
        $app->before(function (Request $request, Application $app) {
            $this->insertPrimaryLanguage($request);
            $app['translator']->setLocale($request->getPreferredLanguage(self::$availableLocales));
        });

        // Register extended translation service for Chillter use.
        //========================================================
        $app['translator.extension'] = function ($app) {
            return new TranslatorExtension($app['translator']);
        };
    }

    protected function getTranslationFilesList()
    {
        if (!$this->files) {
            $translationDirectory = __DIR__ . '/../Resources/translations/';

            if (!file_exists($translationDirectory)) {
                throw new \LogicException("Directory \"$translationDirectory\" does not exist.");
            }

            foreach (new \DirectoryIterator($translationDirectory) as $directoryIterator) {
                if (!$directoryIterator->isFile()
                    || 'yml' !== $directoryIterator->getExtension()
                ) {
                    continue;
                }

                $this->files[] = $directoryIterator->getPathname();
            }
        }

        return $this->files;
    }

    /**
     * Inserts primary language (French) into Accept-Language header if does not exist yet.
     *
     * @param Request $request
     */
    protected function insertPrimaryLanguage(Request &$request)
    {
        $header = $request->headers->get('Accept-Language');

        if (!preg_match('/fr-FR,fr/', $header)) {
            $request->headers->set('Accept-Language', $header . ';fr-FR,fr');
        }
    }
}
