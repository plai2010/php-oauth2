<?php
namespace PL2010\Laravel\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Facade for OAuth2 interaction.
 * The {@link get()} method retrieves a named OAuth2 instance. The other
 * methods are pass through to the default instance.
 * @method static ?\PL2010\OAuth2\Contracts\OAuth2 get(?string $name=null, ?string $usage=null, ?array $params=null)
 * @method static string getName()
 * @method static string redirectUri()
 * @method static string|array authorize(string $type='', string|array $scope='')
 * @method static array receive(string $url)
 * @method static array|NULL refresh(array $cred, int $ttl=300)
 */
class OAuth2 extends Facade {
	/**
	 * Get the registered name of the component.
	 */
	protected static function getFacadeAccessor(): string {
		return config('pl2010.oauth2.naming.name', 'oauth2');
	}
}
