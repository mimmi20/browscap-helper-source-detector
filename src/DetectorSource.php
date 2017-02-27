<?php
/**
 * This file is part of the browscap-helper-source-detector package.
 *
 * Copyright (c) 2016-2017, Thomas Mueller <mimmi20@live.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types = 1);
namespace BrowscapHelper\Source;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UaResult\Result\ResultFactory;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class DetectorSource implements SourceInterface
{
    /**
     * @var \Symfony\Component\Console\Output\OutputInterface
     */
    private $output = null;

    /**
     * @var null
     */
    private $logger = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \Psr\Log\LoggerInterface                          $logger
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     */
    public function __construct(LoggerInterface $logger, OutputInterface $output, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->output = $output;
        $this->cache  = $cache;
    }

    /**
     * @param int $limit
     *
     * @return string[]
     */
    public function getUserAgents($limit = 0)
    {
        $counter   = 0;
        $allAgents = [];

        foreach ($this->loadFromPath() as $dataFile) {
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
     * @return \UaResult\Result\Result[]
     */
    public function getTests()
    {
        $allTests      = [];
        $resultFactory = new ResultFactory();

        foreach ($this->loadFromPath() as $dataFile) {
            foreach ($dataFile as $test) {
                if (!isset($test->ua)) {
                    continue;
                }

                if (array_key_exists($test->ua, $allTests)) {
                    continue;
                }

                yield $test->ua => $resultFactory->fromArray($this->cache, $this->logger, (array) $test->result);
                $allTests[$test->ua] = 1;
            }
        }
    }

    /**
     * @return array[]
     */
    private function loadFromPath()
    {
        $path = 'vendor/mimmi20/browser-detector-tests/tests/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->output->writeln('    reading path ' . $path);

        $iterator = new \RecursiveDirectoryIterator($path);

        foreach (new \RecursiveIteratorIterator($iterator) as $file) {
            /** @var $file \SplFileInfo */
            if (!$file->isFile()) {
                continue;
            }

            $filepath = $file->getPathname();

            $this->output->writeln('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));
            switch ($file->getExtension()) {
                case 'json':
                    yield json_decode(file_get_contents($filepath));
                    break;
                default:
                    // do nothing here
                    break;
            }
        }
    }
}
