<?php
namespace PL2010\OAuth2\Repositories;

use PL2010\OAuth2\OAuth2Manager;

use Closure;
use RuntimeException;

/**
 * File system directory implementation of OAuth2 token repository.
 * Each provider has its own directory under {@link base_dir}.
 * Keys are stored in individual JSON files.
 */
class DirectoryTokenRepository extends AbstractTokenRepository {
	/** @var string Base directory path, without trailing '/'. */
	protected string $base_dir;

	/**
	 * @param string $dir Base directory path, without trailing '/'.
	 * @param Closure|\PL2010\OAuth2\OAuth2Manager $mgr Manager or resolver.
	 */
	public function __construct(string $dir, Closure|OAuth2Manager $mgr) {
		$this->base_dir = $dir;
		parent::__construct($mgr);
	}

	/**
	 * Get JSON file path storing a token.
	 * @param string $key Token key.
	 * @param bool $ensure Create parent directory of the file if needed.
	 * @return string
	 */
	protected function tokenKeyToKeyFilePath(
		string $key,
		bool $ensure=true
	): string {
		$dname = urlencode($this->tokenKeyToProvider($key, true));
		$dpath = "{$this->base_dir}/{$dname}";
		if ($ensure && !is_dir($dpath)) {
			if (!mkdir($dpath, 0770, true)) {
				throw new RuntimeException(
					"failed to create OAuth token directory '$dpath'");
			}
		}
		$fname = urlencode($this->tokenKeyToUsage($key));
		return "{$dpath}/{$fname}.json";
	}

	/** {@inheritdoc} */
	protected function tokenLoad(
		string $key,
		mixed &$handle=null
	): ?array {
		$handle = null;

		$path = $this->tokenKeyToKeyFilePath($key);
		$handle = $path;

		$json = file_get_contents($path);
		if ($json === false)
			return null;

		$token = json_decode($json, true);
		return $token;
	}

	/** {@inheritdoc} */
	protected function tokenSave(
		string $key,
		array $token,
		mixed $handle=null
	): array {
		$path = $handle ?? $this->tokenKeyToKeyFilePath($key);
		$json = json_encode($token, JSON_PRETTY_PRINT+JSON_UNESCAPED_SLASHES);
		if (file_put_contents($path, $json) === false) {
			throw new RuntimeException("failed to save OAuth token to '$path'");
		}
		return $token;
	}
}
