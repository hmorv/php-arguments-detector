<?php

namespace DeGraciaMathieu\PhpArgsDetector\Commands;

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
                'min',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'max',
                null,
                InputOption::VALUE_REQUIRED,
                '',
            )
            ->addOption(
                'without-constructor',
                null,
                InputOption::VALUE_NONE,
            );
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $finder = $this->createFinder($input);

        if ($this->noFilesFound($finder)) {

            $output->writeln('No files found to scan');

            return self::SUCCESS;
        }

        $methods = [];
        $detector = new Detector();

        foreach ($finder as $file) {

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
        }

        $printer = new Console([
            'without_constructor' => $input->getOption('without-constructor'),
            'min' => $input->getOption('min'),
            'max' => $input->getOption('max'),
        ]);

        $printer->printData($output, $methods);

        return 1;
    }

    protected function createFinder(InputInterface $input): Finder
    {
        $finder = new Finder();

        $finder->files()->in(
            $input->getArgument('directories'),
        );

        return $finder;
    }

    protected function noFilesFound(Finder $finder): bool
    {
        $filesCount = $finder->count();

        return $filesCount === 0;
    }
}