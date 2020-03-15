<?php

/*
 * This file is part of Psy Shell.
 *
 * (c) 2012-2020 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Psy\Test\Command;

use Psy\Command\PsyVersionCommand;
use Psy\Shell;
use Symfony\Component\Console\Tester\CommandTester;

class PsyVersionCommandTest extends \PHPUnit\Framework\TestCase
{
    public function testExecute()
    {
        $command = new PsyVersionCommand();
        $command->setApplication(new Shell());
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertContains(Shell::VERSION, $tester->getDisplay());
    }
}
