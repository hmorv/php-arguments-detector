<?php

namespace DeGraciaMathieu\PhpArgsDetector\Commands;

use PhpParser\Error;
use PhpParser\NodeTraverser;
use Symfony\Component\Finder\Finder;
use DeGraciaMathieu\PhpArgsDetector\Detector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DeGraciaMathieu\PhpArgsDetector\Printers\Console;
use DeGraciaMathieu\PhpArgsDetector\NodeMethodExplorer;
use DeGraciaMathieu\PhpArgsDetector\Visitors\FileVisitor;

class InspectCommand extends Command
{
    protected static $defaultName = 'inspect';

    protected function configure($var = null)
    {
        $this
            ->addArgument(
                'directories',
                InputArgument::REQUIRED,
                'Directories to analyze',
            )
            ->addOption(
                'min-args',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'max-args',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'min-weight',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'max-weight',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'limit',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'without-constructor',
                null,
                InputOption::VALUE_NONE,
            )
            ->addOption(
                'sort-by-weight',
                null,
                InputOption::VALUE_NONE,
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $output->writeln('❀ PHP Arguments Detector ❀');

        $finder = $this->createFinder($input);

        if ($this->noFilesFound($finder)) {

            $output->writeln('No files found to scan');

            return self::SUCCESS;
        }

        $methods = $this->diveIntoNodes($output, $finder);

        $output->writeln(PHP_EOL);

        $options = $input->getOptions([
            'without-constructor',
            'sort-by-weight',
            'min-args',
            'max-args',
            'min-weight',
            'max-weight',
            'limit',
        ]);

        $printer = new Console($options);

        $printer->printData($output, $methods);

        return self::SUCCESS;
    }

    protected function createFinder(InputInterface $input): Finder
    {
        $finder = new Finder();

        $directories = $input->getArgument('directories');

        $finder->files()->in($directories);

        return $finder;
    }

    protected function noFilesFound(Finder $finder): bool
    {
        $filesCount = $finder->count();

        return $filesCount === 0;
    }

    protected function startProgressBar(OutputInterface $output, Finder $finder): void
    {
        $this->progressBar = new ProgressBar($output, $finder->count());

        $this->progressBar->start();
    }

    protected function diveIntoNodes(OutputInterface $output, Finder $finder): array
    {
        $this->startProgressBar($output, $finder);

        $methods = [];
        $detector = new Detector();

        foreach ($finder as $file) {

            try {

                $tokens = $detector->parse($file);

                $fileVisitor = new FileVisitor(
                    new NodeMethodExplorer($file),
                );

                $traverser = new NodeTraverser();

                $traverser->addVisitor($fileVisitor);

                $traverser->traverse($tokens);

                foreach ($fileVisitor->getMethods() as $method) {
                    $methods[] = $method;
                }

            } catch (Error $e) {
                //
            }

            $this->progressBar->advance();
        }

        $this->progressBar->finish();

        return $methods;
    }
}