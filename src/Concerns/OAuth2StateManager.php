<?php
namespace PL2010\OAuth2\Concerns;

/**
 * OAuth2 statement manager.
 * Provide facility to create and verify states.
 */
trait OAuth2StateManager {
	/**
	 * Create an OAuth2 state.
	 * @param string $name Some internal name for the state.
	 * @param array $info State information.
	 * @param string $salt Used as key for HMAC algorithm.
	 * @return string
	 */
	private function makeOAuth2State(
		string $name,
		array $info,
		string $salt
	): string {
		$info = array_merge([
			'name' => $name,
		], $info);
		ksort($info);
		return hash_hmac('sha256', http_build_query($info), $salt);
	}

	/**
	 * Verify an OAuth2 state.
	 * This basically recreates the state and compares.
	 * @param string $name {@see makeOAuth2State()}
	 * @param string $state State to verify.
	 * @param array $info {@see makeOAuth2State()}
	 * @param string $salt {@see makeOAuth2State()}
	 * @return bool Whether $state is good.
	 */
	private function verifyOAuth2State(
		string $name,
		string $state,
		array $info,
		string $salt
	): bool {
		return $state === $this->makeOAuth2State($name, $info, $salt);
	}
}
