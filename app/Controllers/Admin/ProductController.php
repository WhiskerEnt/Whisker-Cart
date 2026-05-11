<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session, Validator};

class ProductController
{
    public function index(Request $request, array $params = []): void
    {
        $products = Database::fetchAll(
            "SELECT p.*, c.name AS category_name,
                    (SELECT image_path FROM wk_product_images WHERE product_id=p.id AND is_primary=1 LIMIT 1) AS image,
                    COALESCE(
                        (SELECT SUM(oi.quantity) FROM wk_order_items oi
                         JOIN wk_orders o ON o.id=oi.order_id
                         WHERE oi.product_id=p.id AND o.status NOT IN ('cancelled','refunded')), 0
                    ) AS total_sold
             FROM wk_products p
             LEFT JOIN wk_categories c ON c.id=p.category_id
             ORDER BY p.created_at DESC"
        );

        View::render('admin/products/index', [
            'pageTitle' => 'Products',
            'products'  => $products,
        ], 'admin/layouts/main');
    }

    public function create(Request $request, array $params = []): void
    {
        $categories = Database::fetchAll(
            "SELECT c.id, c.name, c.parent_id, p.name AS parent_name
             FROM wk_categories c LEFT JOIN wk_categories p ON p.id=c.parent_id
             WHERE c.is_active=1 ORDER BY c.parent_id IS NULL DESC, c.name"
        );

        View::render('admin/products/create', [
            'pageTitle'  => 'Add Product',
            'categories' => $categories,
        ], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Session::setOldInput($request->all());
            Response::redirect(View::url('admin/products/create'));
            return;
        }

        $v = new Validator($request->all(), [
            'name'  => 'required|min:2|max:255',
            'price' => 'required|numeric|min:0',
            'sku'   => 'required|max:64',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Session::setOldInput($request->all());
            Response::redirect(View::url('admin/products/create'));
            return;
        }

        $slug = $this->uniqueSlug($request->clean('name'));

        $insertData = [
            'category_id'      => $request->input('category_id') ?: null,
            'sku'              => $request->clean('sku'),
            'name'             => $request->clean('name'),
            'slug'             => $slug,
            'description'      => $request->input('description') ?? '',
            'short_description'=> $request->clean('short_description') ?? '',
            'price'            => (float)$request->input('price'),
            'sale_price'       => $request->input('sale_price') ? (float)$request->input('sale_price') : null,
            'stock_quantity'   => (int)($request->input('stock_quantity') ?? 0),
            'is_active'        => $request->input('is_active') ? 1 : 0,
            'is_featured'      => $request->input('is_featured') ? 1 : 0,
            'meta_title'       => trim($request->input('meta_title') ?? '') ?: null,
            'meta_description' => trim($request->input('meta_description') ?? '') ?: null,
            'meta_keywords'    => trim($request->input('meta_keywords') ?? '') ?: null,
            'og_image'         => trim($request->input('og_image') ?? '') ?: null,
        ];

        // Add weight if column exists
        try {
            $weightKg = self::convertToKg((float)($request->input('weight_value') ?? 0), $request->input('weight_unit') ?? 'kg');
            $insertData['weight'] = $weightKg;
        } catch (\Exception $e) {}

        $productId = Database::insert('wk_products', $insertData);

        // Save product meta (weight unit, shipping overrides)
        self::saveProductMeta($productId, $request);

        // Handle images uploaded during creation (stored as temp files in session)
        $tempImages = Session::get('wk_temp_images', []);
        if (!empty($tempImages)) {
            $uploadDir = WK_ROOT . '/storage/uploads/products/';
            foreach ($tempImages as $i => $tempFile) {
                if (file_exists($tempFile['tmp_path'])) {
                    $filename = 'prod_' . $productId . '_' . bin2hex(random_bytes(6)) . '.' . $tempFile['ext'];
                    if (rename($tempFile['tmp_path'], $uploadDir . $filename)) {
                        Database::insert('wk_product_images', [
                            'product_id' => $productId,
                            'image_path' => $filename,
                            'alt_text'   => '',
                            'sort_order' => $i,
                            'is_primary' => $i === 0 ? 1 : 0,
                        ]);
                    }
                }
            }
            Session::remove('wk_temp_images');
        }

        Session::flash('success', 'Product created! Now add variants and more images below.');
        Response::redirect(View::url('admin/products/edit/' . $productId));
    }

    public function edit(Request $request, array $params = []): void
    {
        $product = Database::fetch("SELECT * FROM wk_products WHERE id=?", [$params['id']]);
        if (!$product) { Response::notFound(); return; }

        $categories = Database::fetchAll(
            "SELECT c.id, c.name, c.parent_id, p.name AS parent_name
             FROM wk_categories c LEFT JOIN wk_categories p ON p.id=c.parent_id
             WHERE c.is_active=1 ORDER BY c.parent_id IS NULL DESC, c.name"
        );
        $images = Database::fetchAll("SELECT * FROM wk_product_images WHERE product_id=? ORDER BY sort_order", [$params['id']]);

        View::render('admin/products/edit', [
            'pageTitle'  => 'Edit: ' . $product['name'],
            'product'    => $product,
            'categories' => $categories,
            'images'     => $images,
        ], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/products/edit/' . $params['id']));
            return;
        }

        $updateData = [
            'category_id'      => $request->input('category_id') ?: null,
            'sku'              => $request->clean('sku'),
            'name'             => $request->clean('name'),
            'description'      => $request->input('description') ?? '',
            'short_description'=> $request->clean('short_description') ?? '',
            'price'            => (float)$request->input('price'),
            'sale_price'       => $request->input('sale_price') ? (float)$request->input('sale_price') : null,
        ];

        // Only include stock_quantity if no variants (otherwise it's auto-calculated)
        $hasVariants = false;
        try {
            $hasVariants = (int)Database::fetchValue("SELECT COUNT(*) FROM wk_variant_combos WHERE product_id=?", [$params['id']]) > 0;
        } catch (\Exception $e) {}

        if (!$hasVariants) {
            $updateData['stock_quantity'] = (int)($request->input('stock_quantity') ?? 0);
        }

        $updateData['is_active'] = $request->input('is_active') ? 1 : 0;
        $updateData['is_featured'] = $request->input('is_featured') ? 1 : 0;
        $updateData['meta_title'] = trim($request->input('meta_title') ?? '') ?: null;
        $updateData['meta_description'] = trim($request->input('meta_description') ?? '') ?: null;
        $updateData['meta_keywords'] = trim($request->input('meta_keywords') ?? '') ?: null;
        $updateData['og_image'] = trim($request->input('og_image') ?? '') ?: null;

        try {
            $updateData['weight'] = self::convertToKg((float)($request->input('weight_value') ?? 0), $request->input('weight_unit') ?? 'kg');
        } catch (\Exception $e) {}

        Database::update('wk_products', $updateData, 'id = ?', [$params['id']]);

        self::saveProductMeta((int)$params['id'], $request);

        // Re-sync stock from variants if they exist
        if ($hasVariants) {
            self::syncVariantStock((int)$params['id']);
        }

        Session::flash('success', 'Product updated!');
        Response::redirect(View::url('admin/products'));
    }

    public function delete(Request $request, array $params = []): void
    {
        // Delete images from disk
        $images = Database::fetchAll("SELECT image_path FROM wk_product_images WHERE product_id=?", [$params['id']]);
        foreach ($images as $img) {
            $path = WK_ROOT . '/storage/uploads/products/' . $img['image_path'];
            if (file_exists($path)) @unlink($path);
        }

        Database::delete('wk_products', 'id = ?', [$params['id']]);
        Session::flash('success', 'Product deleted.');
        Response::redirect(View::url('admin/products'));
    }

    /**
     * AJAX image upload — works for both new and existing products
     */
    public function uploadImage(Request $request, array $params = []): void
    {
        $productId = (int)$request->input('product_id');

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit',
                UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
                UPLOAD_ERR_PARTIAL    => 'File only partially uploaded',
                UPLOAD_ERR_NO_FILE    => 'No file selected',
                UPLOAD_ERR_NO_TMP_DIR => 'Server missing temp folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            ];
            $errCode = $file['error'] ?? UPLOAD_ERR_NO_FILE;
            $msg = $errorMessages[$errCode] ?? 'Upload error (code: ' . $errCode . ')';
            Response::json(['success' => false, 'message' => $msg], 400);
            return;
        }

        // Validate file type — use multiple methods for compatibility
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $mime = null;

        // Try mime_content_type first (not available on all hosts)
        if (function_exists('mime_content_type')) {
            $mime = mime_content_type($file['tmp_name']);
        }
        // Try finfo as fallback
        elseif (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $file['tmp_name']);
            finfo_close($fi);
        }
        // Last resort: trust the browser-reported type + verify extension
        else {
            $mime = $file['type'] ?? '';
        }

        // Also validate by extension as extra safety
        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!$mime || !in_array($mime, $allowed) || !in_array($fileExt, $allowedExts)) {
            Response::json(['success' => false, 'message' => 'Only JPG, PNG, WebP, GIF allowed.'], 400);
            return;
        }

        // Additional validation: must be a real image with valid dimensions
        $imageInfo = @getimagesize($file['tmp_name']);
        if (!$imageInfo || $imageInfo[0] < 1 || $imageInfo[1] < 1) {
            Response::json(['success' => false, 'message' => 'File is not a valid image.'], 400);
            return;
        }

        if ($file['size'] > 5 * 1024 * 1024) {
            Response::json(['success' => false, 'message' => 'Max file size is 5MB'], 400);
            return;
        }

        // Determine extension from file name (most reliable when mime functions missing)
        $extMap = ['jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
        $ext = $extMap[$fileExt] ?? 'jpg';

        $uploadDir = WK_ROOT . '/storage/uploads/products/';
        if (!is_dir($uploadDir)) {
            if (!@mkdir($uploadDir, 0775, true)) {
                Response::json(['success' => false, 'message' => 'Cannot create upload directory. Check folder permissions.'], 500);
                return;
            }
        }

        // If product_id=0, this is a new product — store in uploads with tmp_ prefix
        if ($productId === 0) {
            $tempName = 'tmp_' . bin2hex(random_bytes(8)) . '.' . $ext;
            if (!move_uploaded_file($file['tmp_name'], $uploadDir . $tempName)) {
                Response::json(['success' => false, 'message' => 'Failed to save temp file'], 500);
                return;
            }
            // Re-encode to strip any embedded payloads (polyglot attack prevention)
            self::reencodeImage($uploadDir . $tempName, $ext);
            $tempImages = Session::get('wk_temp_images', []);
            $tempImages[] = ['tmp_path' => $uploadDir . $tempName, 'ext' => $ext];
            Session::set('wk_temp_images', $tempImages);

            Response::json([
                'success'    => true,
                'image_id'   => count($tempImages) - 1,
                'filename'   => $tempName,
                'url'        => View::url('storage/uploads/products/' . $tempName),
                'is_primary' => count($tempImages) === 1 ? 1 : 0,
                'temp'       => true,
            ]);
            return;
        }

        // Existing product — save directly
        $filename = 'prod_' . $productId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            Response::json(['success' => false, 'message' => 'Failed to move uploaded file. Check folder permissions on storage/uploads/products/'], 500);
            return;
        }
        // Re-encode to strip any embedded payloads (polyglot attack prevention)
        self::reencodeImage($uploadDir . $filename, $ext);

        $existingCount = Database::fetchValue("SELECT COUNT(*) FROM wk_product_images WHERE product_id=?", [$productId]);

        // Check if this image is for a specific variant
        $variantComboId = (int)($request->input('variant_combo_id') ?? 0);

        $imageId = Database::insert('wk_product_images', [
            'product_id' => $productId,
            'image_path' => $filename,
            'alt_text'   => $variantComboId ? 'variant_' . $variantComboId : '',
            'sort_order' => $existingCount,
            'is_primary' => ($existingCount == 0 && !$variantComboId) ? 1 : 0,
        ]);

        // If variant image, link it to the combo
        if ($variantComboId) {
            Database::update('wk_variant_combos', ['image_id' => $imageId], 'id=?', [$variantComboId]);
        }

        Response::json([
            'success'    => true,
            'image_id'   => $imageId,
            'filename'   => $filename,
            'url'        => View::url('storage/uploads/products/' . $filename),
            'is_primary' => $existingCount == 0 ? 1 : 0,
        ]);
    }

    public function deleteImage(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->header('X-CSRF-Token'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $imageId = (int)($params['id'] ?? 0);
        $image = Database::fetch("SELECT * FROM wk_product_images WHERE id=?", [$imageId]);

        if ($image) {
            $filepath = WK_ROOT . '/storage/uploads/products/' . $image['image_path'];
            if (file_exists($filepath)) @unlink($filepath);
            Database::delete('wk_product_images', 'id=?', [$imageId]);

            if ($image['is_primary']) {
                $next = Database::fetch("SELECT id FROM wk_product_images WHERE product_id=? ORDER BY sort_order LIMIT 1", [$image['product_id']]);
                if ($next) Database::update('wk_product_images', ['is_primary' => 1], 'id=?', [$next['id']]);
            }
        }

        Response::json(['success' => true]);
    }

    public function setPrimaryImage(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->header('X-CSRF-Token'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $imageId = (int)($params['id'] ?? 0);
        $image = Database::fetch("SELECT * FROM wk_product_images WHERE id=?", [$imageId]);
        if ($image) {
            Database::update('wk_product_images', ['is_primary' => 0], 'product_id=?', [$image['product_id']]);
            Database::update('wk_product_images', ['is_primary' => 1], 'id=?', [$imageId]);
        }
        Response::json(['success' => true]);
    }

    /**
     * AJAX: Quick-create a category from product form
     */
    public function quickCategory(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->header('X-CSRF-Token'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $name = trim($request->input('name') ?? '');
        $parentId = $request->input('parent_id') ?: null;

        if (empty($name)) {
            Response::json(['success' => false, 'message' => 'Category name required'], 400);
            return;
        }

        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        $base = $slug; $i = 1;
        while (Database::fetchValue("SELECT COUNT(*) FROM wk_categories WHERE slug=?", [$slug])) {
            $slug = $base . '-' . $i++;
        }

        $id = Database::insert('wk_categories', [
            'parent_id' => $parentId,
            'name'      => htmlspecialchars($name, ENT_QUOTES, 'UTF-8'),
            'slug'      => $slug,
            'is_active'  => 1,
        ]);

        Response::json(['success' => true, 'id' => $id, 'name' => $name]);
    }

    /**
     * AJAX: Save variant groups + options, then regenerate combos
     */
    public function saveVariants(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->header('X-CSRF-Token'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $productId = (int)$params['id'];
        \App\Services\VariantService::saveGroups($productId, $request->all());
        $combos = \App\Services\VariantService::generateCombos($productId);
        self::syncVariantStock($productId);
        Response::json(['success' => true, 'combos' => $combos, 'count' => count($combos)]);
    }

    /**
     * AJAX: Update a single variant combo (price, stock, sku, image)
     */
    public function updateCombo(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $comboId = (int)$params['id'];
        \App\Services\VariantService::updateCombo($comboId, $request->all());

        // Sync product total stock from all combos
        $combo = Database::fetch("SELECT product_id FROM wk_variant_combos WHERE id=?", [$comboId]);
        if ($combo) self::syncVariantStock((int)$combo['product_id']);
        Response::json(['success' => true]);
    }

    /**
     * AJAX: Upload image for a primary variant option (e.g. images for "Red")
     */
    public function uploadVariantOptionImage(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf') ?? $request->header('X-CSRF-Token'))) {
            Response::json(['success' => false, 'error' => 'Session expired'], 403); return;
        }
        $productId = (int)$request->input('product_id');
        $optionId = (int)$request->input('option_id');

        if (!$productId || !$optionId) {
            Response::json(['success' => false, 'message' => 'Product ID and Option ID required'], 400);
            return;
        }

        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            Response::json(['success' => false, 'message' => 'No file uploaded'], 400);
            return;
        }

        $allowedExts = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($fileExt, $allowedExts)) {
            Response::json(['success' => false, 'message' => 'Only JPG, PNG, WebP, GIF allowed'], 400);
            return;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            Response::json(['success' => false, 'message' => 'Max 5MB'], 400);
            return;
        }

        $extMap = ['jpeg' => 'jpg', 'jpg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
        $ext = $extMap[$fileExt] ?? 'jpg';
        $filename = 'vopt_' . $optionId . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

        $uploadDir = WK_ROOT . '/storage/uploads/products/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0775, true);

        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
            Response::json(['success' => false, 'message' => 'Upload failed'], 500);
            return;
        }
        // Re-encode to strip any embedded payloads
        self::reencodeImage($uploadDir . $filename, $ext);

        $imageId = \App\Services\VariantService::uploadOptionImage($productId, $optionId, $filename);

        Response::json([
            'success'  => true,
            'image_id' => $imageId,
            'url'      => View::url('storage/uploads/products/' . $filename),
        ]);
    }

    /**
     * AJAX: Delete a variant option image
     */
    public function deleteVariantOptionImage(Request $request, array $params = []): void
    {
        $imageId = (int)$params['id'];
        \App\Services\VariantService::deleteOptionImage($imageId);
        Response::json(['success' => true]);
    }

    private function uniqueSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        $base = $slug; $i = 1;
        while (Database::fetchValue("SELECT COUNT(*) FROM wk_products WHERE slug=?", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /**
     * Sync product's stock_quantity with sum of all variant combo stocks
     */
    private static function syncVariantStock(int $productId): void
    {
        try {
            $totalStock = (int)Database::fetchValue(
                "SELECT COALESCE(SUM(stock_quantity), 0) FROM wk_variant_combos WHERE product_id=? AND is_active=1",
                [$productId]
            );
            Database::query("UPDATE wk_products SET stock_quantity=? WHERE id=?", [$totalStock, $productId]);
        } catch (\Exception $e) {
            // Log but don't crash
        }
    }

    /**
     * Convert weight value to kilograms for storage
     */
    private static function convertToKg(float $value, string $unit): float
    {
        return match($unit) {
            'g'   => $value / 1000,
            'lb'  => $value * 0.453592,
            'oz'  => $value * 0.0283495,
            'ton' => $value * 1000,
            default => $value, // kg
        };
    }

    /**
     * Save product metadata (weight unit, shipping overrides)
     */
    private static function saveProductMeta(int $productId, Request $request): void
    {
        $meta = [
            'weight_unit'       => $request->input('weight_unit') ?? 'kg',
            'shipping_override' => $request->input('shipping_override') ?? '',
            'shipping_charge'   => $request->input('shipping_charge') ?? '',
        ];

        foreach ($meta as $key => $value) {
            Database::query(
                "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('product_meta', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
                [$key . '_' . $productId, trim($value)]
            );
        }
    }

    /**
     * Re-encode an uploaded image using GD to strip any embedded payloads.
     * Converts the image to a clean copy — prevents polyglot attacks
     * (e.g. PHP code hidden inside EXIF/comment data).
     *
     * @param string $filePath Full path to the uploaded image
     * @param string $ext      File extension (jpg, png, webp, gif)
     * @return bool True if re-encoded successfully
     */
    private static function reencodeImage(string $filePath, string $ext): bool
    {
        if (!extension_loaded('gd')) return true; // Skip if GD not available

        try {
            $source = match ($ext) {
                'jpg', 'jpeg' => @imagecreatefromjpeg($filePath),
                'png'         => @imagecreatefrompng($filePath),
                'webp'        => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($filePath) : false,
                'gif'         => @imagecreatefromgif($filePath),
                default       => false,
            };

            if (!$source) return false;

            // Get dimensions
            $width = imagesx($source);
            $height = imagesy($source);

            // Create clean image
            $clean = imagecreatetruecolor($width, $height);

            // Preserve transparency for PNG and GIF
            if ($ext === 'png' || $ext === 'gif') {
                imagealphablending($clean, false);
                imagesavealpha($clean, true);
                $transparent = imagecolorallocatealpha($clean, 0, 0, 0, 127);
                imagefilledrectangle($clean, 0, 0, $width, $height, $transparent);
            }

            // Copy image data (strips all metadata and embedded payloads)
            imagecopy($clean, $source, 0, 0, 0, 0, $width, $height);

            // Save back to same path
            $result = match ($ext) {
                'jpg', 'jpeg' => imagejpeg($clean, $filePath, 90),
                'png'         => imagepng($clean, $filePath, 8),
                'webp'        => function_exists('imagewebp') ? imagewebp($clean, $filePath, 85) : false,
                'gif'         => imagegif($clean, $filePath),
                default       => false,
            };

            imagedestroy($source);
            imagedestroy($clean);

            return $result;
        } catch (\Exception $e) {
            return false;
        }
    }
}