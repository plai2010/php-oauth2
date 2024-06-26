# OAuth2 Utilities for PHP #

[packagist]: https://packagist.org/
[league-oauth2-client]: https://oauth2-client.thephpleague.com/

This is a utility package for handling OAuth2 in PHP applications.
It allows obtaining OAuth2 access token through an
authorization code grant with just a browser and a command shell
running side by side; there is no need to set up a working web
endpoint for the OAuth2 provider to call back.

On-line authorization flow with working redirect is also supported.
See [this section](#online-example) for how it may be set up in an
Laravel application.

The use case for this package was SMTP XOAUTH2 authentication in
a Laravel (10.x) application. A Laravel service provider is included.

This package depends on [league/oauth2-client][league-oauth2-client],
its `GenericProvider` in particular.

## Installation ##

The package can be installed from [Packagist][packagist]:
```shell
$ composer require plai2010/php-oauth2
```

One may also clone the source repository from `Github`:
```shell
$ git clone https://github.com/plai2010/php-oauth2.git
$ cd php-oauth2
$ composer install
```

## Example: Obtaining Access Token for Outlook SMTP <a id="offline-example"></id> ##

Let us say a web application has been registered in Micrsoft Azure AD:

	* Application ID - 11111111-2222-3333-4444-567890abcdef
	* Application secret - v8rstf8eVD5My89xDOTw8CoKG6rIw9dukIjHYzPU
	* Redirect URI - http://localhost/example

For our purpose the redirect URI should _not_ point to an actual web
site. It can be any URL in proper format; just test with a browser to
ake sure it yields a 404 error.

Create a PHP script, say `outlook-oauth2.php`:
```php
<?php
return [
	'provider' => [
		// As registered with the OAuth2 provider.
		'client_id' => '11111111-2222-3333-4444-567890abcdef',
		'client_secret' => 'v8rstf8eVD5My89xDOTw8CoKG6rIw9dukIjHYzPU',
		'redirect_uri' => 'http://localhost/example',

		// These items are OAuth2 provider specific.
		// The values here are for Microsoft OAuth2.
		'scope_separator' => ' ',
		'url_access_token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
		'url_authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',

		// Other options.
		'pkce_method' => 'S256',
		'timeout' => 30,
	]
];
```

To obtain an OAuth2 token for SMTP login (XOAUTH2), start an interactive
PHP shell:
```shell
$ php -a
Interactive shell

php >
```

Obtain an authorization URL:
```php
php > require_once 'vendor/autoload.php';
php > config = require('outlook-oauth2.php');
php > // The name 'outlook_smtp' does not matter in this example.
php > $oauth2 = new PL2010\OAuth2\OAuth2Provider('outlook_smtp', $config);
php > // Scope is OAuth2 provider specific.
php > // The value here is for Outlook SMTP login authorization by
php > // Microsoft OAuth2; offline_access to request refresh token.
php > $scope = [ 'https://outlook.office.com/SMTP.Send', 'offline_access' ];
php > $url = $oauth2->authorize('code', $scope);
php > echo $url, PHP_EOL;
```

An URL like this is printed as the result (line breaks inserted):
```
https://login.microsoftonline.com/common/oauth2/v2.0/authorize
	?scope=...
	&state=...
	&response_type=code
	&client_id=...
	&...
```

Do not end the interactive PHP shell. Copy the URL into a browser and go
through the steps to authorize access. At the end the browser will be
redirected to an non-existing page with URL that matches the redirect
URI. Go back to the the interactive PHP shell to process that URL:
```php
php > // Get from browser the URL of the 'not found' page.
php > $redir = 'http://localhost/example?code=...&state=...';
php > $token = $oauth2->receive($redir);
php > echo json_encode($token, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
{
	"token_type": "Bearer",
	"scope": "https://outlook.office.com/SMTP.Send",
	"ext_expires_in": 3600,
	"access_token": "EwBFB+l3BAK...",
	"refresh_token": "M.C732_B...",
	"expires": 1689702075
}
php > 
```

The token can be saved to some storage (e.g. database) for use by an
application. If the token has expiration and is refreshable, the application
would request a refresh. This is illustrated as follows:
```php
// Retrieve token from storage.
$token = [ ... ];

// Refresh token if it is expiring in say a minute.
$ttl = 60;
$oauth2 = new PL2010\OAuth2\OAuth2Provider(...);
$refreshed = $oauth2->refresh($token, $ttl);
if ($refreshed !== null) {
	// Save refreshed token to storage.
	...
	$token = $refreshed;
}

// Use $token ...
```

## Provider Manager <a id="provider-manager"></a> ##

An application may interact with multiple OAuth2 providers.
For example, it may need authorization from Google to access
files in Google Drive and from Microsoft to send email via
Outlook SMTP. [`OAuth2Manager`](src/OAuth2Manager.php) makes
multiple OAuth2 providers available. For example,
```php
php > // Create manager.
php > $manager = new PL2010\OAuth2\OAuth2Manager;
php > // Configure 'google' provider for 'drive' and 'openid' purposes.
php > $manager->configure('google', [
	'provider' => [
		'client_id' => ...,
		'client_secret' => ...,
		'url_access_token' => 'https://oauth2.googleapis.com/token',
		'url_authorize' => 'https://accounts.google.com/o/oauth2/auth',
	],
	'usage' => [
		'drive' => [
			'scopes' => [
				'https://www.googleapis.com/auth/drive.file',
				'https://www.googleapis.com/auth/drive.resource',
				...
			],
		],
		'signin' => [
			'scopes' => [
				'openid',
				'email',
				...
			],
		],
	],
]);
php > // Configure 'microsoft' provider for 'smtp'.
php > $manager->configure('microsoft', [
	'provider' => [
		'client_id' => ...,
		'client_secret' => ...,
		'url_access_token' => 'https://login.microsoftonline.com/common/oauth2/v2.0/token',
		'url_authorize' => 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize',
	],
	'usage' => [
		'smtp' => [
			'scopes' => [
				'https://graph.microsoft.com/mail.send',
			],
		],
	]
]);
```

Then to interact with Microsoft to obtain access token for SMTP login,
one would do this:
```php
php > $oauth2 = $manager->get('microsoft', 'smtp');
php > // Like before ...
php > $scope = [ 'https://outlook.office.com/SMTP.Send', 'offline_access' ];
php > $url = $oauth2->authorize('code', $scope);
...
```

## Token Repository ##

It is up to an application to manage the storage of OAuth2 tokens. This
package does includes [`TokenRepository`](src/Contracts/TokenRepository.php)
and some implementation as a model, however. In this model, a token is
identified by a two-part key: _provider_`:`_usage_. The two parts
correspond to parameters of `OAuth2Manager::get()`(src/OAuth2Manager.php),
so that a repository and refresh tokens as needed. The key scheme can
be extended to suit an application. For example, to allow per-user OAuth2
tokens, _provider_`:`_usage_`:`_user\_id_ may suffice.

[`AbstractTokenRepository`](src/Repositories/AbstractTokenRepository.php)
is an abstract implementation. Just provide `tokenLoad()` and `tokenSave()`
methods.

## Laravel Integration ##

This package includes a Laravel service provider that makes
a singleton [`OAuth2Manager`](src/OAuth2Manager.php) available
two ways:

	* Abstract 'oauth2' in the application container, i.e. `app('oauth2')`.
	* Facade alias 'OAuth2', i.e. `OAuth2::`.

The configuration file is `config/oauth2.php`. It returns an
associative array of provider configurations by names, like this:
```php
<?php
return [
	'google' => [
		'provider' => [
			'client_id' => ...,
			'client_secret' => ...,
			...
		],
		'usage' => [
			'drive' => [
				...
			],
			'signin' => [
				...
			],
		],
	],

	'microsoft' => [
		'provider' => [
			'client_id' => ...,
			'client_secret' => ...,
			...
		],
		'usage' => [
			'smtp' => [
				...
			],
		]
	],
];
```

There is no [`TokenRepository`](src/Contracts/TokenRepository.php) setup,
but here is an example of making available as 'oauth2_tokens' a singleton
[`DirectoryTokenRepository`](src/Repositories/DirectoryTokenRepository.php):
```php
use PL2010\OAuth2\Repositories\DirectoryTokenRepository;

app()->singleton('oauth2_tokens', function() {
	return new DirectoryTokenRepository(
		storage_path('app/oauth2_tokens'),
		app('oauth2')
	);
});
```

## Example: Online Flow in Laravel Application <a id="online-example"></a> ##

Let's say a Laravel application at `example.com` is using this package. One
can add a route in `routes/web.php` for starting an authorization code grant
flow:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Presumably there would a button on some page to do make a POST request,
// but for simplicity we just use `GET` in our example.
Route::get('/oauth2/authorize/{provider}/{usage?}', function(
	Request $request,
	string $provider,
	?string $usage=null
) {
	/**
	 * @var \PL2010\OAuth2\OAuth2Manager $mgr
	 * @var \PL2010\OAuth2\OAuth2Provider $oauth2
	 */
	$mgr = app('oauth2');
	$oauth2 = $mgr->get($provider, $usage);
	$redirect = route('oauth2.callback', [
		'provider' => $provider,
		'usage' => $usage,
	]);
	$url = $oauth2->authorize('code', '', $redirect, function($state, $data) {
		// Preserve state data in cache for a short while.
		Cache::put("oauth2:flow:state:{$state}", $data, now()->addMinutes(15));
	});
	return redirect($url);
})->middleware([
	// There would be middlewares appropriate for the use case.
	'can:configureOAuth2',
])->name('oauth2.authorize');
```

Using Microsoft SMTP as in the [Provider Manager](#provider-manager) example,
request `https://example.com/oauth2/authorize/microsoft/smtp` would redirect
to Microsoft's `login.microsoftonline.com`.

A route named `oauth2.callback` is assumed above for receiving authorization
code. Here is what it would look like:
```php
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;

// Handle redirect from OAuth2 authorization provider.
Route::get('/oauth2/callback/{provider}/{usage?}', function(
	Request $request,
	string $provider,
	?string $usage=null
) {
	/**
	 * @var \PL2010\OAuth2\OAuth2Manager $mgr
	 * @var \PL2010\OAuth2\OAuth2Provider $oauth2
	 * @var \PL2010\OAuth2\Contracts\TokenRepository $tkrepo
	 */
	// Expect authorization state in the request.
	$state = $request->get('state');
	if (!is_string($state))
		return redirect('/')->with('error', 'Missing OAuth2 state');

	// Retrieve preserved state data.
	$data = Cache::get("oauth2:flow:state:{$state}");
	if (!$data)
		return redirect('/')->with('error', 'Invalid OAuth2 state');

	// Obtain access token.
	$mgr = app('oauth2');
	$oauth2 = $mgr->get($provider, $usage);
	$token = $oauth2->receive($request->fullUrl(), preserved:$data);

	// Save access token.
	$key = $provider.($usage != ''
		? ":{$usage}"
		: ''
	);
	$tkrepo = app('oauth2_tokens');
	$tkrepo->putOAuth2Token($key, $token);

	return redirect('/')->with('success', 'OAuth2 access token saved');
})->middleware([
	// There would be middlewares appropriate for the use case.
	'can:configureOAuth2',
])->name('oauth2.callback');
```

On Microsoft side, OAuth2 should be configured to allow redirect URI
`https://example.com/oauth2/callback/microsoft/smtp`.
