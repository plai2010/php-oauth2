<?php
namespace PL2010\Laravel;

use PL2010\Laravel\Facades\OAuth2;
use PL2010\OAuth2\OAuth2Manager;

use Illuminate\Foundation\AliasLoader;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Laravel service provider for OAuth2.
 */
class OAuth2ServiceProvider extends ServiceProvider {
	/** @var array Default naming scheme. */
	private array $naming = [
		'name' => 'oauth2',
		'alias' => 'OAuth2',
	];

	public function register(): void {
		// Allow configuration to override naming scheme.
		$this->naming = array_merge(
			$this->naming,
			$this->app['config']['pl2010.oauth2.naming'] ?? []
		);

		$abstract = $this->naming['name'];
		$this->app->singleton($abstract, function() use($abstract) {
			$oauth2 = new OAuth2Manager([
				'error_log' => function(string $msg) {
					Log::error($msg);
				},
			]);

			$callbackRoute = "{$abstract}.callback";
			if (!Route::has($callbackRoute))
				$callbackRoute = null;

			// Configure the OAuth2 providers, with first one as default.
			$first = true;
			foreach ($this->app['config'][$abstract]??[] as $name => $config) {
				// $config is either actual configuration array, or
				// a string that points to another config
				if (is_string($config))
					$config = $this->app['config']["oauth2.{$config}"] ?? [];

				// Provide redirect URI if not configured.
				if (!($config['provider']['redirect_uri'] ?? null)) {
					$config['provider']['redirect_uri'] = $callbackRoute
						? route($callbackRoute, [ 'name' => $name ])
						: url("/{$abstract}/callback/".rawurlencode($name));
				}
				$oauth2->configure($name, $config, $first);
				$first = false;
			}

			return $oauth2;
		});

		AliasLoader::getInstance()->alias(
			$this->naming['alias'],
			OAuth2::class
		);
	}
}
