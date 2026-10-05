<?php

namespace App\Libraries;

final class GoogleDriveImporter
{
    private const MAX_LIST_BYTES = 10485760;
    private const MAX_FILE_BYTES = 1073741824;
    private const MAX_FOLDER_DEPTH = 10;
    private const MAX_FILES = 500;

    public function extractFolderId(string $url): ?string
    {
        if (preg_match('~^https://drive\.google\.com/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]+)(?:[/?#].*)?$~', trim($url), $matches) !== 1) {
            return null;
        }

        return $matches[1];
    }

    /** @return list<array{id: string, name: string, kind: string}> */
    public function listFiles(string $folderId): array
    {
        $files = [];
        $visitedFolders = [];
        $this->crawlFolder($folderId, '', 0, $visitedFolders, $files);
        if ($files === []) {
            throw new \RuntimeException('Nenhum arquivo acessível foi encontrado nessa pasta do Google Drive ou em suas subpastas.');
        }

        return array_values($files);
    }

    /**
     * @param array<string, true> $visitedFolders
     * @param array<string, array{id: string, name: string, kind: string}> $files
     */
    private function crawlFolder(string $folderId, string $parentPath, int $depth, array &$visitedFolders, array &$files): void
    {
        if (isset($visitedFolders[$folderId])) {
            return;
        }
        if ($depth > self::MAX_FOLDER_DEPTH) {
            throw new \RuntimeException('A estrutura excede o limite de ' . self::MAX_FOLDER_DEPTH . ' níveis de subpastas.');
        }
        $visitedFolders[$folderId] = true;
        $html = $this->request('https://drive.google.com/embeddedfolderview?id=' . rawurlencode($folderId) . '#list', self::MAX_LIST_BYTES);
        if ($html === '' || stripos($html, 'Sign in') !== false && stripos($html, '/drive/folders/') === false && stripos($html, '/file/d/') === false) {
            throw new \RuntimeException('A pasta não está pública ou não pôde ser lida. Compartilhe-a como “qualquer pessoa com o link”.');
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        $subfolders = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $href = html_entity_decode($anchor->getAttribute('href'), ENT_QUOTES | ENT_HTML5);
            if (preg_match('~drive\.google\.com/drive/(?:u/\d+/)?folders/([A-Za-z0-9_-]+)~', $href, $folderMatches) === 1) {
                $subfolderId = $folderMatches[1];
                if ($subfolderId !== $folderId && ! isset($visitedFolders[$subfolderId])) {
                    $subfolders[$subfolderId] = $this->anchorName($anchor, 'Pasta ' . substr($subfolderId, 0, 8));
                }
                continue;
            }
            $kind = 'file';
            if (preg_match('~(?:drive\.google\.com/)?file/d/([A-Za-z0-9_-]+)~', $href, $matches) !== 1) {
                if (preg_match('~docs\.google\.com/(document|spreadsheets|presentation)/d/([A-Za-z0-9_-]+)~', $href, $matches) !== 1) {
                    continue;
                }
                $kind = $matches[1];
                $matches[1] = $matches[2];
            }
            $id = $matches[1];
            if (isset($files[$id])) {
                continue;
            }
            $name = $this->anchorName($anchor, 'arquivo-' . substr($id, 0, 8));
            $name .= match ($kind) {
                'document' => str_ends_with(strtolower($name), '.docx') ? '' : '.docx',
                'spreadsheets' => str_ends_with(strtolower($name), '.xlsx') ? '' : '.xlsx',
                'presentation' => str_ends_with(strtolower($name), '.pptx') ? '' : '.pptx',
                default => '',
            };
            $displayName = $parentPath === '' ? $name : $parentPath . ' / ' . $name;
            $files[$id] = ['id' => $id, 'name' => $displayName, 'kind' => $kind];
            if (count($files) > self::MAX_FILES) {
                throw new \RuntimeException('A importação excede o limite de ' . self::MAX_FILES . ' arquivos por pasta.');
            }
        }

        foreach ($subfolders as $subfolderId => $subfolderName) {
            $subfolderPath = $parentPath === '' ? $subfolderName : $parentPath . ' / ' . $subfolderName;
            $this->crawlFolder($subfolderId, $subfolderPath, $depth + 1, $visitedFolders, $files);
        }
    }

    private function anchorName(\DOMElement $anchor, string $fallback): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $anchor->textContent) ?? '');

        return $name === '' ? $fallback : $name;
    }

    public function download(array $file, string $destination, callable $progress): void
    {
        $url = match ($file['kind']) {
            'document' => 'https://docs.google.com/document/d/' . rawurlencode($file['id']) . '/export?format=docx',
            'spreadsheets' => 'https://docs.google.com/spreadsheets/d/' . rawurlencode($file['id']) . '/export?format=xlsx',
            'presentation' => 'https://docs.google.com/presentation/d/' . rawurlencode($file['id']) . '/export/pptx',
            default => 'https://drive.usercontent.google.com/download?id=' . rawurlencode($file['id']) . '&export=download&confirm=t',
        };
        $handle = fopen($destination, 'wb');
        if ($handle === false) {
            throw new \RuntimeException('Não foi possível criar o arquivo temporário.');
        }
        $downloaded = 0;
        $lastPercent = -1;
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_USERAGENT => 'MOSAICO/1.0 GoogleDriveImporter',
            CURLOPT_FAILONERROR => true,
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use ($handle, &$downloaded): int {
                $length = strlen($chunk);
                $downloaded += $length;
                if ($downloaded > self::MAX_FILE_BYTES) {
                    return 0;
                }
                return fwrite($handle, $chunk) === false ? 0 : $length;
            },
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => static function ($curlHandle, float $total, float $current) use ($progress, &$lastPercent): int {
                $percent = $total > 0 ? (int) min(100, floor(($current / $total) * 100)) : 0;
                if ($percent !== $lastPercent && ($percent === 0 || $percent === 100 || $percent - $lastPercent >= 5)) {
                    $lastPercent = $percent;
                    $progress($percent, (int) $current, (int) $total);
                }
                return $current > self::MAX_FILE_BYTES ? 1 : 0;
            },
        ] + $this->tlsOptions());
        $ok = curl_exec($curl);
        $error = curl_error($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        fclose($handle);

        if ($ok !== true || $status < 200 || $status >= 300 || $downloaded === 0) {
            @unlink($destination);
            throw new \RuntimeException($downloaded > self::MAX_FILE_BYTES ? 'O arquivo excede o limite de 1 GB.' : 'Falha ao baixar o arquivo do Google Drive' . ($error !== '' ? ': ' . $error : '.'));
        }
    }

    private function request(string $url, int $maxBytes): string
    {
        $body = '';
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'MOSAICO/1.0 GoogleDriveImporter',
            CURLOPT_FAILONERROR => true,
            CURLOPT_WRITEFUNCTION => static function ($curlHandle, string $chunk) use (&$body, $maxBytes): int {
                if (strlen($body) + strlen($chunk) > $maxBytes) {
                    return 0;
                }
                $body .= $chunk;
                return strlen($chunk);
            },
        ] + $this->tlsOptions());
        $ok = curl_exec($curl);
        $error = curl_error($curl);
        curl_close($curl);
        if ($ok !== true) {
            throw new \RuntimeException('Não foi possível consultar o Google Drive' . ($error !== '' ? ': ' . $error : '.'));
        }

        return $body;
    }

    /** @return array<int, mixed> */
    private function tlsOptions(): array
    {
        $options = [
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ];
        $configured = trim((string) env('MOSAICO_CA_BUNDLE', ''));
        $candidates = array_filter([
            $configured,
            (string) ini_get('curl.cainfo'),
            (string) ini_get('openssl.cafile'),
            dirname(PHP_BINARY, 4) . DIRECTORY_SEPARATOR . 'cert' . DIRECTORY_SEPARATOR . 'cacert.pem',
        ]);
        foreach ($candidates as $candidate) {
            $path = realpath($candidate);
            if ($path !== false && is_readable($path)) {
                $options[CURLOPT_CAINFO] = $path;
                break;
            }
        }

        return $options;
    }
}
