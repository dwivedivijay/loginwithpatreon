<?php

namespace OCA\LoginWithPatreon\Service;

use Hybridauth\Provider;
use Hybridauth\User\Profile;
use Hybridauth\HttpClient\Curl;
use OC\Authentication\Token\IProvider;
use OC\User\LoginException;
use OCA\LoginWithPatreon\AlternativeLogin;
use OCA\LoginWithPatreon\AlternativeLogin\PatreonLogin;
//use OCA\LoginWithPatreon\Provider\CustomDiscourse;
//use OCA\LoginWithPatreon\Provider\CustomOAuth1;
use OCA\LoginWithPatreon\Provider\CustomOAuth2;
//use OCA\LoginWithPatreon\Provider\CustomOpenIDConnect;
use OCA\LoginWithPatreon\Db\ConnectedLoginMapper;
use OCP\Accounts\IAccountManager;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IAvatarManager;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IL10N;
use OCP\IRequest;
use OCP\ISession;
use OCP\IURLGenerator;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\Mail\IMailer;

class ProviderService
{
    const OPTIONS = [
        'disable_registration',
        'create_disabled_users',
        'allow_login_connect',
        'prevent_create_email_exists',
        'update_profile_on_login',
        'no_prune_user_groups',
        'auto_create_groups',
        'restrict_users_wo_mapped_groups',
        'restrict_users_wo_assigned_groups',
        'disable_notify_admins',
        'hide_default_login',
        'button_text_wo_prefix',
    ];
    const DEFAULT_PROVIDERS = [
        'google',
        'amazon',
        'facebook',
        'twitter',
        'GitHub',
        'discord',
        'QQ',
        'slack',
        'telegram',
        'mailru',
        'yandex',
        'BitBucket',
        'PlexTv',
    ];

    const TYPE_OPENID = 'openid';
    const TYPE_OAUTH1 = 'custom_oauth1';
    const TYPE_OAUTH2 = 'custom_oauth2';
    const TYPE_OIDC = 'custom_oidc';
    const TYPE_DISCOURSE = 'custom_discourse';

    const TYPE_CLASSES = [
        //self::TYPE_OPENID => Provider\OpenID::class,
        //self::TYPE_OAUTH1 => CustomOAuth1::class,
        self::TYPE_OAUTH2 => CustomOAuth2::class,
        //self::TYPE_OIDC => CustomOpenIDConnect::class,
        //self::TYPE_DISCOURSE => CustomDiscourse::class,
    ];

    private $configMapping = [
        'default' => [
            'keys' => [
                'id' => 'appid',
                'secret' => 'secret',
            ],
        ],
        self::TYPE_OPENID => [
            'openid_identifier' => 'url',
        ],
        self::TYPE_OAUTH1 => [
            'keys' => [
                'id'     => 'clientId',
                'secret' => 'clientSecret',
            ],
            'endpoints' => [
                'authorize_url'    => 'authorizeUrl',
                'access_token_url' => 'tokenUrl',
                'profile_url'    => 'profileUrl',
            ],
            'logout_url' => 'logoutUrl',
        ],
        self::TYPE_OAUTH2 => [
            'scope' => 'scope',
            'keys' => [
                'id'     => 'clientId',
                'secret' => 'clientSecret',
            ],
            'endpoints' => [
                'api_base_url'     => 'apiBaseUrl',
                'authorize_url'    => 'authorizeUrl',
                'access_token_url' => 'tokenUrl',
                'profile_url'    => 'profileUrl',
            ],
            'profile_fields' => 'profileFields',
            'displayname_claim' => 'displayNameClaim',
            'groups_claim'  => 'groupsClaim',
            'group_mapping' => 'groupMapping',
            'logout_url'    => 'logoutUrl',
        ],
        self::TYPE_OIDC => [
            'scope' => 'scope',
            'keys' => [
                'id'     => 'clientId',
                'secret' => 'clientSecret',
            ],
            'endpoints' => [
                'authorize_url'    => 'authorizeUrl',
                'access_token_url' => 'tokenUrl',
                'user_info_url'    => 'userInfoUrl',
            ],
            'displayname_claim' => 'displayNameClaim',
            'groups_claim'  => 'groupsClaim',
            'group_mapping' => 'groupMapping',
            'logout_url'    => 'logoutUrl',
        ],
        self::TYPE_DISCOURSE => [
            'keys' => [
                'secret' => 'ssoSecret',
            ],
            'endpoints' => [
                'base_url'    => 'baseUrl',
            ],
            'group_mapping' => 'groupMapping',
            'logout_url'    => 'logoutUrl',
        ],
    ];

    /** @var string */
    private $appName;
    /** @var IRequest */
    private $request;
    /** @var IConfig */
    private $config;
    /** @var IURLGenerator */
    private $urlGenerator;
    /** @var PatreonSessionStorage */
    private $storage;
    /** @var IUserManager */
    private $userManager;
    /** @var IUserSession */
    private $userSession;
    /** @var IAvatarManager */
    private $avatarManager;
    /** @var IGroupManager */
    private $groupManager;
    /** @var ISession */
    private $session;
    /** @var IL10N */
    private $l;
    /** @var IMailer */
    private $mailer;
    /** @var ConnectedLoginMapper */
    private $socialConnect;
    /** @var IAccountManager */
    private $accountManager;
    /** @var IProvider */
    private $tokenProvider;

    private $pl_baseurl = "https://www.patreon.com";
    private $pl_authurl = "https://www.patreon.com/oauth2/authorize";
    private $pl_tokenurl = "https://www.patreon.com/api/oauth2/token";
    private $pl_apiurl = "https://www.patreon.com";
    

    public function __construct(
        $appName,
        IRequest $request,
        IConfig $config,
        IURLGenerator $urlGenerator,
        PatreonSessionStorage $storage,
        IUserManager $userManager,
        IUserSession $userSession,
        IAvatarManager $avatarManager,
        IGroupManager $groupManager,
        ISession $session,
        IL10N $l,
        IMailer $mailer,
        ConnectedLoginMapper $socialConnect,
        IAccountManager $accountManager,
        IProvider $tokenProvider
    ) {
        $this->appName = $appName;
        $this->request = $request;
        $this->config = $config;
        $this->urlGenerator = $urlGenerator;
        $this->storage = $storage;
        $this->userManager = $userManager;
        $this->userSession = $userSession;
        $this->avatarManager = $avatarManager;
        $this->groupManager = $groupManager;
        $this->session = $session;
        $this->l = $l;
        $this->mailer = $mailer;
        $this->socialConnect = $socialConnect;
        $this->accountManager = $accountManager;
        $this->tokenProvider = $tokenProvider;
    }

    public function getLoginClass($name, $provider = [], $type = null)
    {
        //var_dump('getLoginClass');exit;
        $redirectUrl = $this->request->getParam('redirect_url');
        $routeName =  $this->appName.'.login.'.($type ? 'custom' : 'oauth');
        
        $authUrl = $this->urlGenerator->linkToRouteAbsolute($routeName, [
            'type' => $type,
            'provider' => $name,
            'login_redirect_url' => $redirectUrl
        ]);
        //throw new \Exception('getLoginClass 3 - ' . $authUrl);

        $className = sprintf('%s\%s', AlternativeLogin::class, ucfirst($name));
        $class = class_exists($className) ? $className : loginwithpatreon::class;

        if (method_exists($class, 'addLogin')) {
            $title = $provider['title'] ?? ucfirst($name);
            $label = $this->config->getAppValue($this->appName, 'button_text_wo_prefix')
                ? $title
                : $this->l->t('Log in with %s', $title);
            $class::addLogin($label, $authUrl, $provider['style'] ?? '');
        }
        return $class;
    }

    public function handleDefault($provider)
    {
        var_dump('handleDefault');exit;
        $config = [];
        $scopes = [
            'discord' => 'identify email guilds',
        ];
        $providers = json_decode($this->config->getAppValue($this->appName, 'oauth_providers'), true) ?: [];

        
        if (is_array($providers) && in_array($provider, array_keys($providers))) {
            $providerClass = $provider;
            foreach ($providers as $name => $prov) {
                if (false && $name === $provider) {
                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.oauth', ['provider' => $provider]);
                    
                    $config = array_merge([
                        'callback' => $callbackUrl,
                        'default_group' => $prov['defaultGroup'],
                        'orgs' => $prov['orgs'] ?? null,
                        'workspace' => $prov['workspace'] ?? null,
                        'guilds' => $prov['guilds'] ?? null,
                    ], $this->applyConfigMapping('default', $prov));

                    if (isset($scopes[$name])) {
                        $config['scope'] = $scopes[$name];
                    }

                    if (isset($prov['auth_params']) && is_array($prov['auth_params'])) {
                        foreach ($prov['auth_params'] as $k => $v) {
                            if (!empty($v)) {
                                $config['authorize_url_parameters'][$k] = $v;
                            }
                        }
                    }
                    break;
                } else if ($name === $provider && $provider=="") {
                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.oauth', ['provider' => $provider]);
                    $providerClass = "";
                    $config = array_merge([
                        'callback' => $callbackUrl,
                        'default_group' => $prov['defaultGroup'],
                        'orgs' => $prov['orgs'] ?? null,
                        'workspace' => $prov['workspace'] ?? null,
                        'guilds' => $prov['guilds'] ?? null,
                    ], $this->applyConfigMapping('default', $prov));

                    if (isset($scopes[$name])) {
                        $config['scope'] = $scopes[$name];
                    }

                    if (isset($prov['auth_params']) && is_array($prov['auth_params'])) {
                        foreach ($prov['auth_params'] as $k => $v) {
                            if (!empty($v)) {
                                $config['authorize_url_parameters'][$k] = $v;
                            }
                        }
                    }
                    break;
                }
            }
        }
        return $this->auth(Provider::class.'\\'.ucfirst($provider), $config, $provider, 'OAuth');
    }

    public function handleCustom($type, $provider)
    {
        var_dump('handleCustom');exit;
        $config = [];
        $providers = json_decode($this->config->getAppValue($this->appName, 'custom_providers'), true) ?: [];
        if (isset($providers[$type])) {
            foreach ($providers[$type] as $prov) {
                if ($prov['name'] === $provider) {
                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.custom', [
                        'type'=> $type,
                        'provider' => $provider
                    ]);
                    $config = array_merge([
                        'callback'          => $callbackUrl,
                        'default_group'     => $prov['defaultGroup'],
                    ], $this->applyConfigMapping($type, $prov));

                    if (isset($config['endpoints']['authorize_url']) && strpos($config['endpoints']['authorize_url'], '?') !== false) {
                        list($authUrl, $authQuery) = explode('?', $config['endpoints']['authorize_url'], 2);
                        $config['endpoints']['authorize_url'] = $authUrl;
                        parse_str($authQuery, $config['authorize_url_parameters']);
                    }
                    break;
                }
            }
        }
        return $this->auth(self::TYPE_CLASSES[$type], $config, $provider);
    }

    public function handlePetronLogin1($type, $provider)
    {
        var_dump('handlePetronLogin1');exit;
        $config = [];
        $providers = json_decode($this->config->getAppValue($this->appName, 'custom_providers'), true) ?: [];
        var_dump($providers);exit;
        if (isset($providers[$type])) {
            foreach ($providers[$type] as $prov) {
                if ($prov['name'] === $provider) {
                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.custom', [
                        'type'=> $type,
                        'provider' => $provider
                    ]);

                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.oauth', [
                        'provider' => $provider
                    ]);
                    $providerClass = "";

                    var_dump($callbackUrl);exit;
                    $config = array_merge([
                        'callback'          => $callbackUrl,
                        'default_group'     => $prov['defaultGroup'],
                    ], $this->applyConfigMapping($type, $prov));

                    if (isset($config['endpoints']['authorize_url']) && strpos($config['endpoints']['authorize_url'], '?') !== false) {
                        list($authUrl, $authQuery) = explode('?', $config['endpoints']['authorize_url'], 2);
                        $config['endpoints']['authorize_url'] = $authUrl;
                        parse_str($authQuery, $config['authorize_url_parameters']);
                    }
                    break;
                }
            }
        }
        return $this->auth(self::TYPE_CLASSES[$type], $config, $provider);
    }

    public function handlePetronLogin($type, $provider)
    {
        //var_dump('handlePetronLogin');exit;
        $config = [];
        $scopes = [
            'discord' => 'identify email guilds',
        ];
        $providers = json_decode($this->config->getAppValue($this->appName, 'oauth_providers'), true) ?: [];
        if (is_array($providers) && in_array($provider, array_keys($providers))) {
            $providerClass = $provider;
            foreach ($providers as $name => $prov) {
                if ($name === $provider && $provider == "PatreonLogin") {
                    $callbackUrl = $this->urlGenerator->linkToRouteAbsolute($this->appName.'.login.oauth', ['provider' => $provider]);
                    $prov["name"]= "LoginWithPatreon";
                    $prov["title"]= "Patreon";
                    $prov["apiBaseUrl"]= "https://api.patreon.com/oauth2/api/";
                    $prov["authorizeUrl"]= "https://www.patreon.com/oauth2/authorize";
                    $prov["tokenUrl"]= "https://www.patreon.com/api/oauth2/token";
                    $prov["profileUrl"]= "https://www.patreon.com/api/oauth2/api/current_user";
                    $prov["style"]= "paypal";
                    $prov["defaultGroup"]= "";
                    $prov["groupMapping"]= array("patreon"=> "admin");
                    //die($callbackUrl);
                    $config = array_merge([
                        'callback'          => $callbackUrl,
                        'default_group'     => $prov['defaultGroup'],
                    ], $this->applyConfigMapping($type, $prov));
                    if (isset($config['endpoints']['authorize_url']) && strpos($config['endpoints']['authorize_url'], '?') !== false) {
                        list($authUrl, $authQuery) = explode('?', $config['endpoints']['authorize_url'], 2);
                        $config['endpoints']['authorize_url'] = $authUrl;
                        parse_str($authQuery, $config['authorize_url_parameters']);
                    }
                    break;
                }
            }
        }
        //return $this->auth(Provider::class.'\\'.ucfirst($provider), $config, $provider, 'OAuth');
        return $this->auth(self::TYPE_CLASSES[$type], $config, $provider);
    }

    private function applyConfigMapping($mapping, $data)
    {
        if (!is_array($mapping)) {
            if (!isset($this->configMapping[$mapping])) {
                throw new LoginException(sprintf('Unknown provider type: %s', $mapping));
            }
            $mapping = $this->configMapping[$mapping];
        }
        $result = [];
        foreach ($mapping as $k => $v) {
            if (is_array($v)) {
                $result[$k] = $this->applyConfigMapping($v, $data);
            } else {
                $result[$k] = isset($data[$v]) ? $data[$v] : null;
            }
        }
        return $result;
    }

    private function auth($class, array $config, $provider, $providerType = null)
    {

        if (empty($config)) {
            if (!$providerType) {
                $providerType = explode('\\', $class);
                $providerType = end($providerType);
            }
            throw new LoginException($this->l->t('Unknown %s provider: "%s"', [$providerType, $provider]));
        }
        if ($redirectUrl = $this->request->getParam('login_redirect_url')) {
            $this->session->set('login_redirect_url', $redirectUrl);
        }

        $curlOptions = [];
        $httpClientConfig = $this->config->getSystemValue('patreon_login_http_client', []);
        if (isset($httpClientConfig['timeout'])) {
            $curlOptions[CURLOPT_TIMEOUT] = $httpClientConfig['timeout'];
            $curlOptions[CURLOPT_CONNECTTIMEOUT] = $httpClientConfig['timeout'];
        }
        if (isset($httpClientConfig['proxy'])) {
            $curlOptions[CURLOPT_PROXY] = $httpClientConfig['proxy'];
        }
        if ($curlOptions) {
            $config['curl_options'] = $curlOptions;
        }
        //print_r($class);exit;
        try {
            $adapter = new $class($config, null, $this->storage);
            $adapter->authenticate();
            $profile = $adapter->getUserProfile();
            die($profile);
        }  catch (\Exception $e) {
            $this->storage->clear();
            throw new LoginException($e->getMessage());
        }

        var_dump('End----');exit;

        $profileId = preg_replace('#.*/#', '', rtrim($profile->identifier, '/'));
        if (empty($profileId)) {
            $this->storage->clear();
            throw new LoginException($this->l->t('Can not get identifier from provider'));
        }

        if (!empty($config['authorize_url_parameters']['hd'])) {
            $profileHd = preg_match('#@(.+)#', $profile->email, $m) ? $m[1] : null;
            $allowedHd = array_map('trim', explode(',', $config['authorize_url_parameters']['hd']));
            if (!in_array($profileHd, $allowedHd)) {
                $this->storage->clear();
                throw new LoginException($this->l->t('Login from %s domain is not allowed for %s provider', [$profileHd, $provider]));
            }
        }

        if ($provider === 'GitHub' && !empty($config['orgs'])) {
            $allowedOrgs = array_map('trim', explode(',', $config['orgs']));
            $username = $adapter->apiRequest('user')->login;
            $checkOrgs = function () use ($adapter, $allowedOrgs, $username, $config) {
                foreach ($allowedOrgs as $org) {
                    try {
                        $adapter->apiRequest('orgs/'.$org.'/members/'.$username);
                        return;
                    } catch (\Exception $e) {}
                }
                $this->storage->clear();
                throw new LoginException($this->l->t('Login is available only to members of the following GitHub organizations: %s', $config['orgs']));
            };
            $checkOrgs();
        }

        if ($provider === 'BitBucket') {
            if (empty($config['workspace'])) {
                throw new LoginException($this->l->t('Invalid setup for Bitbucket workspaces', $config['workspace']));
            }
            $allowedWorks = array_map('trim', explode(',', $config['workspace']));
            $username = $adapter->apiRequest('user')->login;
            $checkWorks = function () use ($adapter, $allowedWorks, $config) {
                try {
                    $workspaceData = $adapter->apiRequest('workspaces');
                    $workspaces = array_map(function ($w) {
                        return $w->slug;
                    }, $workspaceData->values);
                    $workspaces = array_intersect($workspaces, $allowedWorks);
                    if (count($workspaces) > 0) {
                        return;
                    }
                } catch (\Exception $e) {}
                $this->storage->clear();
                throw new LoginException($this->l->t('Login is available only to members of the following Bitbucket workspaces: %s', $config['workspace']));
            };
            $checkWorks();
        }

        if ($provider === 'discord' && !empty($config['guilds'])) {
            $allowedGuilds = array_map('trim', explode(',', $config['guilds']));
            $userGuilds = $adapter->apiRequest('users/@me/guilds');
            $checkGuilds = function () use ($allowedGuilds, $userGuilds, $config) {
                foreach ($userGuilds as $guild) {
                    if (in_array($guild->id ?? null, $allowedGuilds)) {
                        return;
                    }
                }
                $this->storage->clear();
                throw new LoginException($this->l->t('Login is available only to members of the following Discord guilds: %s', $config['guilds']));
            };
            $checkGuilds();
        }

        if (!empty($config['logout_url'])) {
            $this->session->set('loginwithpatreon_logout_url', $config['logout_url']);
        } else {
            $this->session->remove('loginwithpatreon_logout_url');
        }

        $profile->data['default_group'] = $config['default_group'];

        if ($provider === 'telegram') {
            $provider = 'tg'; //For backward compatibility
        }
        $uid = $provider.'-'.$profileId;
        if (strlen($uid) > 64 || !preg_match('#^[a-z0-9_.@-]+$#i', $profileId)) {
            $uid = $provider.'-'.md5($profileId);
        }
        return $this->login($uid, $profile, $provider.'-');
    }

    private function login($uid, Profile $profile, $newGroupPrefix = '')
    {
        $user = $this->userManager->get($uid);
        if (null === $user) {
            $connectedUid = $this->socialConnect->findUID($uid);
            $user = $this->userManager->get($connectedUid);
        }
        if ($this->userSession->isLoggedIn()) {
            if (!$this->config->getAppValue($this->appName, 'allow_login_connect')) {
                throw new LoginException($this->l->t('Social login connect is disabled'));
            }
            if (null !== $user) {
                throw new LoginException($this->l->t('This account already connected'));
            }
            $currentUid = $this->userSession->getUser()->getUID();
            $this->socialConnect->connectLogin($currentUid, $uid);
            return new RedirectResponse($this->urlGenerator->linkToRoute('settings.PersonalSettings.index', ['section'=>'loginwithpatreon']));
        }

        if ($this->config->getAppValue($this->appName, 'restrict_users_wo_assigned_groups') && empty($profile->data['groups'])) {
            throw new LoginException($this->l->t('Users without assigned groups is not allowed to login, please contact support'));
        }

        if ($this->config->getAppValue($this->appName, 'restrict_users_wo_mapped_groups') && isset($profile->data['group_mapping'])) {
            $groups = isset($profile->data['groups']) ? $profile->data['groups'] : [];
            $mappedGroups = array_intersect($groups, array_keys($profile->data['group_mapping']));
            if (!$mappedGroups) {
                throw new LoginException($this->l->t('Your user group is not allowed to login, please contact support'));
            }
        }

        $updateUserProfile = $this->config->getAppValue($this->appName, 'update_profile_on_login');
        $userPassword = '';

        if (null === $user) {
            if ($this->config->getAppValue($this->appName, 'disable_registration')) {
                throw new LoginException($this->l->t('Auto creating new users is disabled'));
            }
            if (
                $profile->email && $this->config->getAppValue($this->appName, 'prevent_create_email_exists')
                && count($this->userManager->getByEmail($profile->email)) !== 0
            ) {
                throw new LoginException($this->l->t('Email already registered'));
            }
            $userPassword = substr(base64_encode(random_bytes(64)), 0, 30);
            $user = $this->userManager->createUser($uid, $userPassword);

            if ($this->config->getAppValue($this->appName, 'create_disabled_users')) {
                $user->setEnabled(false);
            }

            $this->config->setUserValue($uid, $this->appName, 'disable_password_confirmation', 1);
            $updateUserProfile = true;

            if (!$this->config->getAppValue($this->appName, 'disable_notify_admins')) {
                $this->notifyAdmins($uid, $profile->displayName ?: $profile->identifier, $profile->data['default_group']);
            }
        }

        if ($updateUserProfile) {
            $user->setDisplayName($profile->displayName ?: $profile->identifier);
            $user->setSystemEMailAddress((string)$profile->email);

            if ($profile->photoURL) {
                $curl = new Curl();
                try {
                    $photo = $curl->request($profile->photoURL);
                    $avatar = $this->avatarManager->getAvatar($user->getUid());
                    $avatar->set($photo);
                } catch (\Throwable $e) {}
            }

            if (isset($profile->data['groups']) && is_array($profile->data['groups'])) {
                $groups = $profile->data['groups'];
                $groupMapping = isset($profile->data['group_mapping']) ? $profile->data['group_mapping'] : null;
                $userGroups = $this->groupManager->getUserGroups($user);
                $autoCreateGroups = $this->config->getAppValue($this->appName, 'auto_create_groups');
                $syncGroups = [];

                foreach ($groups as $k => $v) {
                    if (is_object($v)) {
                        if (empty($v->gid) && $v->gid !== '0' && $v->gid !== 0) {
                            continue;
                        }
                        $group = $v;
                    } else {
                        $group = (object) array('gid' => $v);
                    }

                    if ($groupMapping && isset($groupMapping[$group->gid])) {
                        $syncGroups[] = (object) array('gid' => $groupMapping[$group->gid]);
                    }
                    $autoGroup = $newGroupPrefix.$group->gid;
                    $group->gid = $autoGroup;
                    if ($autoCreateGroups || $this->groupManager->groupExists($group->gid)) {
                        $syncGroups[] = $group;
                    }
                }

                if (!$this->config->getAppValue($this->appName, 'no_prune_user_groups')) {
                    foreach ($userGroups as $group) {
                        if (!in_array($group->getGID(), array_column($syncGroups, 'gid'))) {
                            $group->removeUser($user);
                        }
                    }
                }

                foreach ($syncGroups as $group) {
                    if ($newGroup = $this->groupManager->createGroup($group->gid)) {
                        $newGroup->addUser($user);

                        if(isset($group->displayName)) {
                            $newGroup->setDisplayName($group->displayName);
                        }
                    }
                }

            }

            if (isset($profile->address)) {
                $account = $this->accountManager->getAccount($user);
                $account->setProperty(IAccountManager::PROPERTY_ADDRESS, $profile->address, IAccountManager::SCOPE_PRIVATE, IAccountManager::NOT_VERIFIED);
                $this->accountManager->updateAccount($account);
            }

            $defaultGroup = $profile->data['default_group'];
            if ($defaultGroup && $group = $this->groupManager->get($defaultGroup)) {
                $group->addUser($user);
            }
        }

        $this->userSession->getSession()->regenerateId();
        $this->userSession->setTokenProvider($this->tokenProvider);
        $this->userSession->createSessionToken($this->request, $user->getUID(), $user->getUID());
        $this->userSession->createRememberMeToken($user);

        $token = $this->tokenProvider->getToken($this->userSession->getSession()->getId());
        $this->userSession->completeLogin($user, [
            'loginName' => $user->getUID(),
            'password' => $userPassword,
            'token' => $userPassword ? null : $token,
        ], false);

        $user->updateLastLoginTimestamp();

        //Workaround to create user files folder. Remove it later.
        \OC::$server->get(\OCP\Files\IRootFolder::class)->getUserFolder($user->getUID());

        if ($redirectUrl = $this->session->get('login_redirect_url')) {
            if (strpos($redirectUrl, '/') === 0) {
                // URL relative to the Nextcloud webroot, generate an absolute one
                $redirectUrl = $this->urlGenerator->getAbsoluteURL($redirectUrl);
            } // else, this is an absolute URL, leave it as-is

            return new RedirectResponse($redirectUrl);
        }

        $this->session->set('last-password-confirm', time());

        return new RedirectResponse($this->urlGenerator->getAbsoluteURL('/'));
    }

    private function notifyAdmins($uid, $displayName, $groupId)
    {
        $admins = $this->groupManager->get('admin')->getUsers();
        if ($groupId) {
            $group = $this->groupManager->get($groupId);
            $subAdmins = $this->groupManager->getSubAdmin()->getGroupsSubAdmins($group);
            foreach ($subAdmins as $user) {
                if (!in_array($user, $admins)) {
                    $admins[] = $user;
                }
            }
        }

        $sendTo = [];
        foreach ($admins as $user) {
            $email = $user->getEMailAddress();
            if ($email && $user->isEnabled()) {
                $sendTo[$email] = $user->getDisplayName() ?: $user->getUID();
            }
        }

        if ($sendTo) {
            $template = $this->mailer->createEMailTemplate('loginwithpatreon.NewUser');

            $template->setSubject($this->l->t('New user created'));
            $template->addHeader();
            $template->addBodyText($this->l->t('User %s (%s) just created via social login', [$displayName, $uid]));
            $template->addFooter();

            $message = $this->mailer->createMessage();
            $message->setTo($sendTo);
            $message->useTemplate($template);
            try {
                $this->mailer->send($message);
            } catch (\Exception $e) {}
        }
    }
}
