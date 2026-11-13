<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Security\TokenManager;
use App\Identity\Security\TokenPair;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[OA\Tag(name: 'Authentication')]
#[Route('/api/auth', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly TokenManager $tokenManager)
    {
    }

    /**
     * Log in.
     *
     * Exchanges an email and password for a token pair. Send the access token
     * as "Authorization: Bearer <token>"; when it expires, get a new pair from
     * /api/auth/refresh. Repeated failures are throttled.
     *
     * Handled by the firewall's json_login (see security.yaml); never reached.
     */
    #[OA\RequestBody(required: true, content: new OA\JsonContent(
        required: ['email', 'password'],
        properties: [
            new OA\Property(property: 'email', type: 'string', format: 'email', example: 'me@example.com'),
            new OA\Property(property: 'password', type: 'string', format: 'password'),
        ],
        type: 'object',
    ))]
    #[Item(TokenPair::class, 'Logged in')]
    #[OA\Response(response: 401, description: 'Wrong email or password, an archived account, or too many attempts', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Security(name: null)]
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        throw new LogicException('This route is handled by the json_login authenticator.');
    }

    /**
     * Get a new token pair.
     *
     * Exchanges a refresh token for a new pair; the old pair stops working,
     * and a refresh token can be used once.
     *
     * @throws RandomException
     */
    #[Item(TokenPair::class, 'A new token pair')]
    #[OA\Response(response: 401, description: 'Unknown, expired or already used refresh token', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Invalid or expired refresh token.']))]
    #[Invalid]
    #[Security(name: null)]
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(#[MapRequestPayload] RefreshTokenRequest $payload): JsonResponse
    {
        $pair = $this->tokenManager->refresh($payload->refreshToken);
        if (null === $pair) {
            return $this->json(['error' => 'Invalid or expired refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($pair);
    }

    /**
     * Log out.
     *
     * Revokes the access token sent with the request, and its refresh token.
     */
    #[NoContent('Logged out')]
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function logout(Request $request): Response
    {
        $accessToken = new HeaderAccessTokenExtractor()->extractAccessToken($request);
        if (null !== $accessToken) {
            $this->tokenManager->revokeByAccessToken($accessToken);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
