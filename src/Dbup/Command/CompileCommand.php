<?php

/*
 * This file is part of Dbup.
 *
 * (c) Masao Maeda <brt.river@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dbup\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Dbup\Util\Compiler;

/**
 * @author Masao Maeda <brt.river@gmail.com>
 */
class CompileCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('compile')
            ->setDescription('Compile dbup.phar')
            ->setHelp('
The <info>dbup compile</info> comand compile dbup and make a new dbup.phar file.
            ')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int|null
    {
        $compiler = new Compiler();
        $compiler->compile();

        return null;
    }
}
