<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * After json_login checks the credentials, respond with a fresh token pair.
 */
final readonly class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    public function __construct(
        private TokenManager $tokenManager,
        private NormalizerInterface $normalizer,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Unexpected user class.');
        }

        return new JsonResponse($this->normalizer->normalize($this->tokenManager->issue($user)));
    }
}
