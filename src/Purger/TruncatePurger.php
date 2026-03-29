<?php

namespace App\Purger;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\Purger\ORMPurgerInterface;
use Doctrine\ORM\EntityManagerInterface;

class TruncatePurger implements ORMPurgerInterface
{

    private readonly ORMPurgerInterface $purger;

    public function __construct(?EntityManagerInterface $em = null, array $excluded = [])
    {
        $this->purger = new ORMPurger($em, $excluded);
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->purger->setEntityManager($em);
    }

    public function purge(): void
    {
        $this->purger->setPurgeMode(ORMPurger::PURGE_MODE_TRUNCATE);
        $conn = $this->purger->getObjectManager()->getConnection();

        $pdo = $conn->getNativeConnection();

        $wasInTransaction = $pdo->inTransaction();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 0');

        $this->purger->purge();
        $conn->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
        if ($wasInTransaction && !$pdo->inTransaction()) {
            $pdo->beginTransaction();
        }
    }
}