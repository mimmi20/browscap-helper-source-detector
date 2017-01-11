<?php

namespace BrowscapHelper\Source;

use Monolog\Logger;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class DetectorSource implements SourceInterface
{
    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     * @param int                                               $limit
     *
     * @return \Generator
     */
    public function getUserAgents(Logger $logger, OutputInterface $output, $limit = 0)
    {
        $counter   = 0;
        $allAgents = [];

        foreach ($this->loadFromPath($output) as $dataFile) {
            if ($limit && $counter >= $limit) {
                return;
            }

            foreach ($dataFile as $row) {
                if ($limit && $counter >= $limit) {
                    return;
                }

                if (!isset($row->ua)) {
                    continue;
                }

                if (array_key_exists($row->ua, $allAgents)) {
                    continue;
                }

                yield $row->ua;
                $allAgents[$row->ua] = 1;
                ++$counter;
            }
        }
    }

    /**
     * @param \Monolog\Logger                                   $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Generator
     */
    public function getTests(Logger $logger, OutputInterface $output)
    {
        $allTests = [];

        foreach ($this->loadFromPath($output) as $dataFile) {
            foreach ($dataFile as $test) {
                if (!isset($test->ua)) {
                    continue;
                }

                if (array_key_exists($test->ua, $allTests)) {
                    continue;
                }

                yield [$row->ua => $test];
                $allTests[$row->ua] = 1;
            }
        }
    }

    /**
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return \Generator
     */
    private function loadFromPath(OutputInterface $output = null)
    {
        $path = 'vendor/mimmi20/browser-detector/tests/issues';

        if (!file_exists($path)) {
            return;
        }

        $output->writeln('    reading path ' . $path);

        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            $filepath = $file->getPathname();

            $output->write('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT), false);
            switch ($file->getExtension()) {
                case 'json':
                    yield (array) json_decode(file_get_contents($filepath));
                    break;
                default:
                    // do nothing here
                    break;
            }
        }
    }
}
