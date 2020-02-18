<?php

namespace Spidfire\Filesystem;

class FileSystem
{
    /** @var string */
    private $path;

    /**
     * FileSystem constructor.
     *
     * @param string $path
     * @param bool   $shouldExist
     */
    public function __construct(string $path, bool $shouldExist = true)
    {
        $path = $this->fixSlashes($path);
        if ($shouldExist && !file_exists($path)) {
            throw new FileSystemException('The given path does not exist: ' . $path);
        }
        if (is_dir($path)) {
            $this->path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        } else {
            $this->path = $path;
        }
    }

    public function append(string $path, bool $shouldExist = true): self
    {
        $path = $this->fixSlashes($path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);

        $dir = rtrim($this->getPath(), DIRECTORY_SEPARATOR);
        $result = new self($dir . DIRECTORY_SEPARATOR . $path, $shouldExist);

        if (!$this->containsFs($result)) {
            throw new FileSystemException(
                "Something strange is happening, the found directory is not a part of the parent, {$this->getPathClean()}, {$result->getPathClean()}"
            );
        }

        return $result;
    }

    public function containsFs(self $child): bool
    {
        $parentParts = $this->getPathParts();
        $childParts = $child->getPathParts();
        if (count($parentParts) >= count($childParts)) {
            return false;
        }

        foreach ($childParts as $index => $p) {
            if (!isset($parentParts[$index])) {
                return true;
            }
            if ($p !== $childParts[$index]) {
                return false;
            }
        }

        return false;
    }

    public function isSamePath(self $child): bool
    {
        $parentParts = $this->getPathParts();
        $childParts = $child->getPathParts();
        if (count($parentParts) !== count($childParts)) {
            return false;
        }

        foreach ($childParts as $index => $p) {
            if ($p !== $parentParts[$index]) {
                return false;
            }
        }

        return true;
    }

    public function getPathParts(): array
    {
        $parts = explode(DIRECTORY_SEPARATOR, $this->path);
        $location = [];
        foreach ($parts as $p) {
            if ($p === '..') {
                if (count($location) > 0) {
                    array_pop($location);
                } else {
                    throw new FileSystemException('Could not pop, already on the lowest level');
                }
            } elseif (preg_match('/^.{3-}$/', $p)) {
                throw new FileSystemException(' Windows 95/98/ME  standard, not used anymore');
            } elseif (!empty($p) && $p !== '.') {
                $location[] = $p;
            }
        }

        return $location;
    }

    public function getPathClean(): string
    {
        $parentParts = $this->getPathParts();

        return implode(DIRECTORY_SEPARATOR, $parentParts);
    }

    public function createDir(string $path, int $rights = 0777): self
    {
        $path = $this->fixSlashes($path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);
        $fullPath = $this->getPath() . $path;
        if (!is_dir($fullPath)
            && !mkdir($fullPath, $rights, true)
            && !is_dir($fullPath)) {
            throw new FileSystemException(sprintf('Directory "%s" was not created', $fullPath));
        }

        return new self($fullPath);
    }

    public function createFile(string $path, int $rights = 0777, ?string $contents = null): self
    {
        $path = $this->fixSlashes($path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);
        $fullPath = $this->getPath() . $path;
        touch($fullPath);
        chmod($fullPath, $rights);
        if ($contents !== null) {
            file_put_contents($fullPath, $contents);
        }

        return new self($fullPath);
    }

    public function createUniqueFile(string $path, int $rights = 0777, ?string $contents = null): self
    {
        $path = $this->fixSlashes($path);
        $path = ltrim($path, DIRECTORY_SEPARATOR);
        $md5 = mb_substr(md5(random_bytes(30)), 0, 10);
        $fullPath = $this->getPath() . date('Ymd-His') . $md5 . '.' . $path;
        touch($fullPath);
        chmod($fullPath, $rights);
        if ($contents !== null) {
            file_put_contents($fullPath, $contents);
        }

        return new self($fullPath);
    }

    public function createFileIfNotExist(string $path, int $rights = 0777, ?string $contents = null): self
    {
        $path = $this->fixSlashes($path);
        $file = $this->append($path, false);
        if (!$file->fileExists()) {
            return $this->createFile($path, $rights, $contents);
        }

        return $file;
    }

    public function isDir(): bool
    {
        return is_dir($this->getPath());
    }

    public function isFile(): bool
    {
        return is_file($this->getPath());
    }

    public function isImage($allowedExtensions = ['png', 'jpg', 'jpeg', 'gif']): bool
    {
        if (!$this->isFile()) {
            return false;
        }
        $extension = mb_strtolower($this->getExtension());

        return in_array($extension, $allowedExtensions, true);
    }

    public function isReadableFile($allowedExtensions = null): bool
    {
        if ($allowedExtensions === null) {
            $allowedExtensions = [
                'docx', 'doc', 'odt',
                'txt', 'rtf', 'pdf',
                'xls', 'xlsx', 'ods',
                'ppt', 'pptx', 'odp',
                'wav', 'mp3',
                'rar', 'zip',
                'ttf',
                'ai', 'psd', 'svg',
                'html', 'svg',
                'avi', 'mov', 'mp4'
            ];
        }
        if (!$this->isFile()) {
            return false;
        }
        $extension = mb_strtolower($this->getExtension());

        return in_array($extension, $allowedExtensions, true);
    }

    public function fileExists(): bool
    {
        return $this->isFile() && file_exists($this->getPath());
    }

    public function getRealPath(): string
    {
        return realpath($this->getPath());
    }

    public function getContents(): string
    {
        if (!$this->isFile()) {
            throw new FileSystemException('Could not get contents because file is not found: ' . $this->getRealPath());
        }

        return file_get_contents($this->getPath());
    }

    public function getExtension(): string
    {
        return mb_strtolower(pathinfo($this->getFilename(), PATHINFO_EXTENSION));
    }

    public function getFilenameWithoutExtension(): string
    {
        return pathinfo($this->getFilename(), PATHINFO_FILENAME);
    }

    public function getFilename(): string
    {
        if ($this->isDir()) {
            throw new FileSystemException('Could not get filename from non file: ' . $this->getRealPath());
        }

        return basename($this->getPath());
    }

    public function getDirname(): string
    {
        if ($this->isFile()) {
            throw new FileSystemException('Could not get dirname from non dir: ' . $this->getRealPath());
        }

        return basename($this->getPath());
    }

    public function getSafeFilename(): string
    {
        $extension = $this->getExtension();
        if (empty($extension)) {
            $extension = 'unknown';
        }
        $base = $this->getFilenameWithoutExtension();
        $base = preg_replace('/\W+/', '_', $base);

        return $base . '.' . $extension;
    }

    public function getPath(): string
    {
        $path = $this->path;

        return $this->fixSlashes($path);
    }

    public function getRelativePath(self $relativeToMe): string
    {
        $replace = str_replace($relativeToMe->getRealPath() . DIRECTORY_SEPARATOR, '', $this->getRealPath());
        if ($replace === $this->getRealPath()) {
            throw new FileSystemException('Could not get relative path of ' .
                $this->getPath() . ' compared to ' . $relativeToMe->getPath());
        }

        return $replace;
    }

    public function getMimeType(): string
    {
        $location = $this->getPath();

        return mime_content_type($location);
    }

    public function fileSize()
    {
        if ($this->isDir()) {
            throw new FileSystemException('Could not get filename from non file: ' . $this->getRealPath());
        }

        return filesize($this->getPath());
    }

    /**
     * @param bool $includeHidden
     *
     * @return self[]
     */
    public function getChildren(bool $includeHidden = false): array
    {
        $files = scandir($this->getPath());
        $out = [];
        foreach ($files as $fileName) {
            if ($fileName !== '.' && $fileName !== '..' && ($includeHidden || $this->isVisibleFileType($fileName))) {
                $out[] = $this->append($fileName);
            }
        }

        return $out;
    }

    public function getParent()
    {
        return new self(dirname($this->getRealPath()));
    }

    public function getJson(): array
    {
        $content = $this->getContents();
        $json = json_decode($content, true);
        if ($json === null) {
            throw new FileSystemException('Could not parse JSON from: ' . $this->getPath());
        }

        return $json;
    }

    /**
     * @param string $path
     *
     * @return string|string[]
     */
    protected function fixSlashes(string $path)
    {
        if (DIRECTORY_SEPARATOR === '/') {
            return str_replace('\\', DIRECTORY_SEPARATOR, $path);
        }

        return str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    public function getDirectoryTree(?self $root = null): array
    {
        $output = [];
        $root = $root ?? $this;
        if ($root->isSamePath($this)) {
            $output[$this->getPath()] = $this->getDirname();
        } else {
            $output[$this->getPath()] = $this->getRelativePath($root);
        }
        foreach ($this->getChildren() as $child) {
            if ($child->isDir()) {
                foreach ($child->getDirectoryTree($root) as $key => $item) {
                    $output[$key] = $item;
                }
            }
        }

        return $output;
    }

    public function unlink(): void
    {
        if ($this->isFile()) {
            unlink($this->getPath());
        } else {
            throw new FileSystemException('You can only unlink files');
        }
    }

    public function __toString()
    {
        return $this->path;
    }

    public function rmDir(): void
    {
        if ($this->isDir()) {
            foreach ($this->getChildren(true) as $child) {
                if ($child->isDir()) {
                    $child->rmDir();
                } else {
                    $child->unlink();
                }
            }
            rmdir($this->getPath());
        } else {
            throw new \RuntimeException('This is not an directory ' . $this->getPath());
        }
    }

    /**
     * @param $f
     *
     * @return bool
     */
    protected function isVisibleFileType(string $f): bool
    {
        return true;
    }
}
