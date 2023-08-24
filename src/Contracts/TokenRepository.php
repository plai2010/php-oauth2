<?php
namespace PL2010\OAuth2\Contracts;

/**
 * Repository of OAuth2 tokens.
 * An OAuth2 token is identified by a key that is application specific.
 */
interface TokenRepository {
	/**
	 * Retrieve OAuth2 token by key.
	 * If $valid is positive, token is refreshed if possible to be
	 * valid for the specified time. If provided, $oauth overrides
	 * what the repository otherwise decides to use for token refresh.
	 * @param string $key Token key.
	 * @param int $valid Token validity in seconds.
	 * @param ?\PL2010\OAuth2\Contracts\OAuth2 $oauth For refreshing.
	 * @return array Token with 'access_token' and other attributes.
	 */
	public function getOAuth2Token(
		string $key,
		int $valid=0,
		?OAuth2 $oauth=null
	): array;

	/**
	 * Store OAuth2 token by key.
	 * @param string $name Token key.
	 * @param array Token with 'access_token' and other attributes.
	 * @return $this
	 */
	public function putOAuth2Token(string $key, array $token): static;
}
