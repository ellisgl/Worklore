<?php

declare(strict_types=1);

namespace App\Identity\Query;

use App\Shared\Query\Page;
use App\Shared\Query\PagedSql;
use App\Shared\Query\Paginated;
use App\Shared\Query\ReadsDatabaseTimes;
use DateMalformedStringException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use JsonException;

use function addcslashes;
use function in_array;
use function is_array;
use function json_decode;
use function mb_strtolower;
use function trim;

use const JSON_THROW_ON_ERROR;

/**
 * Users, for admins.
 *
 * @phpstan-type UserRow array{id: string, email: string, roles: string, created_at: string, archived_at: string|null}
 */
final readonly class UserDirectory
{
    use ReadsDatabaseTimes;

    public function __construct(private Connection $db)
    {
    }

    /**
     * Oldest first, optionally only emails containing $email.
     *
     * @return Paginated<UserSummary>
     *
     * @throws DateMalformedStringException
     * @throws Exception
     * @throws JsonException
     */
    public function list(?string $email, Page $page = new Page()): Paginated
    {
        $where = ' FROM app_user';
        $params = [];
        $email = null === $email ? '' : mb_strtolower(trim($email));
        if ('' !== $email) {
            $where .= " WHERE email LIKE :email ESCAPE '\\'";
            $params['email'] = '%'.addcslashes($email, '%_\\').'%';
        }

        /** @var list<UserRow> $rows */
        $rows = $this->db->fetchAllAssociative('SELECT id, email, roles, created_at, archived_at'.$where.' ORDER BY created_at, id'.PagedSql::limit($page), $params);

        $users = [];
        foreach ($rows as $row) {
            $roles = json_decode($row['roles'], true, flags: JSON_THROW_ON_ERROR);
            $users[] = new UserSummary(
                $row['id'],
                $row['email'],
                is_array($roles) && in_array('ROLE_ADMIN', $roles, true),
                self::utc($row['created_at']),
                self::utcOrNull($row['archived_at']),
            );
        }

        return new Paginated($users, $page, PagedSql::count($this->db, $where, $params));
    }
}
