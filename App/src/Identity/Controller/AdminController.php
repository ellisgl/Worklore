<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Application\UserNotFound;
use App\Identity\CannotImpersonate;
use App\Identity\Query\UserDirectory;
use App\Identity\Query\UserSummary;
use App\Identity\Security\ImpersonationToken;
use App\Identity\Security\TokenManager;
use App\Identity\User;
use App\Identity\UserRepository;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NotFound;
use App\Shared\Http\OpenApi\PageOf;
use App\Shared\Http\PageRequest;
use App\Shared\UserId;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use JsonException;
use Monolog\Attribute\WithMonologChannel;
use OpenApi\Attributes as OA;
use Psr\Log\LoggerInterface;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

use function in_array;

/**
 * Admin-only (ROLE_ADMIN, see security.yaml access_control).
 */
#[OA\Tag(name: 'Admin')]
#[Route('/api/admin', name: 'api_admin_')]
#[WithMonologChannel('domain')]
final class AdminController extends AbstractController
{
    public function __construct(
        private readonly UserDirectory $directory,
        private readonly UserRepository $users,
        private readonly TokenManager $tokens,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * List users.
     *
     * GET /api/admin/users[?email=part][&page=1&per_page=50]: every user,
     * oldest first; email narrows to addresses containing it.
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    #[PageOf(UserSummary::class, 'The users')]
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(
        #[MapQueryParameter] ?string $email = null,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_UNPROCESSABLE_ENTITY)] PageRequest $paging = new PageRequest(),
    ): JsonResponse {
        return $this->json($this->directory->list($email, $paging->toPage()));
    }

    /**
     * Impersonate a user.
     *
     * POST /api/admin/users/{id}/impersonate → a 15-minute access token that
     * acts as the user. Everything done with it is recorded as done by you
     * (the history shows impersonated_by). It can't change their password,
     * end their sessions, or export or import their data; it can purge
     * records (POST /api/purge/...). Admins and archived users can't be
     * impersonated.
     *
     * @throws RandomException
     * @throws UserNotFound
     */
    #[Item(ImpersonationToken::class, 'The token; send it as Authorization: Bearer ...')]
    #[Invalid('the user is you, an admin, or archived')]
    #[NotFound('User not found.')]
    #[Route('/users/{id}/impersonate', name: 'impersonate', requirements: ['id' => Requirement::UUID], methods: ['POST'])]
    public function impersonate(#[CurrentUser] User $admin, string $id): JsonResponse
    {
        $user = $this->users->find(UserId::fromString($id)) ?? throw new UserNotFound();
        if ($user->id->equals($admin->id)) {
            throw CannotImpersonate::self();
        }
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            throw CannotImpersonate::admin();
        }
        if ($user->isArchived()) {
            throw CannotImpersonate::archived();
        }

        $token = $this->tokens->impersonate($admin, $user);
        $this->logger->notice('Impersonation started', ['impersonator_id' => $admin->id->toRfc4122(), 'user_id' => $user->id->toRfc4122()]);

        return $this->json($token);
    }
}
