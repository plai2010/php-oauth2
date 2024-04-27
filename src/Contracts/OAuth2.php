<?php
namespace PL2010\OAuth2\Contracts;

use Closure;

/**
 * OAuth2 provider.
 * This captures how a web application would interact with an OAuth2 provider.
 */
interface OAuth2 {
	/**
	 * Get name of the provider.
	 * @return string
	 */
	public function getName(): string;

	/**
	 * Get redirect URI for OAuth2 authorization server to callback.
	 * @return string
	 */
	public function redirectUri(): string;

	/**
	 * Get OAuth2 authorization.
	 * Depending on $type (i.e. OAuth2 'response_type'), either a URL
	 * for user agent redirect to the authorization endpoint or the
	 * actual token is returned. The former case would be for
	 * authorization code grant flow.
	 * @param string $type OAuth2 response type requested if not default.
	 * @param string|array $scope OAuth2 scope if not the default.
	 * @param ?string $redirect Redirect URI to use, overriding configuration.
	 * @param ?\Closure(string, array) $preserve Callback preserve state to receive token later.
	 * @return string|array Authorization URL or access token.
	 */
	public function authorize(
		string $type='',
		string|array $scope='',
		?string $redirect=null,
		?Closure $preserve=null
	): string|array;

	/**
	 * Process callback from authorization endpoint.
	 * This is used to receive authorization code, for example, for
	 * an authorization code grant flow.
	 * @param string $url URL of the callback request.
	 * @param ?array $preserved Preserved state from {@link authorize()}.
	 * @return array OAuth2 token (with 'token_type', 'access_token', etc.).
	 */
	public function receive(string $url, ?array $preserved=null): array;

	/**
	 * Refresh access token.
	 * Token is refreshed if it is expiring in $ttl seconds; force refresh
	 * with a non-positive $ttl value.
	 * @param array $token Access token to refresh.
	 * @param int $ttl Time-to-live in # seconds; non-postive to force refresh.
	 * @return array|NULL Refreshed token, or null if $token is still good.
	 */
	public function refresh(array $cred, int $ttl=300): ?array;
}
