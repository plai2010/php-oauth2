<?php
namespace PL2010\OAuth2;

use PL2010\OAuth2\Contracts\OAuth2;

/**
 * Manager of OAuth2 providers.
 * Providers are retreived by name using the {@link get()} method.
 * This class also is facade for the default provider.
 */
class OAuth2Manager implements OAuth2 {
	/** @var array OAuth2 provider definition by name. */
	protected array $defines = [];

	/** @var array OAuth2 provider instance by name[:usage]. */
	protected array $providers = [];

	/** @pvar ?string Default provider name. */
	protected ?string $default = null;

	public function __construct(
		/** Options including callback 'error_log'. */
		protected array $options=[]
	) {
	}

	/**
	 * Configure an OAuth2 provider.
	 * @param string $name Name of the provider.
	 * @param array $config Configuration of the provider.
	 * @param bool $default Whether the provider is the default.
	 * @return self
	 */
	public function configure(
		string $name,
		array $config,
		bool $default=false
	): self {
		// Clear out previous instances.
		if (isset($this->defines[$name])) {
			$prefix = "{$name}:";
			foreach (array_keys($this->providers) as $slot) {
				if ($slot === $name || str_starts_with($slot, $prefix))
					unset($this->providers[$slot]);
			}
		}

		$this->defines[$name] = $config;
		if ($default)
			$this->default = $name;
		return $this;
	}

	/**
	 * Get OAuth2 provider by name.
	 * If $name is not provided, then the default will be used. The
	 * default is explicitly specified through {@link configure()},
	 * or just the first configured otherwise.
	 * @param ?string $name
	 * @param ?string $usage
	 */
	public function get(?string $name=null, ?string $usage=null): ?OAuth2 {
		$name = $name
			?? $this->default
			?? array_keys($this->providers)[0] ?? null;

		$usage = $usage ?? '';
		$slot = $usage !== '' ? "{$name}:$usage]" : $name;

		// Provider already instantited?
		$impl = $this->providers[$slot] ?? null;
		if ($impl instanceof OAuth2)
			return $impl;

		// Not instantiated; get definition instead.
		$conf = $this->defines[$name] ?? null;
		if ($conf === null)
			return null;
		if ($usage !== '') {
			// Incorporate usage specific configuration.
			$specific = $conf['usage'][$usage] ?? null;
			if ($specific) {
				$conf['provider'] = array_merge(
					$conf['provider'] ?? [],
					$specific
				);
			}
		}
		$conf['usage'] = null;

		$impl = new OAuth2Provider($slot, $conf, $this->options);
		$this->providers[$slot] = $impl;
		return $impl;
	}

	/**
	 * Call {@link OAuth2::getName()} on default.
	 */
	public function getName(): string {
		return $this->get()->getName();
	}

	/**
	 * Call {@link OAuth2::redirectUri()} on default.
	 */
	public function redirectUri(): string {
		return $this->get()->redirectUri();
	}

	/**
	 * Call {@link OAuth2::authorize()} on default.
	 */
	public function authorize(
		string $type='code',
		string|array $scope=''
	): string|array {
		return $this->get()->authorize($type, $scope);
	}

	/**
	 * Call {@link OAuth2::receive()} on default.
	 */
	public function receive(string $url): array {
		return $this->get()->receive($url);
	}

	/**
	 * Call {@link OAuth2::refresh()} on default.
	 */
	public function refresh(array $cred, int $ttl=300): ?array {
		return $this->get()->refresh($cred, $ttl);
	}
}
