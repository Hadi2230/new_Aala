<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\UploadService;
use function pdo;

final class AssetsController extends Controller
{
    public function index(): void
    {
        $stmt = pdo()->query("SELECT a.*, at.display_name AS type_display_name FROM assets a JOIN asset_types at ON a.type_id = at.id ORDER BY a.created_at DESC LIMIT 200");
        $assets = $stmt->fetchAll();
        $this->view('assets/index', ['assets' => $assets]);
    }

    public function create(): void
    {
        $types = pdo()->query('SELECT id, display_name FROM asset_types ORDER BY display_name')->fetchAll();
        $this->view('assets/create', ['types' => $types]);
    }

    public function store(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: /assets');
            return;
        }
        if (function_exists('verifyCsrfToken')) {
            verifyCsrfToken();
        }
        $name = clean($_POST['name'] ?? '');
        $typeId = (int)($_POST['type_id'] ?? 0);
        $serial = clean($_POST['serial_number'] ?? '');
        $status = clean($_POST['status'] ?? 'فعال');
        $brand = clean($_POST['brand'] ?? '');
        $model = clean($_POST['model'] ?? '');
        if ($name === '' || $typeId <= 0) {
            $this->create();
            return;
        }
        $stmt = pdo()->prepare('INSERT INTO assets (name, type_id, serial_number, status, brand, model) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([$name, $typeId, $serial, $status, $brand, $model]);
        $assetId = (int)pdo()->lastInsertId();

        if (!empty($_FILES['device_image']['name'] ?? '')) {
            try {
                $path = UploadService::upload($_FILES['device_image'], 'uploads/assets');
                $ins = pdo()->prepare('INSERT INTO asset_images (asset_id, field_name, image_path) VALUES (?, ?, ?)');
                $ins->execute([$assetId, 'device_image', $path]);
            } catch (\Throwable $e) {
                // ignore but log if possible
                if (function_exists('log_action')) {
                    log_action('UPLOAD_ERROR', 'Asset image upload failed: ' . $e->getMessage());
                }
            }
        }

        if (function_exists('log_action')) {
            log_action('ADD_ASSET', 'افزودن دارایی جدید: ' . $name);
        }
        header('Location: /assets');
    }
}

