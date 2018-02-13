<?php

namespace C\Provider;

use Knp\Console\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

class CommandProvider implements ServiceProviderInterface
{
    public function register(Container $app)
    {
        /** @var SplFileInfo $file */
        foreach ((new Finder())->files()->name('*Command.php')->in($app['root.dir'] . '/src') as $file) {
            $class = '\\' . str_replace('/', '\\', $file->getRelativePath()) . '\\' . $file->getBasename('.php');

            $r = new \ReflectionClass($class);

            if ($r->isSubclassOf('Symfony\\Component\\Console\\Command\\Command')
                && !$r->isAbstract()
                && !$r->getConstructor()->getNumberOfRequiredParameters()
            ) {
                $app->extend('console', function (Application $console) use ($class) {
                    $console->add(new $class());

                    return $console;
                });
            }
        }
    }
}
