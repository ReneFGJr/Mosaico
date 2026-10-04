<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

final class UploadSizeFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if ($request->getMethod() !== 'POST' || ! str_starts_with(strtolower($request->getHeaderLine('Content-Type')), 'multipart/form-data')) {
            return null;
        }

        $contentLength = (int) ($request->getHeaderLine('Content-Length') ?: $request->getServer('CONTENT_LENGTH'));
        $maximumBytes = $this->toBytes((string) ini_get('post_max_size'));
        if ($maximumBytes > 0 && $contentLength > $maximumBytes) {
            return redirect()->back()->with('error', sprintf(
                'O lote selecionado excede o limite total de %s permitido pelo servidor. Reduza a quantidade ou o tamanho dos arquivos.',
                $this->formatBytes($maximumBytes),
            ));
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null): void
    {
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $number = (float) $value;

        return (int) round($number * match (strtolower(substr($value, -1))) {
            'g' => 1024 ** 3,
            'm' => 1024 ** 2,
            'k' => 1024,
            default => 1,
        });
    }

    private function formatBytes(int $bytes): string
    {
        return $bytes >= 1024 ** 3
            ? rtrim(rtrim(number_format($bytes / (1024 ** 3), 1, ',', ''), '0'), ',') . ' GB'
            : rtrim(rtrim(number_format($bytes / (1024 ** 2), 1, ',', ''), '0'), ',') . ' MB';
    }
}
