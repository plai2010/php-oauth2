<?php
namespace PL2010\OAuth2\Concerns;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Provider\GenericProvider;

/**
 * Factory to create provider for League OAuth2 client.
 */
trait OAuth2ProviderFactory {
	/**
	 * Create new instance of provider.
	 * @param array $config Configuration items using snake name convention.
	 * @return GenericProvider
	 */
	private function makeOAuth2Provider(array $config): AbstractProvider {
		// Convert ID from snake case to camel case.
		static $SNAKE_TO_CAMEL;
		$SNAKE_TO_CAMEL = $SNAKE_TO_CAMEL ?: function(string $id): string {
			if ($id === '')
				return '';
			$ubar = strpos($id, '_');
			if ($ubar === false)
				return $id;
			return substr($id, 0, $ubar)
				.str_replace('_', '', ucwords(substr($id, $ubar+1), '_'));
		};

		// Provider options are configuration items, except those that
		// start with '*'.
		$options = [
			// Some options required by GenericProvider but not needed for
			// our purposes.
			'urlResourceOwnerDetails' => 'does-not-matter',				
		];
		foreach ($config as $name => $value) {
			if ($name[0] === '*')
				continue;
			$options[$SNAKE_TO_CAMEL($name)] = $value;
		}

		$collaborators = [];
		if ($config['*collaborators'] ?? null) {
			foreach ($config['*collaborators'] as $name => $value) {
				if (class_exists($value))
					$collaborators[$SNAKE_TO_CAMEL($name)] = new $value;
				else if (is_callable($value))
					$collaborators[$SNAKE_TO_CAMEL($name)] = $value();
			}
		}

		// Use GenericProvider unless '*class' overrides.
		$providerClass = $config['*class'] ?? GenericProvider::class;
		return new $providerClass($options, $collaborators);
	}
}
