<?php

declare(strict_types=1);

/*
 * This file is part of the EcommitMessengerSupervisorBundle package.
 *
 * (c) E-commit <contact@e-commit.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ecommit\MessengerSupervisorBundle\Command;

use Ecommit\MessengerSupervisorBundle\Supervisor\Supervisor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\TableSeparator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class ManageCommand extends Command
{
    /**
     * @var Supervisor
     */
    protected $supervisor;

    public function __construct(Supervisor $supervisor)
    {
        $this->supervisor = $supervisor;

        parent::__construct();
    }

    protected static $defaultName = 'ecommit:supervisor';

    protected static $defaultDescription = 'Manage Supervisor processes';

    protected function configure(): void
    {
        $this
            ->addArgument('action', InputArgument::REQUIRED, 'start|stop|status')
            ->addArgument('programs', InputArgument::REQUIRED | InputArgument::IS_ARRAY, 'Program(s) name(s) or "all"')
            ->addOption('nagios', null, InputOption::VALUE_NONE, 'Suitable for using as a nagios NRPE command')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $availableActions = ['start', 'stop', 'status'];
        $action = $input->getArgument('action');
        if (!\in_array($action, $availableActions)) {
            $output->writeln(sprintf('<error>Bad action "%s" (Available: %s)</error>', $action, implode(', ', $availableActions)));

            return 1;
        }

        if ($input->getOption('nagios') && 'status' !== $action) {
            $output->writeln('<error>Nagios option can only be used with the "status" action</error>');

            return 1;
        }

        $availablePrograms = $this->supervisor->getPrograms();
        $programs = $input->getArgument('programs');
        sort($availablePrograms);
        foreach ($programs as $program) {
            if (!\in_array($program, $availablePrograms) && 'all' !== $program) {
                $output->writeln(sprintf('<error>Bad program "%s" (Available: %s)</error>', $program, implode(', ', array_merge($availablePrograms, ['all']))));

                return 1;
            }
        }
        if (\in_array('all', $programs)) {
            $programs = $availablePrograms;
        }

        switch ($action) {
            case 'start':
                return $this->startAction($programs, $output);
            case 'stop':
                return $this->stopAction($programs, $output);
            case 'status':
                return $this->statusAction($programs, $input, $output);
        }

        return 1;
    }

    protected function startAction(array $programs, OutputInterface $output): int
    {
        foreach ($programs as $program) {
            $output->writeln(sprintf('Starting %s program', $program));
            $this->supervisor->startProgram($program);
            $output->writeln(sprintf('%s program is started', $program));
        }

        return 0;
    }

    protected function stopAction(array $programs, OutputInterface $output): int
    {
        foreach ($programs as $program) {
            $output->writeln(sprintf('Stopping %s program', $program));
            $this->supervisor->stopProgram($program);
            $output->writeln(sprintf('%s program is stopped', $program));
        }

        return 0;
    }

    protected function statusAction(array $programs, InputInterface $input, OutputInterface $output): int
    {
        if ($input->getOption('nagios')) {
            return $this->nagiosStatusAction($programs, $input, $output);
        }
        $io = new SymfonyStyle($input, $output);
        $result = 0;

        $rows = [];
        $status = $this->supervisor->getProgramsStatus($programs);
        foreach ($status as $programName => $supervisorProcesses) {
            $transports = $this->supervisor->getTransportsNamesByProgram($programName);
            if (0 === \count($supervisorProcesses)) {
                $rows[] = ['Program', $programName];
                $rows[] = ['Transport(s)', implode(', ', $transports)];
                $rows[] = ['', '<error>Not found in Supervisor</error>'];
                $rows[] = new TableSeparator();
                $result = 1;
            }
            foreach ($supervisorProcesses as $supervisorProcess) {
                $color = ($supervisorProcess->isRunning()) ? 'info' : 'error';

                $rows[] = ['Program', $programName];
                $rows[] = ['Transport(s)', implode(', ', $transports)];
                $rows[] = ['Process', $supervisorProcess['name']];
                $rows[] = ['State', sprintf('<%s>%s</%s>', $color, $supervisorProcess['statename'], $color)];
                $rows[] = ['PID', $supervisorProcess['pid']];
                $rows[] = new TableSeparator();
                if (!$supervisorProcess->isRunning()) {
                    $result = 1;
                }
            }
        }

        array_pop($rows);
        $io->table([], $rows);

        return $result;
    }

    protected function nagiosStatusAction(array $programs, InputInterface $input, OutputInterface $output): int
    {
        $runningProcesses = 0;
        $stoppedProcesses = 0;

        $status = $this->supervisor->getProgramsStatus($programs);
        foreach ($status as $programName => $supervisorProcesses) {
            if (0 === \count($supervisorProcesses)) {
                ++$stoppedProcesses;
            }
            foreach ($supervisorProcesses as $supervisorProcess) {
                if ($supervisorProcess->isRunning()) {
                    ++$runningProcesses;
                } else {
                    ++$stoppedProcesses;
                }
            }
        }

        if ($stoppedProcesses > 0) {
            $output->writeln(sprintf('CRITICAL - Running processes: %s Stopped processes: %s', $runningProcesses, $stoppedProcesses));

            return 2;
        }

        $output->writeln(sprintf('OK - Running processes: %s Stopped processes: %s', $runningProcesses, $stoppedProcesses));

        return 0;
    }
}
