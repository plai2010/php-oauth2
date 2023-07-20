<?php
namespace PL2010\OAuth2\Repositories;

use PL2010\OAuth2\OAuth2Manager;
use PL2010\OAuth2\Contracts\OAuth2;
use PL2010\OAuth2\Contracts\TokenRepository;

use Closure;

/**
 * Abstract implementation of OAuth2 token repository.
 * This assumes token key has the form "<provider>:<usage>".
 */
abstract class AbstractTokenRepository implements TokenRepository {
	public function __construct(
		/** OAuth2 manager or callback to resolve to one. */
		protected Closure|OAuth2Manager $mgr,
	) {
	}

	/**
	 * Get manager of OAuth2 providers.
	 * @return \PL2010\OAuth2\OAuth2Manager;
	 */
	protected function getOAuth2Manager(): OAuth2Manager {
		if (!$this->mgr instanceof OAuth2Manager)
			$this->mgr = call_user_func($this->mgr);
		return $this->mgr;
	}

	/**
	 * Resolve token key to OAuth2 that issued the token.
	 * @param string $key
	 * @return \PL2010\OAuth2\Contracts\OAuth2
	 */
	protected function resolve(string $key): OAuth2 {
		$provider = $this->tokenKeyToProvider($key);
		return $this->getOAuth2Manager()->get($provider);
	}

	/**
	 * Resolve token key to issuser/provider name.
	 * @param string $key
	 * @param bool $default Return default provider if $key does not specify.
	 * @return ?string Null or empty string implies default provider.
	 */
	protected function tokenKeyToProvider(
		string $key,
		bool $default=false
	): ?string {
		$mark = strpos($key, ':');
		$provider = $mark !== false
			? substr($key, 0, $mark)
			: '';
		if ($provider === '') {
			$provider = $default
				? $this->getOAuth2Manager()->get()->getName()
				: null;
		}
		return $provider;
	}

	/**
	 * Resolve token key to usage.
	 * @param string $key
	 * @return string
	 */
	protected function tokenKeyToUsage(string $key): string {
		$mark = strpos($key, ':');
		return $mark !== false
			? substr($key, $mark+1)
			: $key;
	}

	/**
	 * Retrieve token by key.
	 * This method retrieve a token. It may set $handle so
	 * that a subsequent {@link tokenSave()} to update the
	 * token may be more efficient.
	 * @param string $key Token key.
	 * @param mixed &$handle Handle for updating token.
	 * @return array|NULL Token retrieved; null if not found.
	 */
	abstract protected function tokenLoad(
		string $key,
		mixed &$handle=null
	): ?array;

	/**
	 * Save a token by key.
	 * @param string $key Token key.
	 * @param array $token Token to save.
	 * @param mixed $handle Handle from {@link tokenLoad()} on same $key.
	 * @return array Token saved; possibly different from $token.
	 */
	abstract protected function tokenSave(
		string $key,
		array $token,
		mixed $handle=null
	): array;

	/** {@inheritdoc} */
	public function getOAuth2Token(string $key, int $valid=0): array {
		$token = $this->tokenLoad($key, $handle);

		// Refresh token if needed.
		if ($token && $valid > 0) {
			$oauth2 = $this->resolve($key);
			if ($refreshed = $oauth2->refresh($token, $valid))
				$token = $this->tokenSave($key, $refreshed, $handle);
		}
		return $token ?? [];
	}

	/** {@inheritdoc} */
	public function putOAuth2Token(string $key, array $token): self {
		$this->tokenSave($key, $token);
		return $this;
	}
}
