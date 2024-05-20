<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Command\Command;

$app = new Application();
$app->register('dirsearch')
    ->addArgument('url', InputArgument::REQUIRED, 'Path to url list')
    ->addArgument('path', InputArgument::OPTIONAL, 'Path to list', 'pathlist.txt')
    ->addArgument('timeout', InputArgument::OPTIONAL, 'Default timeout  3s', 3)
    ->addArgument('output', InputArgument::OPTIONAL, 'Output file to write to')
    ->setCode(
        function (InputInterface $input, OutputInterface $output): int {
            $outputError = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
            $urls = file($input->getArgument('url'), FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);
            $pathList = file($input->getArgument('path'), FILE_SKIP_EMPTY_LINES | FILE_IGNORE_NEW_LINES);

            $ch = curl_init();

            foreach ($urls as $url) {
                // filter urls
                if (!filter_var($url, FILTER_VALIDATE_URL)) {
                    $outputError->writeln(sprintf('<error>%s is missing http(s)<error>', $url));
                    continue;
                }
                foreach ($pathList as $path) {
                    $entry = rtrim($url, '/') . '/' . ltrim($path, '/');
                    curl_setopt($ch, CURLOPT_URL, $entry);
                    curl_setopt($ch, CURLOPT_TIMEOUT, $input->getArgument('timeout'));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_NOBODY, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_exec($ch);
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                    switch ($statusCode) {
                        case 200:
                            $output->writeln(sprintf('<info>%s: %s</info>', $statusCode, $entry));
                            break;
                        case 403:
                            $output->writeln(sprintf('<question>%s: %s</question>', $statusCode, $entry));
                            break;
                        default:
                            continue 2;
                    }
                    if ($input->getArgument('output')) {
                        file_put_contents($input->getArgument('output'), $entry . PHP_EOL, FILE_APPEND);
                    }
                }
            }
            curl_close($ch);
            return Command::SUCCESS;
        }
    );
$app->run();