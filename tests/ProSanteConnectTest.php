<?php

namespace GroupePSIH\OAuth2\Client\Test;

use GuzzleHttp\ClientInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Token\AccessToken;
use GroupePSIH\OAuth2\Client\Provider\ProSanteConnect;
use GroupePSIH\OAuth2\Client\Provider\ProSanteConnectResourceOwner as ResourceOwner;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;

class ProSanteConnectTest extends TestCase
{
    private ProSanteConnect $provider;

    protected function setUp(): void
    {
        $this->provider = new ProSanteConnect([
            'clientId' => 'test-client-id',
            'clientSecret' => 'test-client-secret',
            'redirectUri' => 'test-redirect-uri',
        ]);
    }

    /**
     * @param string $responseBody
     * @param int $statusCode
     * @return MockObject
     */
    private function createMockResponse(string $responseBody, int $statusCode = 200): MockObject
    {
        /** @var MockObject $response */
        $response = $this->createMock(ResponseInterface::class);

        $response->method('getStatusCode')
            ->willReturn($statusCode);

        $response->method('getBody')
            ->willReturn($responseBody);

        $response->method('getHeader')
            ->with('content-type')
            ->willReturn('application/json');

        return $response;
    }

    public function testAuthorizationUrl(): void
    {
        $url = $this->provider->getAuthorizationUrl(
            [
                'scope' => ['public', 'profile']
            ]
        );
        $uri = parse_url($url);
        $query = [];

        if (\is_array($uri) && isset($uri['query'])) {
            parse_str($uri['query'], $query);
        }

        $this->assertEquals('public,profile', $query['scope']);
        $this->assertEquals('test-client-id', $query['client_id']);
        $this->assertEquals('test-redirect-uri', $query['redirect_uri']);
        $this->assertArrayHasKey('response_type', $query);
    }

    public function testGetBaseAccessTokenUrl(): void
    {
        $params = [];

        $url = $this->provider->getBaseAccessTokenUrl($params);
        $uri = parse_url($url);

        $path = '';
        if (\is_array($uri) && isset($uri['path'])) {
            $path = $uri['path'];
        }

        $this->assertEquals('/auth/realms/esante-wallet/protocol/openid-connect/token', $path);
    }

    public function testGetAccessTokenWithAuthorizationCode(): void
    {
        $response = $this->createMockResponse('{' .
            '"access_token": "test-access-token",' .
            '"token_type": "bearer",' .
            '"refresh_token": "test-refresh-token",' .
            '"expires_in": 7200,' .
            '"scope": "public",' .
            '"created_at": 1666964584' .
            '}');

        /** @var MockObject|ClientInterface $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->willReturn($response);

        $this->provider->setHttpClient($client);

        $token = $this->provider->getAccessToken('authorization_code', ['code' => 'test-authorization-code']);

        $this->assertEquals('test-access-token', $token->getToken());
        $this->assertEquals('test-refresh-token', $token->getRefreshToken());
        $this->assertLessThanOrEqual(time() + 7200, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
    }

    public function testGetAccessTokenWithClientCredentials(): void
    {
        $response = $this->createMockResponse('{' .
            '"access_token": "test-access-token",' .
            '"token_type": "bearer",' .
            '"expires_in": 7200,' .
            '"scope": "public",' .
            '"created_at": 1666964584' .
            '}');
        /** @var MockObject|ClientInterface $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->withConsecutive([])
            ->willReturn($response);

        $this->provider->setHttpClient($client);
        $token = $this->provider->getAccessToken('client_credentials');

        $this->assertEquals('test-access-token', $token->getToken());
        $this->assertNull($token->getRefreshToken());
        $this->assertLessThanOrEqual(time() + 7200, $token->getExpires());
        $this->assertGreaterThanOrEqual(time(), $token->getExpires());
    }

    public function testGetResourceOwner(): void
    {
        $response = $this->createMockResponse('{' .
            '"SubjectNameID": 1234567890' .
            '}');
        /** @var MockObject|ClientInterface $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->willReturn($response);

        $this->provider->setHttpClient($client);

        /** @var MockObject|AccessToken $accessToken */
        $accessToken = $this->createMock(AccessToken::class);
        $accessToken->method('getToken')
            ->willReturn('test-access-token');

        $resourceOwner = $this->provider->getResourceOwner($accessToken);
        $this->assertInstanceOf(ResourceOwner::class, $resourceOwner);
        $this->assertEquals([
            'SubjectNameID' => 1234567890
        ], $resourceOwner->toArray());
    }

    public function testCanHandleErrors(): void
    {
        $response = $this->createMockResponse('{' .
            '"error_description": "This is the description",' .
            '"error": "error_name"}', 403);
        /** @var MockObject|ClientInterface $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->willReturn($response);

        $this->provider->setHttpClient($client);

        $this->expectException(IdentityProviderException::class);
        $this->expectExceptionMessage("403 - This is the description");
        $this->provider->getAccessToken('authorization_code', ['code' => 'test-authorization-code']);
    }

    public function testCanHandleInvalidArgument(): void
    {
        $response = $this->createMockResponse("{}", 403);
        /** @var MockObject|ClientInterface $client */
        $client = $this->createMock(ClientInterface::class);
        $client->method('send')
            ->willReturn($response);

        $this->provider->setHttpClient($client);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Required option not passed: "access_token"');
        $this->provider->getAccessToken('authorization_code', ['code' => 'test-authorization-code']);
    }
}
