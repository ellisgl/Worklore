<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\RefreshTokenRequest;
use App\Security\TokenManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/auth', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(private readonly TokenManager $tokenManager)
    {
    }

    /**
     * Handled by the firewall's json_login (see security.yaml); never reached.
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(): never
    {
        throw new \LogicException('This route is handled by the json_login authenticator.');
    }

    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(#[MapRequestPayload] RefreshTokenRequest $payload): JsonResponse
    {
        $pair = $this->tokenManager->refresh($payload->refreshToken);
        if (null === $pair) {
            return $this->json(['error' => 'Invalid or expired refresh token.'], Response::HTTP_UNAUTHORIZED);
        }

        return $this->json($pair);
    }

    #[Route('/logout', name: 'logout', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function logout(Request $request): Response
    {
        $accessToken = (new HeaderAccessTokenExtractor())->extractAccessToken($request);
        if (null !== $accessToken) {
            $this->tokenManager->revokeByAccessToken($accessToken);
        }

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
