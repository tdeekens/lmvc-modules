<?php

namespace Scandio\lmvc\modules\security\controllers;

use Scandio\lmvc\LVC;
use Scandio\lmvc\modules\security\AnonymousController;
use Scandio\lmvc\modules\security\Security as SecurityPrincipal;

class Security extends AnonymousController
{
    /**
     * @return bool
     */
    public static function index()
    {
        return static::redirect('Security::login');
    }

    /**
     * @return bool
     */
    public static function login()
    {
        return static::render();
    }

    /**
     * @return bool
     */
    public static function postLogin()
    {
        $principal = SecurityPrincipal::get();
        if ($principal->authenticate(static::request()->username, static::request()->password)) {
            $_SESSION['security']['current_user'] = static::request()->username;
            $_SESSION['security']['authenticated'] = true;
            $controllerAction = $_SESSION['security']['called_before_login']['controller'] .
                '::' . $_SESSION['security']['called_before_login']['action'];
            $params = $_SESSION['security']['called_before_login']['params'];
            unset($_SESSION['security']['called_before_login']);
            return static::redirect($controllerAction, $params);
        } else {
            return static::redirect('Security::login');
        }
    }

    /**
     * @return bool
     */
    public static function logout()
    {
        session_unset();
        session_destroy();
        $logoutAction = (isset(LVC::get()->config->security->logoutAction)) ? LVC::get()->config->security->logoutAction : 'Application::index';
        return static::redirect($logoutAction);
    }

    /**
     * @return bool
     */
    public static function forbidden()
    {
        header('HTTP/1.0 403 Forbidden');
        return static::render();
    }
}