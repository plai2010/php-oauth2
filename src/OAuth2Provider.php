<?php
namespace PL2010\OAuth2;

use PL2010\OAuth2\Concerns\OAuth2ProviderFactory;
use PL2010\OAuth2\Concerns\OAuth2StateManager;
use PL2010\OAuth2\Contracts\OAuth2;

use League\OAuth2\Client\Provider\AbstractProvider;
use League\OAuth2\Client\Token\AccessToken;

use InvalidArgumentException;

/**
 * Implementation of the {@link OAuth2} interface.
 */
class OAuth2Provider implements OAuth2 {
	use OAuth2ProviderFactory;
	use OAuth2StateManager;

	/** Crypto salt; used for a HMAC key. */
	private ?string $salt = null;

	public function __construct(
		private string $name,
		private array $config,
		array $options=[]
	) {
		$this->salt = $this->config['salt'] ?? base64_encode(random_bytes(18));
		if (is_callable($logger = $options['error_log'] ?? null))
			$this->error_log = $logger;
	}

	/** {@inheritdoc} */
	public function getName(): string {
		return $this->name;
	}

	/** {@inheritdoc} */
	public function redirectUri(): string {
		// Configured redirect URI?
		if ($uri = $this->config['provider']['redirect_uri'] ?? null)
			return $uri;

		// Fallback to some default for development.
		return 'https://localhost/'
			. urlencode($this->name)
			. '/callback';
	}

	/** {@inheritdoc} */
	public function authorize(
		string $type='code',
		string|array $scope=''
	): string|array {
		$providerCfg = $this->config['provider'] ?? [];
		$provider = $this->getOAuth2Provider();

		switch ($type) {
		case 'code':
			$state = $this->makeOAuth2State($this->name, [
				'response_type' => 'code',
				'client_id' => $providerCfg['client_id'] ?? null,
				'redirect_uri' => $this->redirectUri(),
			], $this->salt);
			return $provider->getAuthorizationUrl([
				'scope' => $scope,
				'state' => $state,
			]);
		case 'token':
		default:
			$this->logError("unsupported OAuth2 response_type: $type");
			throw new InvalidArgumentException('unsupported_response_type');
		}
	}

	/** {@inheritdoc} */
	public function receive(string $url): array {
		@[
			'query' => $query,
		] = parse_url($url);

		parse_str($query ?? '', $params);
		@[
			'code' => $code,
			'state' => $state,
		//	'token' => $token,
		] = $params;

		$providerCfg = $this->config['provider'] ?? [];
		$provider = $this->getOAuth2Provider();

		if (!is_string($state)) {
			$this->logError('invalid call-back: missing/invalid state');
			throw new InvalidArgumentException('invalid_request');
		}

		if (is_string($code ?? null)) {
			if (!$this->verifyOAuth2State($this->name, $state, [
				'response_type' => 'code',
				'client_id' => $providerCfg['client_id'] ?? null,
				'redirect_uri' => $this->redirectUri(),
			], $this->salt)) {
				$this->logError('invalid call-back: state cannot be verified');
				throw new InvalidArgumentException('invalid_request');
			}
			$accessToken = $provider->getAccessToken('authorization_code', [
				'code' => $code,
			]);
			return json_decode(json_encode($accessToken), true);
		}

		$this->logError('invalid call-back: cannot determine callback type');
		throw new InvalidArgumentException('invalid_request');
	}

	/** {@inheritdoc} */
	public function refresh(array $cred, int $ttl=300): ?array {
		$token = new AccessToken($cred);
		if ($ttl > 0 && $token->getExpires() > time() + $ttl)
			return null;

		$provider = $this->getOAuth2Provider();
		$token = $provider->getAccessToken('refresh_token', array_merge($cred, [
			// Don't care about redirect URI for refresh token request.
			'redirect_uri' => null,
		]));
		$refreshed = json_decode(json_encode($token), true);
		$cred = array_merge($cred, $refreshed);
		return $cred;
	}

	/** OAuth2 provider. */
	protected ?AbstractProvider $provider = null;

	protected function getOAuth2Provider(): AbstractProvider {
		if (!$this->provider) {
			$this->provider = $this->makeOAuth2Provider(
				$this->config['provider'] ?? []
			);
		}
		return $this->provider;
	}

	/** @var callable Error logger. */
	protected $error_log = 'error_log';

	protected function logError(string $msg): void {
		($this->error_log)($msg);
	}
}
