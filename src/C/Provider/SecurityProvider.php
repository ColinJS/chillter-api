<?php

namespace C\Provider;

use C\Application;
use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Translation\Exception\LogicException;

class SecurityProvider implements ServiceProviderInterface
{
    protected $userID;

    /**
     * Perform security check
     *
     * @param Container $app
     */
    public function register(Container $app)
    {
        if (!$app instanceof Application) {
            throw new LogicException('Container must be an instance of Application.');
        }

        $app->before(function (Request $request) use ($app) {
            if (0 === strpos($request->headers->get('Content-Type'), 'application/json')) {
                $data = \GuzzleHttp\json_decode($request->getContent(), true);
                $app['chillter.json.req'] = is_array($data) ? $data : [];
            }

            if ($this->isHandledBySecurityComponent($request)) {
                return;
            }

            if ($this->isAnonymousPath($request, $app)) {
                return;
            }

            if (!($this->isAuthenticated($request, $app) && $this->isAuthorized($request))) {
                throw new AccessDeniedHttpException('Invalid token.');
            }

            return;
        });
    }

    protected function isHandledBySecurityComponent(Request $request)
    {
        return 0 === strpos($request->getPathInfo(), '/backend');
    }

    /**
     * @param Request $request
     * @param Container $app
     * @return bool
     */
    protected function isAnonymousPath(Request $request, Container $app)
    {
        $anonymousPaths = [
            ['POST', '/login'],
            ['POST', '/chillers'],
            ['GET', '/chills/' . $request->get('chillId')],
            ['GET', '/chills'],
            ['POST', '/reset_password/obtain_token'],
            ['POST', '/reset_password/verify'],
            ['POST', '/reset_password/set'],
        ];

        if ($app['debug']) {
            $anonymousPaths[] = ['GET', '/'];
        }

        return in_array([$request->getMethod(), $request->getPathInfo()], $anonymousPaths);
    }

    protected function isAuthorized(Request $request)
    {
        $userID = (int)$request->get('userId') ? : null;

        if ($userID) {
            return $userID === $this->userID;
        }

        return true;
    }

    /**
     * @param Request $request
     * @param Container $app
     * @return bool
     */
    protected function isAuthenticated(Request $request, Container $app)
    {
        $this->userID = (int)$app['db']->fetchColumn("SELECT `id` FROM `chiller` WHERE `bearer` = ? ", [
            $request->headers->get('X-Token')
        ]) ? : null;


        return !!$this->userID;
    }
}
