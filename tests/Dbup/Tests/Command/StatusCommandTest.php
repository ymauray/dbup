<?php
namespace Dbup\Tests\Command;

use Dbup\Application;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Dbup\Command\StatusCommand;

class StatusCommandTest extends TestCase
{
    public function setUp() : void
    {
        \Hamcrest\Util::registerGlobalFunctions();
    }

    public function tearDown(): void
    {
        $this->addToAssertionCount(\Hamcrest\MatcherAssert::getCount());
    }

    public function testSpecificPropertiesIni()
    {
        $application = new Application();
        $application->add(new StatusCommand());

        $command = $application->find('status');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(),
                '--ini' => __DIR__ . '/../../.dbup/properties.ini.test',
            ]);

        assertThat($commandTester->getDisplay(), is(containsString('| appending...        | V12__sample12_select.sql |')));
    }

    public function testCatchExceptionNonExistIni()
    {
        $this->expectException(\Dbup\Exception\RuntimeException::class);
        $application = new Application();
        $application->add(new StatusCommand());

        $command = $application->find('status');
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--ini' => 'notfound.ini']);
    }
}