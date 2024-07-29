<?php

namespace WpOrg\Requests\Tests;

use Exception;
use stdClass;
use WpOrg\Requests\Requests;
use WpOrg\Requests\Tests\TypeProviderHelper;
use Yoast\PHPUnitPolyfills\TestCases\TestCase as Polyfill_TestCase;

abstract class TestCase extends Polyfill_TestCase {

	/**
	 * Clean up after the tests.
	 *
	 * As most test classes will use the TypeProviderHelper for one or more tests,
	 * we may as well always call the clean up function just to be on the safe side.
	 */
	public static function tear_down_after_test() {
		TypeProviderHelper::cleanUp();
	}

	/**
	 * Helper function to skip select tests when the transport under test is not available.
	 *
	 * @param string $transport Fully qualified class name for the transport to verify.
	 *
	 * @return void
	 */
	public function skipWhenTransportNotAvailable($transport) {
		if (!$transport::test()) {
			$this->markTestSkipped('Transport "' . $transport . '" is not available');
		}
	}

	/**
	 * Retrieve a URL to use for testing and mark the test as skipped if the server for that URL is unavailable.
	 *
	 * @param string $suffix The query path to add to the base URL.
	 * @param bool   $ssl    Whether to get the URL using the `http` or the `https` protocol.
	 *                       Defaults to `false`, which will result in a URL using `http`.
	 *
	 * @return string
	 */
	public function httpbin($suffix = '', $ssl = false) {
		$this->skipOnUnavailableHttpbinHost($ssl);

		return self::getHttpbinUrl($suffix, $ssl);
	}

	/**
	 * Check if a test which depends on a certain server type to be available should run or not.
	 *
	 * @param bool $ssl Whether the URL under tests will be using the `http` or the `https` protocol.
	 *
	 * @return void
	 */
	public function skipOnUnavailableHttpbinHost($ssl = false) {
		if ($ssl === false && REQUESTS_TEST_SERVER_HTTP_AVAILABLE === false) {
			$this->markTestSkipped(sprintf('Host %s not available. This needs investigation', REQUESTS_TEST_HOST_HTTP));
		}

		if ($ssl === true && REQUESTS_TEST_SERVER_HTTPS_AVAILABLE === false) {
			$this->markTestSkipped(sprintf('Host %s not available. This needs investigation', REQUESTS_TEST_HOST_HTTPS));
		}
	}

	/**
	 * Retrieve a URL to use for testing.
	 *
	 * @param string $suffix The query path to add to the base URL.
	 * @param bool   $ssl    Whether to get the URL using the `http` or the `https` protocol.
	 *                       Defaults to `false`, which will result in a URL using `http`.
	 *
	 * @return string
	 */
	public static function getHttpbinUrl($suffix = '', $ssl = false) {
		$host = $ssl ? 'https://' . \REQUESTS_TEST_HOST_HTTPS : 'http://' . \REQUESTS_TEST_HOST_HTTP;
		return rtrim($host, '/') . '/' . ltrim($suffix, '/');
	}

	/**
	 * Data provider for use in tests which need to be run against all default supported transports.
	 *
	 * @return array
	 */
	public static function transportProvider() {
		$data = [];

		foreach (Requests::DEFAULT_TRANSPORTS as $transport) {
			$name        = substr($transport, (strrpos($transport, '\\') + 1));
			$data[$name] = [$transport];
		}

		return $data;
	}

	/**
	 * Helper function to convert a single-level array containing text strings to a named data provider.
	 *
	 * @param array<string> $input Input array.
	 *
	 * @return array<array> Array which is usable as a test data provider with named data sets.
	 */
	public static function textArrayToDataprovider($input) {
		$data = [];
		foreach ($input as $value) {
			if (!is_string($value)) {
				throw new Exception(
					sprintf(
						'All values in the input array should be text strings. Fix the input data. Received: %s',
						var_export($value, true)
					)
				);
			}

			if (isset($data[$value])) {
				throw new Exception(
					"Attempting to add a duplicate data set for value $value to the data provider. Fix the input data."
				);
			}

			$data[$value] = [$value];
		}

		return $data;
	}

	/**
	 * Helper method to retrieve a mock object based on stdClass with select methods mocked.
	 *
	 * The `setMethods()` method was silently deprecated in PHPUnit 8.3 and removed in PHPUnit 10.
	 *
	 * Note: the `getMockBuilder()` and `addMethods()` methods are also soft deprecated as of
	 * PHPUnit 10.x, and are expected to be hard deprecated in PHPUnit 11 and removed in PHPUnit 12.
	 * Dealing with that is something for a later iteration of the test suite.
	 * {@link https://github.com/WordPress/Requests/issues/814}
	 *
	 * @param array $methods The names of the methods to mock.
	 *
	 * @return \PHPUnit\Framework\MockObject\MockObject
	 */
	protected function getMockedStdClassWithMethods($methods) {
		$mock = $this->getMockBuilder(stdClass::class);

		if (\method_exists($mock, 'addMethods')) {
			// PHPUnit 8.3.0+.
			return $mock->addMethods($methods)
				->getMock();
		}

		// PHPUnit < 8.3.0.
		return $mock->setMethods($methods)
			->getMock();
	}
}
