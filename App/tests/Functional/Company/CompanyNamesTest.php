<?php

declare(strict_types=1);

namespace App\Tests\Functional\Company;

use App\Company\Query\CompanyNames;
use App\Identity\User;
use App\Tests\Functional\CreatesRecords;
use DateMalformedStringException;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The name checks commands run before recording a name: case-insensitive,
 * archived records included, scoped to the owner / company / product.
 */
final class CompanyNamesTest extends KernelTestCase
{
    use CreatesRecords;

    private CompanyNames $names;
    private User $me;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->names = self::getContainer()->get(CompanyNames::class);
        $this->me = $this->owner();
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testCompanyNames(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $this->company($this->me, 'Gone', archived: true);
        $other = $this->owner('other@example.com');

        self::assertTrue($this->names->companyNameTaken($this->me->id, ' ACME '), 'Trimmed, any case');
        self::assertTrue($this->names->companyNameTaken($this->me->id, 'gone'), 'Archived ones hold their name');
        self::assertFalse($this->names->companyNameTaken($this->me->id, 'Globex'));
        self::assertFalse($this->names->companyNameTaken($other->id, 'Acme'), 'Free for another user');
        self::assertFalse($this->names->companyNameTaken($this->me->id, 'acme', except: $acme->id()), 'Its own name');
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testProductNames(): void
    {
        $acme = $this->company($this->me, 'Acme');
        $globex = $this->company($this->me, 'Globex');
        $widget = $this->product($acme, 'Widget');
        $this->product($acme, 'Gadget', archived: true);

        self::assertTrue($this->names->productNameTaken($acme->id(), ' WIDGET '));
        self::assertTrue($this->names->productNameTaken($acme->id(), 'gadget'));
        self::assertFalse($this->names->productNameTaken($globex->id(), 'Widget'), 'Free in another company');
        self::assertFalse($this->names->productNameTaken($acme->id(), 'widget', except: $widget->id()));
        self::assertTrue($this->names->productNameTaken($acme->id(), 'gadget', except: $widget->id()));
    }

    /**
     * @throws DateMalformedStringException
     * @throws Exception
     */
    public function testProjectNames(): void
    {
        $acme = $this->company($this->me, 'Acme');
        [$widget, $gadget] = [$this->product($acme, 'Widget'), $this->product($acme, 'Gadget')];
        $launch = $this->project($widget, 'Launch');
        $this->project($widget, 'Beta', archived: true);

        self::assertTrue($this->names->projectNameTaken($widget->id(), ' LAUNCH '));
        self::assertTrue($this->names->projectNameTaken($widget->id(), 'beta'));
        self::assertFalse($this->names->projectNameTaken($gadget->id(), 'Launch'), 'Free in another product');
        self::assertFalse($this->names->projectNameTaken($widget->id(), 'launch', except: $launch->id()));
    }
}
