<?php
declare(strict_types=1);
// SPDX-FileCopyrightText: Vijay <vijaydwivedics@gmail.com>
// SPDX-License-Identifier: AGPL-3.0-or-later

/**
 * Create your routes in here. The name is the lowercase name of the controller
 * without the controller part, the stuff after the hash is the method.
 * e.g. page#index -> OCA\LoginWithPatreon\Controller\PageController->index()
 *
 * The controller class has to be registered in the application.php file since
 * it's instantiated in there
 */
return [
	'resources' => [
		'note' => ['url' => '/notes'],
		'note_api' => ['url' => '/api/0.1/notes']
	],
	/*'routes' => [
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		['name' => 'note_api#preflighted_cors', 'url' => '/api/0.1/{path}',
			'verb' => 'OPTIONS', 'requirements' => ['path' => '.+']]
	]*/
	'routes' => [
        ['name' => 'settings#saveAdmin', 'url' => '/settings/save-admin', 'verb' => 'POST'],
        ['name' => 'settings#disconnectloginwithpatreon', 'url' => '/disconnect-social/{login}', 'verb' => 'GET'],
        ['name' => 'settings#savePersonal', 'url' => '/settings/save-personal', 'verb' => 'POST'],
        ['name' => 'api#setConfig', 'url' => '/api/config', 'verb' => 'POST'],
        ['name' => 'login#oauth', 'url' => '/oauth/{provider}', 'verb' => 'GET'],
        ['name' => 'login#custom', 'url' => '/{type}/{provider}', 'verb' => 'GET'],
        ['name' => 'login#custom', 'url' => '/{type}/{provider}', 'postfix' => '.post', 'verb' => 'POST'],
    ],
    'ocs' => [
        ['name' => 'link#connectloginwithpatreon', 'url' => '/api/connect/{uid}', 'verb' => 'POST'],
        ['name' => 'link#disconnectloginwithpatreon', 'url' => '/api/connect/{identifier}', 'verb' => 'DELETE'],
    ]
];
