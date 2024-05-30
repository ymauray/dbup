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

use Dbup\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Dbup\Exception\RuntimeException;
use Symfony\Component\Console\Helper\TableHelper;

/**
 * @author Masao Maeda <brt.river@gmail.com>
 */
class StatusCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('status')
            ->setDescription('Display migration status')
            ->addOption(
                'ini',
                null,
                InputOption::VALUE_REQUIRED,
                'If set, the task uses it instead of default one'
            )
            ->setHelp('
The <info>dbup status</info> comand shows migration statuses.
If migration sql files were applied, display applied time and file names.
otherwise not applied, display <info>appending...</info>.
dbup checks whether sql files were applied or not by comparing the file names in <info>sql directory</info> and <info>applied directory</info>.
            ')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int|null
    {
        /** @var Application $app */
        $app = $this->getApplication();
        $ini = $input->getOption('ini');
        if (!$ini) {
            $ini = $app->getIni();
        }
        if (!file_exists($ini)) {
            throw new RuntimeException($ini . ' does not exist.');
        }

        $app->setConfigFromIni($ini);

        $statuses = $app->getStatuses();

        $output->writeln('<info>dbup migration status</info>');

        $rows = [];
        foreach ($statuses as $status) {
            $appliedAt = $status->appliedAt === '' ? "appending..." : $status->appliedAt;
            $rows[] = [$appliedAt, $status->file->getFileName()];
        }

        /** @var TableHelper $table */
        $table = $app->getHelperSet()->get('table');
        $table
            ->setHeaders(['Applied At', 'Migration Sql File'])
            ->setRows($rows)
        ;
        $table->render($output);

        return null;
    }
}
