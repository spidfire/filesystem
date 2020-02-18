<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Spidfire\Filesystem\FileSystem;
use Spidfire\Filesystem\FileSystemException;

final class BasicTest extends TestCase
{
    private static function getTestDirectory(): FileSystem
    {
        return new FileSystem(__DIR__ . '/../testingFiles/');
    }

    public function testCreateFile(): void
    {
        $fs = self::getTestDirectory();

        $newfile = $fs->createFile('test.txt', 0777, 'testdata');
        self::assertFileExists(__DIR__ . '/../testingFiles/test.txt');
        self::assertEquals('testdata', $newfile->getContents());
        $newfile->unlink();

        self::assertFileNotExists(__DIR__ . '/../testingFiles/test.txt');
    }

    public function testAppendNotExisting(): void
    {
        $fs = self::getTestDirectory();
        $this->expectException(FileSystemException::class);
        $fs->append('notExisting/');
    }

    public function testCreateAndAppend(): void
    {
        $fs = self::getTestDirectory();

        $previous = $fs->append('existing/', false);
        if ($previous->isDir()) {
            $previous->rmDir();
        }

        $fs->createDir('existing/');
        $newDir = $fs->append('existing/');
        $newDir->createFile('test.file', 0777, 'yes');

        self::assertFileExists(__DIR__ . '/../testingFiles/existing/test.file');

        $newDir->rmDir();
    }

    public function testNotOutsideSandbox(): void
    {
        $fs = self::getTestDirectory();

        $this->expectException(FileSystemException::class);
        $fs->append('..');
    }

    public function testNotOutsideSandboxSneaky(): void
    {
        $fs = self::getTestDirectory();

        $this->expectException(FileSystemException::class);
        $fs->append('test/../../');
    }

    public function testChildOf(): void
    {
        $fs = self::getTestDirectory();
        $child = $fs->createDir('childTest');

        self::assertTrue($fs->containsFs($child), 'childTest should be child');
        self::assertFalse($child->containsFs($fs), 'child does not contain parent');
        self::assertFalse($fs->containsFs($fs), 'cannot contain itself');

        self::assertFalse($child->isSamePath($fs), 'child is not the sae ast parent');
        self::assertFalse($fs->isSamePath($child), 'parent is not the same as child');
        self::assertTrue($fs->isSamePath($fs), 'cannot contain itself');

        $child->rmDir();
    }

    public function testConstructor(): void
    {
        $fs = new FileSystem(__DIR__ . '/../testingFiles/');
        self::assertTrue($fs->isDir());
    }

    public function testFailedConstructor(): void
    {
        $this->expectException(FileSystemException::class);
        new FileSystem(__DIR__ . '/../testingNotFiles/');
    }

    public function testIgnoredConstructor(): void
    {
        $fs = new FileSystem(__DIR__ . '/../testingNotFiles/', false);
        self::assertFalse($fs->isDir());
    }

    public function testCheckParts(): void
    {
        $test1 = new Spidfire\Filesystem\FileSystem('/var/www/logs', false);
        self::assertEquals(['var', 'www', 'logs'], $test1->getPathParts());

        $test1 = new Spidfire\Filesystem\FileSystem('c:\\Users\\Djurre', false);
        self::assertEquals(['c:', 'Users', 'Djurre'], $test1->getPathParts());
    }

    public function testCleanPath(): void
    {
        $fs = static::getTestDirectory();

        self::assertEquals($fs->getRealPath(), $fs->getPathClean());
    }

    public function testUniqueFile(): void
    {
        $fs = static::getTestDirectory();
        $uniqeDir = $fs->createDir('uniqueTests');

        for ($i = 0; $i < 10; ++$i) {
            $uniqeDir->createUniqueFile('test.txt');
            self::assertFileExists($uniqeDir->getPath());
        }
        $uniqeDir->rmDir();
    }

    public function testIsFileTest(): void
    {
        $fs = static::getTestDirectory();

        $dir = $fs->createDir('test');
        $file = $dir->createFile('test.txt');

        self::assertTrue($dir->isDir());
        self::assertTrue($file->isFile());
        self::assertTrue($file->fileExists());

        self::assertFalse($dir->fileExists());
        self::assertFalse($dir->isFile());
        self::assertFalse($file->isDir());

        $fileAbsent = $dir->append('mysterious.txt', false);

        self::assertFalse($fileAbsent->fileExists());
        self::assertFalse($fileAbsent->isFile());
        self::assertFalse($fileAbsent->isDir());

        $dir->rmDir();
    }

    public function testContent(): void
    {
        $fs = static::getTestDirectory();

        $result = $fs->createFile('testcontentFile.txt', 0777, 'this is a test');

        self::assertEquals('this is a test', $result->getContents());
        $result->unlink();
    }

    public function testExtension(): void
    {
        $fs = static::getTestDirectory();

        $result = $fs->createFile('testcontentFile.txt');
        self::assertEquals('txt', $result->getExtension());
        self::assertEquals('testcontentFile', $result->getFilenameWithoutExtension());
        self::assertEquals('testcontentFile.txt', $result->getFilename());
        $result->unlink();

        $result = $fs->createFile('test.wicked.test.txt');
        self::assertEquals('txt', $result->getExtension());
        self::assertEquals('test.wicked.test', $result->getFilenameWithoutExtension());
        self::assertEquals('test.wicked.test.txt', $result->getFilename());
        $result->unlink();

        $result = $fs->createDir('test.wicked.test.txt');
        self::assertEquals('test.wicked.test.txt', $result->getDirname());
        $result->rmDir();
    }
}
