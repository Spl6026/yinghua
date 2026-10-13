<?php

declare(strict_types=1);

namespace Yinghua\Tests;

use PHPat\Selector\Selector;
use PHPat\Test\PHPat;
use PHPat\Test\Builder\Rule;

class ArchitectureTest
{
    public function testControllersDoNotQueryDatabase(): Rule
    {
        return PHPat::rule()->classes(Selector::inNamespace('Yinghua\\Controllers'))
            ->shouldNotDependOn()->classes(Selector::inNamespace('Yinghua\\Repositories'), Selector::classname(\PDO::class));
    }

    public function testRepositoriesDoNotCallServices(): Rule
    {
        return PHPat::rule()->classes(Selector::inNamespace('Yinghua\\Repositories'))
            ->shouldNotDependOn()->classes(Selector::inNamespace('Yinghua\\Services'), Selector::inNamespace('Yinghua\\Controllers'), Selector::inNamespace('Yinghua\\Http'));
    }

    public function testServicesDoNotUseHttp(): Rule
    {
        return PHPat::rule()->classes(Selector::inNamespace('Yinghua\\Services'))
            ->shouldNotDependOn()->classes(Selector::inNamespace('Yinghua\\Http'), Selector::inNamespace('Yinghua\\Controllers'));
    }

    public function testServicesDoNotUseDatabaseDriver(): Rule
    {
        return PHPat::rule()->classes(Selector::inNamespace('Yinghua\\Services'))
            ->shouldNotDependOn()->classes(Selector::classname(\PDO::class), Selector::classname(\PDOException::class), Selector::classname(\PDOStatement::class));
    }

    public function testPoliciesAreIndependentOfPersistence(): Rule
    {
        return PHPat::rule()->classes(Selector::inNamespace('Yinghua\\Policies'), Selector::inNamespace('Yinghua\\Models'), Selector::inNamespace('Yinghua\\DTO'))
            ->shouldNotDependOn()->classes(Selector::inNamespace('Yinghua\\Repositories'), Selector::inNamespace('Yinghua\\Http'), Selector::classname(\PDO::class));
    }
}
