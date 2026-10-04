<?php

namespace App\Libraries;

use App\Models\UuidModel;

final class PieceThumbnail
{
    private const WIDTH = 640;
    private const HEIGHT = 360;
    private const MAX_PIXELS = 40000000;

    public function findOrCreate(string $pieceId): ?string
    {
        $db = db_connect();
        if (! $db->tableExists('renditions')) {
            return null;
        }

        $existing = $db->table('renditions')->where(['piece_id' => $pieceId, 'kind' => 'thumbnail'])->get()->getRowArray();
        if ($existing) {
            $path = $this->safeStoragePath((string) $existing['storage_key'], 'thumbnails');
            if ($path !== null && is_file($path)) {
                return $path;
            }
        }

        if (! extension_loaded('gd')) {
            return null;
        }

        $source = $db->table('repository_files')
            ->select('repository_files.id, repository_files.storage_key, repository_files.mime_type')
            ->join('asset_versions', 'asset_versions.id = repository_files.version_id')
            ->join('assets', 'assets.id = asset_versions.asset_id')
            ->where('assets.piece_id', $pieceId)
            ->like('repository_files.mime_type', 'image/', 'after')
            ->orderBy('repository_files.created_at', 'DESC')
            ->get()
            ->getRowArray();
        if (! $source) {
            return null;
        }

        $sourcePath = $this->safeStoragePath((string) $source['storage_key'], 'repository');
        if ($sourcePath === null || ! is_file($sourcePath)) {
            return null;
        }
        $dimensions = @getimagesize($sourcePath);
        if (! $dimensions || $dimensions[0] < 1 || $dimensions[1] < 1 || ($dimensions[0] * $dimensions[1]) > self::MAX_PIXELS) {
            return null;
        }
        $bytes = @file_get_contents($sourcePath);
        $sourceImage = $bytes === false ? false : @imagecreatefromstring($bytes);
        if ($sourceImage === false) {
            return null;
        }

        $thumbnail = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        $background = imagecolorallocate($thumbnail, 242, 234, 219);
        imagefill($thumbnail, 0, 0, $background);
        $scale = min(self::WIDTH / $dimensions[0], self::HEIGHT / $dimensions[1]);
        $width = max(1, (int) round($dimensions[0] * $scale));
        $height = max(1, (int) round($dimensions[1] * $scale));
        $x = (int) floor((self::WIDTH - $width) / 2);
        $y = (int) floor((self::HEIGHT - $height) / 2);
        imagecopyresampled($thumbnail, $sourceImage, $x, $y, 0, 0, $width, $height, $dimensions[0], $dimensions[1]);

        $relativePath = 'thumbnails/' . $pieceId . '.jpg';
        $targetPath = WRITEPATH . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $targetDirectory = dirname($targetPath);
        if (! is_dir($targetDirectory) && ! mkdir($targetDirectory, 0750, true) && ! is_dir($targetDirectory)) {
            imagedestroy($sourceImage);
            imagedestroy($thumbnail);
            return null;
        }
        $created = imagejpeg($thumbnail, $targetPath, 84);
        imagedestroy($sourceImage);
        imagedestroy($thumbnail);
        if (! $created) {
            return null;
        }

        $data = [
            'id' => UuidModel::generateUuid(),
            'piece_id' => $pieceId,
            'source_file_id' => $source['id'],
            'kind' => 'thumbnail',
            'storage_key' => $relativePath,
            'mime_type' => 'image/jpeg',
            'size_bytes' => filesize($targetPath),
            'sha256' => hash_file('sha256', $targetPath),
            'created_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $db->table('renditions')->where('id', $existing['id'])->update($data);
        } else {
            $db->table('renditions')->insert($data);
        }

        return $targetPath;
    }

    private function safeStoragePath(string $storageKey, string $expectedRoot): ?string
    {
        $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $storageKey);
        $candidate = WRITEPATH . ltrim($normalized, DIRECTORY_SEPARATOR);
        $root = realpath(WRITEPATH . $expectedRoot);
        $parent = realpath(dirname($candidate));
        if ($root === false || $parent === false || ! str_starts_with($parent . DIRECTORY_SEPARATOR, $root . DIRECTORY_SEPARATOR)) {
            return null;
        }
        return $candidate;
    }
}
