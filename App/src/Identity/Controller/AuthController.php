<?php

declare(strict_types=1);

namespace App\Identity\Controller;

use App\Identity\Security\TokenManager;
use App\Identity\Security\TokenPair;
use App\Identity\UserAlreadyExistsException;
use App\Identity\UserManager;
use App\Shared\Http\OpenApi\Conflict;
use App\Shared\Http\OpenApi\Invalid;
use App\Shared\Http\OpenApi\Item;
use App\Shared\Http\OpenApi\NoContent;
use LogicException;
use Nelmio\ApiDocBundle\Attribute\Model;
use Nelmio\ApiDocBundle\Attribute\Security;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\AccessToken\HeaderAccessTokenExtractor;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Exception\ValidationFailedException;

use function max;
use function time;

#[OA\Tag(name: 'Authentication')]
#[Route('/api/auth', name: 'api_auth_', format: 'json')]
final class AuthController extends AbstractController
{
    public function __construct(
        private readonly TokenManager $tokenManager,
        private readonly UserManager $users,
        // Named autowiring: the "signup" limiter in rate_limiter.yaml
        private readonly RateLimiterFactoryInterface $signupLimiter,
        #[Autowire(env: 'bool:APP_SIGNUP_ENABLED')]
        private readonly bool $signupEnabled,
    ) {
    }

    /**
     * Create an account.
     *
     * POST /api/auth/signup {"email": "...", "password": "..."} → 201 with a
     * token pair, already logged in. Only when APP_SIGNUP_ENABLED is on;
     * otherwise 403. Limited to a few sign-ups per address per hour.
     *
     * @throws RandomException
     * @throws ValidationFailedException never: the request was validated with the same rules
     */
    #[OA\Response(response: 201, description: 'Created and logged in', content: new OA\JsonContent(ref: new Model(type: TokenPair::class)))]
    #[OA\Response(response: 403, description: 'Sign-up is turned off (APP_SIGNUP_ENABLED)', content: new OA\JsonContent(ref: '#/components/schemas/Error', example: ['error' => 'Sign-up is disabled.']))]
    #[Conflict('An account with this email already exists, ignoring case', 'An account with this email already exists.')]
    #[Invalid]
    #[OA\Response(response: 429, description: 'Too many sign-ups from this address; see Retry-After', content: new OA\JsonContent(ref: '#/components/schemas/Error'))]
    #[Security(name: null)]
    #[Route('/signup', name: 'signup', methods: ['POST'])]
    public function signUp(#[MapRequestPayload] SignUpRequest $payload, Request $request): JsonResponse
    {
        if (!$this->signupEnabled) {
            return $this->json(['error' => 'Sign-up is disabled.'], Response::HTTP_FORBIDDEN);
        }

        $limit = $this->signupLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            return $this->json(
                ['error' => 'Too many sign-ups from this address; try again later.'],
                Response::HTTP_TOO_MANY_REQUESTS,
                ['Retry-After' => (string) max(1, $limit->getRetryAfter()->getTimestamp() - time())],
            );
        }

        try {
            $user = $this->users->create($payload->email, $payload->password);
        } catch (UserAlreadyExistsException) {
            return $this->json(['error' => 'An account with this email already exists.'], Response::HTTP_CONFLICT);
        }

        return $this->json($this->tokenManager->issue($user), Response::HTTP_CREATED);
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
