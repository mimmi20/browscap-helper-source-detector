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
use Symfony\Component\Finder\Finder;
use UaResult\Result\ResultFactory;

/**
 * Class DirectorySource
 *
 * @author  Thomas Mueller <mimmi20@live.de>
 */
class DetectorSource implements SourceInterface
{
    /**
     * @var null
     */
    private $logger = null;

    /**
     * @var \Psr\Cache\CacheItemPoolInterface|null
     */
    private $cache = null;

    /**
     * @param \Psr\Log\LoggerInterface          $logger
     * @param \Psr\Cache\CacheItemPoolInterface $cache
     */
    public function __construct(LoggerInterface $logger, CacheItemPoolInterface $cache)
    {
        $this->logger = $logger;
        $this->cache  = $cache;
    }

    /**
     * @param int $limit
     *
     * @return string[]
     */
    public function getUserAgents($limit = 0)
    {
        $counter = 0;

        foreach ($this->loadFromPath() as $test) {
            if ($limit && $counter >= $limit) {
                return;
            }

            yield trim($test->ua);
            ++$counter;
        }
    }

    /**
     * @return \UaResult\Result\Result[]
     */
    public function getTests()
    {
        $resultFactory = new ResultFactory();

        foreach ($this->loadFromPath() as $test) {
            yield trim($test->ua) => $resultFactory->fromArray($this->cache, $this->logger, (array) $test->result);
        }
    }

    /**
     * @return \StdClass[]
     */
    private function loadFromPath()
    {
        $path = 'vendor/mimmi20/browser-detector-tests/tests/issues';

        if (!file_exists($path)) {
            return;
        }

        $this->logger->info('    reading path ' . $path);

        $allTests = [];
        $finder   = new Finder();
        $finder->files();
        $finder->name('*.json');
        $finder->ignoreDotFiles(true);
        $finder->ignoreVCS(true);
        $finder->sortByName();
        $finder->ignoreUnreadableDirs();
        $finder->in($path);

        foreach ($finder as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            if (!$file->isFile()) {
                continue;
            }

            if ('json' !== $file->getExtension()) {
                continue;
            }

            $filepath = $file->getPathname();

            $this->logger->info('    reading file ' . str_pad($filepath, 100, ' ', STR_PAD_RIGHT));
            $dataFile = json_decode(file_get_contents($filepath));

            foreach ($dataFile as $test) {
                if (!isset($test->ua)) {
                    continue;
                }

                $agent = trim($test->ua);

                if (array_key_exists($agent, $allTests)) {
                    continue;
                }

                yield $test;
                $allTests[$agent] = 1;
            }
        }
    }
}
