<?php
/**
 * Unit tests for detached-signature verification.
 *
 * @package DP\Tests
 */

declare( strict_types=1 );

namespace DP\Tests\Unit\Update;

/*
 * The Unit suite runs without WordPress, so `wp_json_encode()` does not exist
 * to call; and base64 here is the wire format for a signature, not a way of
 * hiding code.
 */
// phpcs:disable WordPress.WP.AlternativeFunctions.json_encode_json_encode
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode

use DP\Core\Update\ManifestError;
use DP\Core\Update\Verifier;
use LogicException;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

/**
 * Real libsodium, real keys, no mocks.
 *
 * Mocking the signature check here would test that the code calls a function we
 * told it to call. These tests generate keypairs and sign real bytes, so a
 * refactor that quietly stops verifying anything fails.
 */
final class VerifierTest extends TestCase {

	/**
	 * Base64 public key of the keypair under test.
	 *
	 * @var string
	 */
	private string $public_key = '';

	/**
	 * Raw secret key of the keypair under test.
	 *
	 * @var string
	 */
	private string $secret_key = '';

	/**
	 * Generate a fresh keypair for each test.
	 *
	 * @return void
	 */
	protected function set_up(): void {
		parent::set_up();

		$pair             = sodium_crypto_sign_keypair();
		$this->public_key = base64_encode( sodium_crypto_sign_publickey( $pair ) );
		$this->secret_key = sodium_crypto_sign_secretkey( $pair );
	}

	/**
	 * A manifest payload, JSON-encoded exactly as the release tool would.
	 *
	 * @param array<string, mixed> $overrides Fields to replace.
	 * @return string
	 */
	private function payload_json( array $overrides = array() ): string {
		$payload = array_merge(
			array(
				'type'    => 'theme',
				'slug'    => 'dpaternina',
				'version' => '1.2.3',
				'package' => 'https://github.com/dp/site/releases/download/theme-v1.2.3/dpaternina-1.2.3.zip',
			),
			$overrides
		);

		return (string) json_encode( $payload );
	}

	/**
	 * Build an envelope around a payload, signed with a given key.
	 *
	 * @param string      $payload_json Bytes to sign.
	 * @param string|null $secret_key   Raw secret key, or null for this test's key.
	 * @param int|null    $schema       Envelope schema, or null for the current one.
	 * @return string
	 *
	 * @throws LogicException If the harness never generated a keypair.
	 */
	private function envelope( string $payload_json, ?string $secret_key = null, ?int $schema = null ): string {
		$key = $secret_key ?? $this->secret_key;

		if ( '' === $key ) {
			throw new LogicException( 'set_up() has not run: there is no key to sign with.' );
		}

		return (string) json_encode(
			array(
				'schema'    => $schema ?? Verifier::SCHEMA,
				'payload'   => base64_encode( $payload_json ),
				'signature' => base64_encode( sodium_crypto_sign_detached( $payload_json, $key ) ),
			)
		);
	}

	/**
	 * A correctly signed envelope opens into a manifest.
	 *
	 * @return void
	 */
	public function test_a_valid_signature_opens_the_envelope(): void {
		$manifest = ( new Verifier( $this->public_key ) )->open( $this->envelope( $this->payload_json() ) );

		$this->assertSame( '1.2.3', $manifest->version );
		$this->assertSame( 'dpaternina', $manifest->slug );
	}

	/**
	 * One flipped byte in the payload invalidates the signature.
	 *
	 * @return void
	 */
	public function test_a_tampered_payload_is_refused(): void {
		$envelope = $this->envelope( $this->payload_json() );
		$decoded  = json_decode( $envelope, true );

		$this->assertIsArray( $decoded );

		// Re-encode the payload with the version bumped, keeping the old signature.
		$decoded['payload'] = base64_encode( $this->payload_json( array( 'version' => '9.9.9' ) ) );

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'signature does not verify' );

		( new Verifier( $this->public_key ) )->open( (string) json_encode( $decoded ) );
	}

	/**
	 * A signature from a different keypair is refused.
	 *
	 * @return void
	 */
	public function test_a_signature_from_another_key_is_refused(): void {
		$other = sodium_crypto_sign_keypair();

		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'signature does not verify' );

		( new Verifier( $this->public_key ) )->open(
			$this->envelope( $this->payload_json(), sodium_crypto_sign_secretkey( $other ) )
		);
	}

	/**
	 * With no key compiled in, nothing verifies. This is the shipped default.
	 *
	 * @return void
	 */
	public function test_an_empty_public_key_refuses_everything(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'No usable update signing key' );

		( new Verifier( '' ) )->open( $this->envelope( $this->payload_json() ) );
	}

	/**
	 * A key of the wrong length is refused rather than handed to libsodium.
	 *
	 * @return void
	 */
	public function test_a_short_public_key_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'No usable update signing key' );

		( new Verifier( base64_encode( 'too-short' ) ) )->open( $this->envelope( $this->payload_json() ) );
	}

	/**
	 * An envelope schema we do not understand is refused before verification.
	 *
	 * @return void
	 */
	public function test_an_unknown_envelope_schema_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'unsupported envelope schema' );

		( new Verifier( $this->public_key ) )->open(
			$this->envelope( $this->payload_json(), null, Verifier::SCHEMA + 1 )
		);
	}

	/**
	 * A response that is not JSON at all — an HTML error page, say — is refused.
	 *
	 * @return void
	 */
	public function test_a_non_json_body_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'not JSON' );

		( new Verifier( $this->public_key ) )->open( '<!doctype html><title>502</title>' );
	}

	/**
	 * An envelope missing its parts is refused.
	 *
	 * @return void
	 */
	public function test_an_envelope_without_a_signature_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'missing payload or signature' );

		( new Verifier( $this->public_key ) )->open(
			(string) json_encode(
				array(
					'schema'  => Verifier::SCHEMA,
					'payload' => base64_encode( $this->payload_json() ),
				)
			)
		);
	}

	/**
	 * Base64 is decoded strictly, so a corrupted signature cannot decode to a valid one.
	 *
	 * @return void
	 */
	public function test_non_base64_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'not valid base64' );

		( new Verifier( $this->public_key ) )->open(
			(string) json_encode(
				array(
					'schema'    => Verifier::SCHEMA,
					'payload'   => base64_encode( $this->payload_json() ),
					'signature' => 'not base64 !!!!',
				)
			)
		);
	}

	/**
	 * A signature of the wrong length is refused before libsodium sees it.
	 *
	 * @return void
	 */
	public function test_a_short_signature_is_refused(): void {
		$this->expectException( ManifestError::class );
		$this->expectExceptionMessage( 'wrong length' );

		( new Verifier( $this->public_key ) )->open(
			(string) json_encode(
				array(
					'schema'    => Verifier::SCHEMA,
					'payload'   => base64_encode( $this->payload_json() ),
					'signature' => base64_encode( 'short' ),
				)
			)
		);
	}

	/**
	 * A payload that verifies but is not a usable manifest is still refused.
	 *
	 * @return void
	 */
	public function test_a_correctly_signed_but_unusable_payload_is_refused(): void {
		$this->expectException( ManifestError::class );

		( new Verifier( $this->public_key ) )->open( $this->envelope( '"just a string"' ) );
	}
}
