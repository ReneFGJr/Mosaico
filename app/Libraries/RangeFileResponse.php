<?php

namespace App\Libraries;

use CodeIgniter\HTTP\Response;
use Config\App;

final class RangeFileResponse extends Response
{
    private int $start = 0;
    private int $end;
    private int $size;

    public function __construct(
        private readonly string $path,
        string $mimeType,
        string $filename,
        string $rangeHeader = '',
    ) {
        parent::__construct(config(App::class));

        $this->size = (int) filesize($path);
        $this->end = max(0, $this->size - 1);
        $this->setHeader('Content-Type', $mimeType);
        $this->setHeader('Accept-Ranges', 'bytes');
        $this->setHeader('Content-Disposition', $this->contentDisposition($filename));
        $this->setHeader('Cache-Control', 'private, max-age=3600');
        $this->setHeader('X-Content-Type-Options', 'nosniff');

        if ($rangeHeader !== '') {
            $this->applyRange($rangeHeader);
        }

        if ($this->getStatusCode() !== 416) {
            $this->setHeader('Content-Length', (string) ($this->end - $this->start + 1));
        }
    }

    public function sendBody(): self
    {
        if ($this->getStatusCode() === 416) {
            return $this;
        }

        $handle = fopen($this->path, 'rb');
        if ($handle === false) {
            return $this;
        }

        fseek($handle, $this->start);
        $remaining = $this->end - $this->start + 1;
        while ($remaining > 0 && ! feof($handle) && connection_status() === CONNECTION_NORMAL) {
            $chunk = fread($handle, min(1_048_576, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
            unset($chunk);
            if (ENVIRONMENT !== 'testing' && ob_get_level() > 0) {
                ob_flush();
            }
            if (ENVIRONMENT !== 'testing') {
                flush();
            }
        }
        fclose($handle);

        return $this;
    }

    private function applyRange(string $rangeHeader): void
    {
        if (preg_match('/^bytes=(\d*)-(\d*)$/', trim($rangeHeader), $matches) !== 1 || ($matches[1] === '' && $matches[2] === '')) {
            return;
        }

        if ($matches[1] === '') {
            $length = min((int) $matches[2], $this->size);
            $this->start = $this->size - $length;
        } else {
            $this->start = (int) $matches[1];
        }
        if ($matches[2] !== '' && $matches[1] !== '') {
            $this->end = min((int) $matches[2], $this->end);
        }

        if ($this->start < 0 || $this->start >= $this->size || $this->start > $this->end) {
            $this->setStatusCode(416);
            $this->setHeader('Content-Range', 'bytes */' . $this->size);

            return;
        }

        $this->setStatusCode(206);
        $this->setHeader('Content-Range', sprintf('bytes %d-%d/%d', $this->start, $this->end, $this->size));
    }

    private function contentDisposition(string $filename): string
    {
        $safeFilename = str_replace(['"', "\r", "\n"], '', basename($filename));

        return sprintf('inline; filename="%s"; filename*=UTF-8\'\'%s', $safeFilename, rawurlencode($safeFilename));
    }
}
