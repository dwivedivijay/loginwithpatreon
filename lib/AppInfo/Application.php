<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Vijay <vijaydwivedics@gmail.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\LoginWithPatreon\AppInfo;

use OCA\LoginWithPatreon\AlternativeLogin\DefaultLoginShow;
use OCA\LoginWithPatreon\Db\ConnectedLoginMapper;
use OCA\LoginWithPatreon\Service\ProviderService;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IConfig;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IUserSession;
use OCP\User\Events\BeforeUserDeletedEvent;
use OCP\User\Events\UserLoggedOutEvent;
use OCP\Util;
use OCP\AppFramework\Http\JSONResponse;

class Application extends App implements IBootstrap{
	public const APP_ID = 'loginwithpatreon';
    public const APP_NAME = 'Login With Patreon';

	private $appName = 'loginwithpatreon';
    private $regContext;

	public function __construct() {
		parent::__construct(self::APP_ID);
        //new JSONResponse(['message' => '__construct init']);
        //throw new \Exception('construct init 123');
	}

    public function register(IRegistrationContext $context): void
    {
        require __DIR__ . '/../../3rdparty/autoload.php';

        $this->regContext = $context;
    }

    public function boot(IBootContext $context): void
    {
        //throw new \Exception('boot 1 - ' . PHP_SAPI);
        Util::addStyle(self::APP_ID, 'styles');

        $l = $this->query(IL10N::class);
        $config = $this->query(IConfig::class);

        $dispatcher = $this->query(IEventDispatcher::class);
        $dispatcher->addListener(BeforeUserDeletedEvent::class, [$this, 'preDeleteUser']);

        $userSession = $this->query(IUserSession::class);
        if ($userSession->isLoggedIn()) {
            $uid = $userSession->getUser()->getUID();
            $session = $this->query(ISession::class);
            if ($config->getUserValue($uid, self::APP_ID, 'disable_password_confirmation')) {
                $session->set('last-password-confirm', time());
            }
            if ($logoutUrl = $session->get('loginwithpatreon_logout_url')) {
                $dispatcher->addListener(UserLoggedOutEvent::class, function () use ($logoutUrl) {
                    header('Location: ' . $logoutUrl);
                    exit();
                });
            }
            return;
        }

        $providerService = $this->query(ProviderService::class);
        $request = $this->query(IRequest::class);

        $providersCount = 0;
        $loginClass = '';

        /*Fatal   no app in context   Exception: boot 1 - {"PatreonLogin":{"clientId":"rCuDrDMIVCjiGKQ_X4-Cj4vbqf1oAaz2aF7biFeAIl7Irivfvo1qhc4AvrD40jzK","clientSecret":"6M_A3dj3MRWzvRItiCiCyroXHDOuOFQ4agePVcrVeHFK0SMiVUXBJ2QIn4D1mAzh"}}*/

        $providers = json_decode($config->getAppValue(self::APP_ID, 'oauth_providers'), true) ?: [];
        //throw new \Exception('boot 1 - ' . json_encode($providers));
        foreach ($providers as $name => $provider) {
            if ($provider['clientId']) {
                ++$providersCount;
                $loginClass = $providerService->getLoginClass($name);
                $this->regContext->registerAlternativeLogin($loginClass);
            }
        }

        /*
        $providers = json_decode($config->getAppValue(self::APP_ID, 'custom_providers'), true) ?: [];
        foreach ($providers as $providersType => $providerList) {
            foreach ($providerList as $provider) {
                ++$providersCount;
                $loginClass = $providerService->getLoginClass($provider['name'], $provider, $providersType);
                $this->regContext->registerAlternativeLogin($loginClass);
            }
        }*/

        if (PHP_SAPI !== 'cli') {
            $useLoginRedirect = $providersCount === 1
                && $request->getMethod() === 'GET'
                && !$request->getParam('noredir')
                && $config->getSystemValue('patreon_login_auto_redirect', false);
            if ($useLoginRedirect && $request->getPathInfo() === '/login') {
                $login = $this->query($loginClass);
                $login->load();
                header('Location: ' . $login->getLink());
                exit();
            }

            $hideDefaultLogin = $providersCount > 0 && $config->getAppValue(self::APP_ID, 'hide_default_login');
            if ($hideDefaultLogin && $request->getPathInfo() === '/login') {
                $this->regContext->registerAlternativeLogin(DefaultLoginShow::class);
            }
        }
    }

    public function preDeleteUser(BeforeUserDeletedEvent $event)
    {
        $user = $event->getUser();
        $this->query(ConnectedLoginMapper::class)->disconnectAll($user->getUID());
    }

    private function query($className)
    {
        return $this->getContainer()->get($className);
    }

}
