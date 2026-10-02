<?php

namespace HtmlPdf\Image;

use RuntimeException;

/**
 * Turns an <img src="..."> value into an Image.
 *
 * Supported sources:
 *  - data: URIs (base64 or percent-encoded)          always allowed
 *  - local files (absolute, or relative to basePath)  always allowed
 *  - file:// URLs                                     same rules as local files
 *  - http(s):// URLs                                  OFF by default, see $allowRemote
 *
 * Security notes (matters when the HTML contains user-supplied content):
 *  - When $basePath is set, local files are only read from inside that
 *    directory (symlinks and ../ are resolved before checking).
 *  - Remote fetching can be abused for SSRF (e.g. http://169.254.169.254/ or
 *    internal services), which is why it is disabled unless you opt in.
 *  - Other stream wrappers (php://, phar://, ftp://, ...) are always refused.
 *
 * Failures never throw: load() returns null and the reason is appended to
 * $errors so the document still renders.
 */
class ImageLoader
{
    /** @var array<string,Image|null> */
    private array $cache = [];

    /** @var string[] */
    public array $errors = [];

    public function __construct(
        private ?string $basePath = null,
        private bool $allowRemote = false,
        private int $maxBytes = 10 * 1024 * 1024,
        private int $remoteTimeout = 10,
    ) {
    }

    public function load(string $src): ?Image
    {
        $src = trim($src);
        if ($src === '') {
            return null;
        }

        $key = md5($src);
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        try {
            $image = Image::fromBinary($this->read($src));
        } catch (\Throwable $e) {
            $label = str_starts_with(strtolower($src), 'data:') ? 'data URI' : $src;
            $this->errors[] = "{$label}: {$e->getMessage()}";
            $image = null;
        }

        return $this->cache[$key] = $image;
    }

    private function read(string $src): string
    {
        // data: URI
        if (preg_match('#^data:([^,]*?),(.*)$#is', $src, $m)) {
            $isBase64 = (bool) preg_match('/;\s*base64\s*$/i', $m[1]);
            $payload = $isBase64
                ? base64_decode((string) preg_replace('/\s+/', '', $m[2]), true)
                : rawurldecode($m[2]);
            if ($payload === false || $payload === '') {
                throw new RuntimeException('invalid data URI');
            }
            return $this->checkSize($payload);
        }

        // http(s)
        if (preg_match('#^https?://#i', $src)) {
            return $this->readRemote($src);
        }

        // Any other scheme (php://, phar://, ftp://, ...) is refused.
        // Single letters are left alone so Windows paths like C:\logo.png work.
        if (preg_match('#^([a-z][a-z0-9+.\-]+):#i', $src, $m) && strtolower($m[1]) !== 'file') {
            throw new RuntimeException("'{$m[1]}:' sources are not allowed");
        }

        $path = preg_match('#^file://#i', $src) ? rawurldecode(substr($src, 7)) : $src;

        return $this->readLocal($path);
    }

    private function readLocal(string $path): string
    {
        $base = $this->basePath !== null ? realpath($this->basePath) : null;
        if ($this->basePath !== null && $base === false) {
            throw new RuntimeException('base path does not exist');
        }

        $isAbsolute = str_starts_with($path, '/') || str_starts_with($path, '\\') || (bool) preg_match('#^[A-Za-z]:[\\\\/]#', $path);

        $candidates = [];
        if ($isAbsolute) {
            $candidates[] = $path;
            if ($base !== null) {
                // lets web-style "/img/logo.png" resolve against the base path
                $candidates[] = $base . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
            }
        } else {
            $candidates[] = ($base ?? getcwd()) . DIRECTORY_SEPARATOR . $path;
        }

        foreach ($candidates as $candidate) {
            $real = realpath($candidate);
            if ($real === false || !is_file($real)) {
                continue;
            }
            if ($base !== null && !str_starts_with($real, $base . DIRECTORY_SEPARATOR)) {
                continue; // outside the allowed directory
            }
            if (filesize($real) > $this->maxBytes) {
                throw new RuntimeException('file is too large');
            }
            $data = file_get_contents($real);
            if ($data === false) {
                throw new RuntimeException('could not read file');
            }
            return $data;
        }

        throw new RuntimeException($base !== null ? 'file not found inside the base path' : 'file not found');
    }

    private function readRemote(string $url): string
    {
        if (!$this->allowRemote) {
            throw new RuntimeException('remote images are disabled (call allowRemoteImages() to enable)');
        }

        $context = stream_context_create([
            'http' => [
                'timeout' => $this->remoteTimeout,
                'follow_location' => 1,
                'max_redirects' => 3,
                'user_agent' => 'HtmlPdf',
            ],
        ]);

        // Read one byte past the limit so oversized responses are detected.
        $data = @file_get_contents($url, false, $context, 0, $this->maxBytes + 1);
        if ($data === false || $data === '') {
            throw new RuntimeException('could not download image');
        }

        return $this->checkSize($data);
    }

    private function checkSize(string $data): string
    {
        if (strlen($data) > $this->maxBytes) {
            throw new RuntimeException('image is too large');
        }
        return $data;
    }
}
