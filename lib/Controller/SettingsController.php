<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Vijay <vijaydwivedics@gmail.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

namespace OCA\LoginWithPatreon\Controller;

use OCA\LoginWithPatreon\AppInfo\Application;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\JSONResponse;
use OCP\AppFramework\Http\RedirectResponse;

use OCP\IRequest;
use OCP\Util;

use OCP\IL10N;
use OCP\IConfig;
use OCP\IURLGenerator;
use OCP\IUserSession;

class SettingsController extends Controller {
	/** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IUserSession */
    private $userSession;
    /** @var IL10N */
    private $l;
    /** @var ConnectedLoginMapper */
    private $socialConnect;

	/*public function __construct(IRequest $request) {
		parent::__construct(Application::APP_ID, $request);
	}
    //ConnectedLoginMapper $socialConnect
	*/

	public function __construct(
        $appName,
        IRequest $request,
        IConfig $config,
        IURLGenerator $urlGenerator,
        IUserSession $userSession,
        IL10N $l
    ) {
        parent::__construct(Application::APP_ID, $request);
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->userSession = $userSession;
        $this->l = $l;
        //$this->socialConnect = $socialConnect;
    }


	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		Util::addScript(Application::APP_ID, 'loginwithpatreon-main');

		return new TemplateResponse(Application::APP_ID, 'main');
	}

	public function saveAdmin($options, $providers, $custom_providers) {
        //return new JSONResponse($custom_providers);

        /*foreach ($options as $k => $v) {
            $this->config->setAppValue($this->appName, $k, $v ? true : false);
        }*/

        $this->config->setAppValue($this->appName, 'oauth_providers', json_encode($providers));

        /*
        if (is_array($custom_providers)) {
            try {
                $names = array_keys($providers);
                foreach ($custom_providers as $provType => $provs) {
                    $this->checkProviders($provs, $names);
                    $custom_providers[$provType] = array_values($provs);
                }
            } catch (\Exception $e) {
                return new JSONResponse(['message' => $e->getMessage()]);
            }
        }
        $this->config->setAppValue($this->appName, 'custom_providers', json_encode($custom_providers));
        */
        return new JSONResponse(['success' => true]);
    }

    
}
