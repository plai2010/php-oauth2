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
	 * @return $this
	 */
	public function configure(
		string $name,
		array $config,
		bool $default=false
	): static {
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
	 * Inject parameters into a configuration.
	 * Pattern "${name}" is replaced by $param['name'].
	 * @param array $config Configuration.
	 * @param array $params Name-value map.
	 * @return array
	 */
	private function injectParameters(array $config, ?array $params): array {
		if (!$params)
			return $config;

		array_walk_recursive($config, function(&$val) use($params) {
			if (!is_string($val) || strpos($val, '$') === false)
				return;

			$matches = [];
			$offset = 0;
			$pattern = '%(\\\\.)|\${([A-Za-z][A-Za-z0-9_]*)}%';
			$options = PREG_OFFSET_CAPTURE;
			ob_start();
			while (preg_match($pattern, $val, $matches, $options, $offset)) {
				[ $found, $mark ] = $matches[0];
				echo substr($val, $offset, $mark-$offset);
				if ($found[0] == '\\') {
					// Found escaped character, e.g. '\$' => '$'
					echo $found[1];
				}
				else {
					// Found parameter reference, e.g. '${foobar}'
					$name = $matches[2][0];
					echo $params[$name] ?? null;
				}
				$offset = $mark + strlen($found);
			}
			echo substr($val, $offset);
			$val = ob_get_clean();
		});
		return $config;
	}

	/**
	 * Get OAuth2 provider by name.
	 * If $name is not provided, then the default will be used. The
	 * default is explicitly specified through {@link configure()},
	 * or just the first configured otherwise.
	 * @param ?string $name
	 * @param ?string $usage
	 * @param ?array $params Template instantiation parameters.
	 */
	public function get(
		?string $name=null,
		?string $usage=null,
		?array $params=null
	): ?OAuth2 {
		$name = $name
			?? $this->default
			?? array_keys($this->providers)[0] ?? null;

		$usage = $usage ?? '';
		$slot = $usage !== '' ? "{$name}:$usage" : $name;

		// Provider already instantited?
		$impl = $this->providers[$slot] ?? null;
		if ($impl instanceof OAuth2)
			return $impl;

		// Not instantiated; get definition instead.
		$conf = $this->defines[$name] ?? null;
		if ($conf === null)
			return null;

		// For template, $usage may have the form "<usage>:<inst>"
		// where <inst> is used to differentiate between the
		// instantiations.
		$template = $conf['template'] ?? false;
		if ($template) {
			$tuple = explode(':', $usage, 2);
			if (count($tuple) > 1)
				$usage = $tuple[0];
		}
		$conf['template'] = null;

		if ($usage !== '') {
			// Incorporate usage specific configuration.
			// Usage may be an alias for another, but no chaining.
			$specific = $conf['usage'][$usage] ?? null;
			if (is_string($specific))
				$specific = $conf['usage'][$specific] ?? null;
			if (is_array($specific)) {
				$conf['provider'] = array_merge(
					$conf['provider'] ?? [],
					$specific
				);
			}
		}
		$conf['usage'] = null;

		if ($template)
			$conf = $this->injectParameters($conf, $params);

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
		string $type='',
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
