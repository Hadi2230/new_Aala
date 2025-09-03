<?php
declare(strict_types=1);

namespace App\Services;

final class UploadService
{
    /**
     * @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file
     * @param string $targetDir
     * @param array<int,string> $allowedExtensions
     * @param int $maxSizeBytes
     */
    public static function upload(array $file, string $targetDir, array $allowedExtensions = ['jpg','jpeg','png','gif','pdf'], int $maxSizeBytes = 5_000_000): string
    {
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('خطا در آپلود فایل');
        }
        $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            throw new \RuntimeException('پسوند فایل مجاز نیست');
        }
        if (($file['size'] ?? 0) > $maxSizeBytes) {
            throw new \RuntimeException('حجم فایل بیش از حد مجاز است');
        }
        // MIME check
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        $allowedMimes = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'pdf' => 'application/pdf'
        ];
        if (isset($allowedMimes[$ext]) && $mime !== $allowedMimes[$ext]) {
            throw new \RuntimeException('نوع فایل معتبر نیست');
        }
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0755, true);
        }
        $basename = bin2hex(random_bytes(8)) . '_' . time();
        $filename = $basename . '.' . $ext;
        $dest = rtrim($targetDir, '/\\') . '/' . $filename;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('ذخیره فایل ناموفق بود');
        }
        return $dest;
    }
}
