<?php

namespace C\Provider;

use C\Controller;
use Doctrine\Common\Inflector\Inflector;
use Pimple\Container;
use Pimple\ServiceProviderInterface;

class ControllerProvider implements ServiceProviderInterface
{
    const CONTROLLERS = [
        Controller\ResetPasswordController::class,
        Controller\Event\ParticipantController::class,
        Controller\Event\ExpenseController::class,
        Controller\Event\ElementController::class,
        Controller\Event\CarController::class,
        Controller\EventController::class,
        Controller\Chiller\HomeController::class,
        Controller\ChillerController::class,
        Controller\ChillController::class,
        Controller\Chill\CustomController::class,
        Controller\Chill\Custom\CarController::class,
        Controller\Chill\Custom\ExpenseController::class,
        Controller\Chill\Custom\ElementController::class,
        Controller\Chill\Custom\ParticipantController::class,
    ];

    const CONTROLLERS_DEV = [
        Controller\DebugController::class,
    ];

    public function register(Container $app)
    {
        foreach (self::CONTROLLERS as $controllerClass) {
            $this->registerController($app, $controllerClass);
        }

        if ($app['debug']) {
            foreach (self::CONTROLLERS_DEV as $controllerClass) {
                $this->registerController($app, $controllerClass);
            }
        }
    }

    protected function registerController(Container $app, $fullyQualifiedClassName)
    {
        $serviceId = $this->getControllerServiceIdentifier($fullyQualifiedClassName);

        $app[$serviceId] = function () use ($fullyQualifiedClassName, $app) {
            return new $fullyQualifiedClassName($app);
        };
    }

    protected function getControllerServiceIdentifier($fullyQualifiedClassName)
    {
        $serviceName = '';
        $tree = explode("\\", substr($fullyQualifiedClassName, strlen("C\\Controller\\")));
        $controllerName = Inflector::tableize(array_pop($tree));

        foreach ($tree as $value) {
            $serviceName .= Inflector::tableize($value) . '.';
        }

        return $serviceName . $controllerName;
    }
}
