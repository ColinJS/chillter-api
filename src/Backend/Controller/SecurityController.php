<?php

namespace Backend\Controller;

use C\Application;
use Symfony\Component\HttpFoundation\Request;

class SecurityController extends AbstractController
{
    public function loginAction(Request $request, Application $application)
    {
        return $this->twig->render('login.html.twig', array(
            'showNavbar' => false,
            'error' => $application['security.last_error']($request),
            'last_username' => $application['session']->get('_security.last_username'),
        ));
    }
}
