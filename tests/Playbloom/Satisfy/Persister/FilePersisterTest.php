<?php

namespace Tests\Playbloom\Satisfy\Persister;

use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Playbloom\Satisfy\Persister\FilePersister;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Lock\Lock;
use Tests\Playbloom\Satisfy\Traits\SchemaValidatorTrait;

class FilePersisterTest extends TestCase
{
    use SchemaValidatorTrait;

    /** @var vfsStreamDirectory */
    protected $root;

    /** @var FilePersister */
    protected $persister;

    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        $this->root = vfsStream::setup();
        $this->persister = new FilePersister(new Filesystem, $this->root->url().'/satis.json', $this->root->url());
    }

    /**
     * {@inheritdoc}
     */
    protected function tearDown()
    {
        $this->root = null;
        $this->persister = null;
    }

    public function testDumpMustTruncateFile()
    {
        $config = [
            'name'         => 'test',
            'homepage'     => 'http://localhost',
            'repositories' => [
                [
                    'type' => 'git',
                    'url'  => 'https://github.com/ludofleury/satisfy.git',
                ],
            ],
            'require-all'  => true,
        ];
        $content = json_encode($config);
        $this->persister->flush($content);
        $configFile = $this->root->getChild('satis.json');
        $this->assertStringEqualsFile($configFile->url(), $content);
        $this->assertEquals($content, $this->persister->load());

        $this->validateSchema(json_decode($configFile->getContent()), $this->getSatisSchema());

        $config['repositories'] = array();
        $content = json_encode($config);
        $this->persister->flush($content);
        $this->assertStringEqualsFile($configFile->url(), $content);
        $this->assertEquals($content, $this->persister->load());
    }

    public function testFileLocking()
    {
        // try to acquireLock twice
        $reflection = new \ReflectionClass($this->persister);
        $method = $reflection->getMethod('acquireLock');
        $method->setAccessible(true);
        /** @var Lock $lock */
        $lock = $method->invoke($this->persister);
        $this->assertInstanceOf(Lock::class, $lock);
        $this->assertTrue($lock->isAcquired());

        try {
            $this->persister->flush('');
            $this->fail('Persister must fail if resource is already locked');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(\RuntimeException::class, $e);
        }

        // lock must still present
        $this->assertTrue($lock->isAcquired());

        // try again without lock
        unset($lock);
        $this->persister->flush('');
    }
}
