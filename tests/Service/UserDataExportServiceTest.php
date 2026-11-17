<?php

declare(strict_types=1);

namespace FrankProjects\UltimateWarfare\Tests\Service;

use DateTimeImmutable;
use FrankProjects\UltimateWarfare\Entity\User;
use FrankProjects\UltimateWarfare\Entity\UserIpLog;
use FrankProjects\UltimateWarfare\Repository\UserIpLogRepository;
use FrankProjects\UltimateWarfare\Service\UserDataExportService;
use PHPUnit\Framework\TestCase;

class UserDataExportServiceTest extends TestCase
{
    public function testExportContainsAccountAndIpLog(): void
    {
        $user = new User();
        $user->setUsername('frank');
        $user->setEmail('frank@example.com');
        $user->setSignedUpAt(new DateTimeImmutable('2026-01-01 10:00:00'));

        $userIpLogRepository = self::createStub(UserIpLogRepository::class);
        $userIpLogRepository->method('findByUser')->willReturn([
            UserIpLog::create($user, '10.0.0.1', new DateTimeImmutable('2026-01-04 10:00:00')),
        ]);

        $export = (new UserDataExportService($userIpLogRepository))->export($user);

        self::assertSame(['exportedAt', 'account', 'ipLog'], array_keys($export));
        self::assertSame(
            [
                'username' => 'frank',
                'email' => 'frank@example.com',
                'signedUpAt' => (new DateTimeImmutable('2026-01-01 10:00:00'))->format(DATE_ATOM),
            ],
            $export['account']
        );

        self::assertIsArray($export['ipLog']);
        self::assertIsArray($export['ipLog'][0]);
        self::assertSame('10.0.0.1', $export['ipLog'][0]['ip']);
    }
}
