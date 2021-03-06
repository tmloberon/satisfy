<?php

namespace Playbloom\Satisfy\Service;

use Playbloom\Satisfy\Model\Repository;

/**
 * Processor for uploaded composer.lock files.
 *
 * @author Julius Beckmann <php@h4cc.de>
 */
class LockProcessor
{
    /** @var Manager */
    private $manager;

    /**
     * Constructor
     *
     * @param Manager $manager
     */
    public function __construct(Manager $manager)
    {
        $this->manager = $manager;
    }

    /**
     * Adds repositories from composer.lock JSON file.
     *
     * @param \SplFileObject $file
     */
    public function processFile(\SplFileObject $file)
    {
        $content = $this->getComposerLockData($file);
        $this->addReposFromContent($content);
    }

    /**
     * Reads and decodes json from given file.
     *
     * @param \SplFileObject $file
     * @return mixed
     */
    private function getComposerLockData(\SplFileObject $file)
    {
        $json = file_get_contents($file->getRealPath());

        return json_decode($json);
    }

    /**
     * Adds all repos from composer.lock data, even require-dev ones.
     *
     * @param \stdClass $content
     * @return void
     */
    private function addReposFromContent(\stdClass $content)
    {
        $repositories = array();
        if (!empty($content->packages)) {
            $repositories = $this->getRepositories($content->packages);
        }

        if (!empty($content->{'packages-dev'})) {
            $repositories = array_merge($repositories, $this->getRepositories($content->{'packages-dev'}));
        }

        $this->manager->addAll($repositories);
    }

    /**
     * @param array $packages
     * @return Repository[]
     */
    protected function getRepositories(array $packages)
    {
        $repos = array();

        foreach ($packages as $package) {
            if (empty($package->source)) {
                continue;
            }
            $source = $package->source;
            if (!empty($source->url) && !empty($source->type)) {
                $repo = new Repository();
                $repo
                    ->setUrl($source->url)
                    ->setType($source->type);
                $repos[] = $repo;
            }
        }

        return $repos;
    }
}
