<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Functional\ApiTestCase;
use JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function is_array;
use function sort;
use function str_starts_with;
use function strtolower;

/**
 * The OpenAPI docs: served without a token, and complete, so a new endpoint
 * can't ship undocumented.
 */
final class ApiDocTest extends ApiTestCase
{
    /**
     * @throws JsonException
     */
    public function testSwaggerUiIsServedWithoutAToken(): void
    {
        $this->client->request('GET', '/doc');

        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('swagger', (string) $response->getContent());
    }

    /**
     * @throws JsonException
     */
    public function testEveryApiRouteIsDocumented(): void
    {
        $spec = $this->spec();
        $paths = $spec['paths'] ?? null;
        self::assertIsArray($paths);

        $router = self::getContainer()->get(RouterInterface::class);
        $missing = [];
        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/api/')) {
                continue;
            }
            foreach ($route->getMethods() as $method) {
                $pathItem = $paths[$route->getPath()] ?? null;
                $operation = is_array($pathItem) ? ($pathItem[strtolower($method)] ?? null) : null;
                if (!is_array($operation)) {
                    $missing[] = "$method {$route->getPath()} ($name): not in the spec";
                    continue;
                }
                if ('' === ($operation['summary'] ?? '')) {
                    $missing[] = "$method {$route->getPath()}: no summary";
                }
                $responses = is_array($operation['responses'] ?? null) ? $operation['responses'] : [];
                $success = array_filter(array_keys($responses), static fn (int|string $code) => str_starts_with((string) $code, '2'));
                if ([] === $success) {
                    $missing[] = "$method {$route->getPath()}: no 2xx response";
                }
            }
        }

        self::assertSame([], $missing);
    }

    /**
     * @throws JsonException
     */
    public function testOnlyLoginAndRefreshArePublic(): void
    {
        $paths = $this->spec()['paths'] ?? null;
        self::assertIsArray($paths);

        $public = [];
        foreach ($paths as $path => $operations) {
            self::assertIsArray($operations);
            foreach ($operations as $method => $operation) {
                self::assertIsArray($operation);
                if ([] === ($operation['security'] ?? null)) {
                    $public[] = "$method $path";
                }
            }
        }
        sort($public);

        self::assertSame(['post /api/auth/login', 'post /api/auth/refresh'], $public);
    }

    /**
     * @return array<array-key, mixed>
     *
     * @throws JsonException
     */
    private function spec(): array
    {
        $this->client->request('GET', '/doc.json');
        $response = $this->client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode());

        $spec = $this->decode($response);
        self::assertSame('Worklore API', is_array($spec['info'] ?? null) ? ($spec['info']['title'] ?? null) : null);

        return $spec;
    }
}
