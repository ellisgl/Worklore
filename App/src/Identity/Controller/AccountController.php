<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Application\ChangePassword;
use App\Identity\Application\RevokeSessions;
use App\Identity\Security\TokenManager;
use App\Identity\Security\TokenPair;
use App\Identity\User;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use App\Shared\Secret;
use App\Shared\Security\Impersonation;
use App\Shared\Security\NotWhileImpersonating;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * The signed-in user's own account. Changes dispatch the Identity commands,
 * the same ones the console commands use.
 */
#[OA\Tag(name: 'Account')]
#[Route('/api/me', name: 'api_me_')]
final class AccountController extends AbstractController
{
    public function __construct(
        // Named autowiring: $commandBus is the command.bus service.
        private readonly MessageBusInterface $commandBus,
        private readonly TokenManager $tokens,
        private readonly Impersonation $impersonation,
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
        return $this->json(AccountSummary::of($user, $this->impersonation->impersonatorId()));
    }

    /**
     * Log out everywhere.
     *
     * POST /api/me/sessions/revoke → 204. Ends every session (access and
     * refresh tokens) on every device, this one included.
     *
     * @throws ExceptionInterface
     */
    #[NoContent('Every session ended')]
    #[NotWhileImpersonating]
    #[Route('/sessions/revoke', name: 'revoke_sessions', methods: ['POST'])]
    public function revokeSessions(#[CurrentUser] User $user): Response
    {
        $this->commandBus->dispatch(new RevokeSessions($user->id));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }

    /**
     * Change your password.
     *
     * POST /api/me/password {"current_password": "...", "new_password": "..."} → 200
     * with a new token pair. Every session ends, this one included; carry on
     * with the tokens in the response.
     *
     * @throws ExceptionInterface
     * @throws RandomException
     */
    #[Item(TokenPair::class, 'Changed; use these tokens from now on')]
    #[Invalid('the current password is incorrect')]
    #[NotWhileImpersonating]
    #[Route('/password', name: 'change_password', methods: ['POST'])]
    public function changePassword(
        #[CurrentUser] User $user,
        #[MapRequestPayload] ChangePasswordRequest $payload,
    ): JsonResponse {
        $this->commandBus->dispatch(new ChangePassword($user->id, new Secret($payload->newPassword), new Secret($payload->currentPassword)));

        return $this->json($this->tokens->issue($user));
    }
}
