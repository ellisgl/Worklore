<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\IncorrectPassword;
use App\Identity\Security\TokenManager;
use App\Identity\Security\TokenPair;
use App\Identity\User;
use App\Identity\UserManager;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Validator\Exception\ValidationFailedException;

/**
 * The signed-in user's own account. Account changes go through UserManager,
 * which the console commands share.
 */
#[OA\Tag(name: 'Account')]
#[Route('/api/me', name: 'api_me_')]
final class AccountController extends AbstractController
{
    public function __construct(
        private readonly UserManager $users,
        private readonly TokenManager $tokens,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    /**
     * Get your account.
     *
     * GET /api/me: who the access token belongs to.
     */
    #[Item(AccountSummary::class, 'Your account')]
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json(AccountSummary::of($user));
    }

    /**
     * Change your password.
     *
     * POST /api/me/password {"current_password": "...", "new_password": "..."} → 200
     * with a new token pair. Every session ends, this one included; carry on
     * with the tokens in the response.
     *
     * @throws IncorrectPassword
     * @throws RandomException
     * @throws ValidationFailedException never: the request was validated with the same rules
     */
    #[Item(TokenPair::class, 'Changed; use these tokens from now on')]
    #[Invalid('the current password is incorrect')]
    #[Route('/password', name: 'change_password', methods: ['POST'])]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ChangePasswordRequest $payload,
    ): JsonResponse {
        if (!$this->hasher->isPasswordValid($user, $payload->currentPassword)) {
            throw new IncorrectPassword();
        }

        $this->users->changePassword($user, $payload->newPassword);

        return $this->json($this->tokens->issue($user));
    }
}
