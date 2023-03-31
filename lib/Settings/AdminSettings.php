<?php
/**
 * @copyright Copyright (c) 2017 Robin Appelman <robin@icewind.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace OCA\LoginWithPatreon\Settings;

use OCA\LoginWithPatreon\AppInfo\Application;
//use OCA\LoginWithPatreon\Service\ConfigProxy;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IL10N;
use OCP\ILogger;
use OCP\Settings\ISettings;
use OCP\Util;
use OCP\IGroupManager;
use OCP\IURLGenerator;

class AdminSettings implements ISettings {

	private IL10N $l;
    private IConfig $config;

    /** @var string */
    private $appName;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var IGroupManager */
    private $groupManager;

	public function __construct( IConfig $config, IL10N $l, $appName, IURLGenerator $urlGenerator, IGroupManager $groupManager) {
        $this->config = $config;
        $this->l = $l;

        $this->appName = $appName;
        $this->urlGenerator = $urlGenerator;
        $this->groupManager = $groupManager;
    }

	/**
	 * @return TemplateResponse
	 */
	public function getForm() {

		Util::addScript($this->appName, 'settings');
        //Util::addScript($this->appName, 'admin');

        $groupNames = [];
        $groups = $this->groupManager->search('');
        foreach ($groups as $group) {
            $groupNames[] = $group->getGid();
        }
        $providers = [];
        $savedProviders = json_decode($this->config->getAppValue($this->appName, 'oauth_providers'), true) ?: [];
        /*foreach (ProviderService::DEFAULT_PROVIDERS as $provider) {
            if (array_key_exists($provider, $savedProviders)) {
                $providers[$provider] = $savedProviders[$provider];
            } else {
                $providers[$provider] = [
                    'appid' => '',
                    'secret' => '',
                ];
            }
        }*/

        $provider = 'PatreonLogin';
        if (array_key_exists($provider, $savedProviders)) {
            $providers[$provider] = $savedProviders[$provider];
        } else {
            $providers[$provider] = [
                'clientId' => '1',
                'clientSecret' => '2',
            ];
        }
        //print_r($providers[$provider]);exit;

        $customProviders = json_decode($this->config->getAppValue($this->appName, 'custom_providers'), true);

        $params = [
            'app_name' => $this->appName,
            'action_url' => $this->urlGenerator->linkToRoute($this->appName.'.settings.saveAdmin'),
            //'groups' => $groupNames,
            //'custom_providers' => $customProviders,
            'providers' => $providers,
        ];

        /*foreach (ProviderService::OPTIONS as $paramName) {
            $params['options'][$paramName] = !!$this->config->getAppValue($this->appName, $paramName);
        }*/
        return new TemplateResponse($this->appName, 'admin', $params);

	}

	/**
	 * @return string the section ID, e.g. 'sharing'
	 */
	public function getSection() {
		return Application::APP_ID;
	}

	/**
	 * @return int whether the form should be rather on the top or bottom of
	 * the admin section. The forms are arranged in ascending order of the
	 * priority values. It is required to return a value between 0 and 100.
	 *
	 * E.g.: 70
	 */
	public function getPriority() {
		return 92;
	}
}
