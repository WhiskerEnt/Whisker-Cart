<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session};

class ImportController
{
    public function index(Request $request, array $params = []): void
    {
        View::render('admin/import/index', ['pageTitle' => 'CSV Import'], 'admin/layouts/main');
    }

    public function process(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/import'));
            return;
        }

        $type = $request->input('import_type');
        if (!in_array($type, ['categories', 'products', 'variants', 'all'])) {
            Session::flash('error', 'Invalid import type.');
            Response::redirect(View::url('admin/import'));
            return;
        }

        if (empty($_FILES['csv_file']['tmp_name']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            Session::flash('error', 'Please upload a valid CSV file.');
            Response::redirect(View::url('admin/import'));
            return;
        }

        $ext = strtolower(pathinfo($_FILES['csv_file']['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            Session::flash('error', 'Only .csv files are accepted.');
            Response::redirect(View::url('admin/import'));
            return;
        }

        $rows = self::parseCSV($_FILES['csv_file']['tmp_name']);
        if (empty($rows)) {
            Session::flash('error', 'CSV has no data rows or headers are missing.');
            Response::redirect(View::url('admin/import'));
            return;
        }

        $skipExisting = $request->input('skip_existing') ? true : false;

        try {
            if ($type === 'all') {
                $result = self::importAll($rows, $skipExisting);
            } else {
                $result = match ($type) {
                    'categories' => self::importCategories($rows, $skipExisting),
                    'products'   => self::importProducts($rows, $skipExisting),
                    'variants'   => self::importVariants($rows),
                };
            }
            Session::flash('success', $result);
        } catch (\Exception $e) {
            Session::flash('error', 'Import failed. Please check your CSV format and try again.');
        }

        Response::redirect(View::url('admin/import'));
    }

    public function sample(Request $request, array $params = []): void
    {
        $type = $params['type'] ?? '';
        $filename = "whisker-sample-{$type}.csv";

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo "\xEF\xBB\xBF"; // BOM for Excel

        $out = fopen('php://output', 'w');

        switch ($type) {
            case 'categories':
                fputcsv($out, ['name', 'parent', 'description', 'sort_order', 'is_active']);
                fputcsv($out, ['Clothing', '', 'All clothing items', '1', '1']);
                fputcsv($out, ['T-Shirts', 'Clothing', 'Casual and formal tees', '1', '1']);
                fputcsv($out, ['Jeans', 'Clothing', 'Denim jeans collection', '2', '1']);
                fputcsv($out, ['Electronics', '', 'Gadgets and accessories', '2', '1']);
                fputcsv($out, ['Headphones', 'Electronics', 'Wireless and wired', '1', '1']);
                break;

            case 'products':
                fputcsv($out, ['sku', 'name', 'category', 'price', 'sale_price', 'stock_quantity', 'description', 'short_description', 'weight', 'is_active', 'is_featured', 'meta_title', 'meta_description', 'meta_keywords']);
                fputcsv($out, ['TSH-001', 'Classic White Tee', 'T-Shirts', '999.00', '799.00', '50', 'Premium cotton white t-shirt.', 'Premium white tee', '0.2', '1', '1', '', '', '']);
                fputcsv($out, ['TSH-002', 'Black Graphic Tee', 'T-Shirts', '1299.00', '', '30', 'Bold graphic print on cotton.', 'Bold graphic tee', '0.22', '1', '0', '', '', '']);
                fputcsv($out, ['JNS-001', 'Slim Fit Denim', 'Jeans', '2499.00', '1999.00', '25', 'Stretch denim slim fit.', 'Slim fit denim', '0.6', '1', '1', '', '', '']);
                break;

            case 'variants':
                fputcsv($out, ['product_sku', 'variant_group', 'options', 'combo_sku', 'combo_price', 'combo_stock']);
                fputcsv($out, ['TSH-001', 'Size', 'S,M,L,XL', '', '', '']);
                fputcsv($out, ['TSH-001', 'Color', 'White,Black,Navy', '', '', '']);
                fputcsv($out, ['TSH-001', '', '', 'TSH-001-S-WHT', '799.00', '10']);
                fputcsv($out, ['TSH-001', '', '', 'TSH-001-M-WHT', '799.00', '15']);
                fputcsv($out, ['JNS-001', 'Size', '28,30,32,34,36', '', '', '']);
                break;

            case 'all':
                fputcsv($out, ['row_type', 'name', 'parent', 'sku', 'category', 'price', 'sale_price', 'stock_quantity', 'description', 'short_description', 'weight', 'is_active', 'is_featured', 'variant_group', 'options', 'combo_sku', 'combo_price', 'combo_stock', 'meta_title', 'meta_description', 'meta_keywords']);
                // Categories
                fputcsv($out, ['category', 'Clothing', '', '', '', '', '', '', 'All clothing items', '', '', '1', '', '', '', '', '', '', '', '', '']);
                fputcsv($out, ['category', 'T-Shirts', 'Clothing', '', '', '', '', '', 'Casual tees', '', '', '1', '', '', '', '', '', '', '', '', '']);
                fputcsv($out, ['category', 'Jeans', 'Clothing', '', '', '', '', '', 'Denim collection', '', '', '1', '', '', '', '', '', '', '', '', '']);
                // Products
                fputcsv($out, ['product', 'Classic White Tee', '', 'TSH-001', 'T-Shirts', '999.00', '799.00', '50', 'Premium cotton white t-shirt.', 'Premium white tee', '0.2', '1', '1', '', '', '', '', '', 'Classic White Tee | Store', 'Premium cotton white tee', 'white,tee,cotton']);
                fputcsv($out, ['product', 'Slim Fit Denim', '', 'JNS-001', 'Jeans', '2499.00', '1999.00', '25', 'Stretch denim slim fit jeans.', 'Slim fit denim', '0.6', '1', '1', '', '', '', '', '', '', '', '']);
                // Variant groups
                fputcsv($out, ['variant', '', '', 'TSH-001', '', '', '', '', '', '', '', '', '', 'Size', 'S,M,L,XL', '', '', '', '', '', '']);
                fputcsv($out, ['variant', '', '', 'TSH-001', '', '', '', '', '', '', '', '', '', 'Color', 'White,Black', '', '', '', '', '', '']);
                // Variant combo overrides
                fputcsv($out, ['combo', '', '', 'TSH-001', '', '', '', '', '', '', '', '', '', '', '', 'TSH-001-S-WHT', '799.00', '10', '', '', '']);
                fputcsv($out, ['combo', '', '', 'TSH-001', '', '', '', '', '', '', '', '', '', '', '', 'TSH-001-M-WHT', '799.00', '15', '', '', '']);
                fputcsv($out, ['combo', '', '', 'TSH-001', '', '', '', '', '', '', '', '', '', '', '', 'TSH-001-L-BLK', '899.00', '8', '', '', '']);
                break;
        }

        fclose($out);
        exit;
    }

    // ═══════════════════════════════════════════
    //  ALL-IN-ONE IMPORT
    // ═══════════════════════════════════════════

    private static function importAll(array $rows, bool $skipExisting): string
    {
        $catRows = $prodRows = $variantRows = $comboRows = [];

        foreach ($rows as $row) {
            $type = strtolower(trim($row['row_type'] ?? ''));
            switch ($type) {
                case 'category': $catRows[] = $row; break;
                case 'product':  $prodRows[] = $row; break;
                case 'variant':  $variantRows[] = $row; break;
                case 'combo':    $comboRows[] = $row; break;
            }
        }

        $results = [];

        // 1. Categories (parents first, then children)
        if (!empty($catRows)) {
            $catCreated = 0;
            // Parents first
            foreach ($catRows as $row) {
                if (!empty(trim($row['parent'] ?? ''))) continue;
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;
                if (Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$name])) {
                    if ($skipExisting) continue;
                }
                if (!Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$name])) {
                    Database::insert('wk_categories', [
                        'name' => $name, 'slug' => self::uniqueSlug($name, 'wk_categories'),
                        'description' => $row['description'] ?? '', 'sort_order' => (int)($row['sort_order'] ?? 0),
                        'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                    ]);
                    $catCreated++;
                }
            }
            // Children
            foreach ($catRows as $row) {
                $parent = trim($row['parent'] ?? '');
                if (empty($parent)) continue;
                $name = trim($row['name'] ?? '');
                if (empty($name)) continue;
                $parentId = Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$parent]);
                if (!$parentId) continue;
                if (Database::fetchValue("SELECT id FROM wk_categories WHERE name=? AND parent_id=?", [$name, $parentId])) {
                    if ($skipExisting) continue;
                }
                if (!Database::fetchValue("SELECT id FROM wk_categories WHERE name=? AND parent_id=?", [$name, $parentId])) {
                    Database::insert('wk_categories', [
                        'parent_id' => $parentId, 'name' => $name,
                        'slug' => self::uniqueSlug($name, 'wk_categories'),
                        'description' => $row['description'] ?? '', 'sort_order' => (int)($row['sort_order'] ?? 0),
                        'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                    ]);
                    $catCreated++;
                }
            }
            $results[] = "{$catCreated} categories";
        }

        // 2. Products
        if (!empty($prodRows)) {
            $prodCreated = 0; $prodUpdated = 0;
            foreach ($prodRows as $row) {
                $sku = trim($row['sku'] ?? '');
                $name = trim($row['name'] ?? '');
                if (empty($sku) || empty($name)) continue;

                $existingId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
                if ($existingId && $skipExisting) continue;

                $categoryId = null;
                $catName = trim($row['category'] ?? '');
                if (!empty($catName)) {
                    $categoryId = Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$catName]);
                }

                $data = [
                    'sku' => $sku, 'name' => $name,
                    'description' => $row['description'] ?? '', 'short_description' => $row['short_description'] ?? '',
                    'price' => (float)($row['price'] ?? 0),
                    'sale_price' => !empty($row['sale_price']) ? (float)$row['sale_price'] : null,
                    'stock_quantity' => (int)($row['stock_quantity'] ?? 0),
                    'weight' => !empty($row['weight']) ? (float)$row['weight'] : null,
                    'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                    'is_featured' => ($row['is_featured'] ?? '0') === '1' ? 1 : 0,
                    'meta_title' => !empty($row['meta_title']) ? $row['meta_title'] : null,
                    'meta_description' => !empty($row['meta_description']) ? $row['meta_description'] : null,
                    'meta_keywords' => !empty($row['meta_keywords']) ? $row['meta_keywords'] : null,
                ];
                if ($categoryId) $data['category_id'] = $categoryId;

                if ($existingId) {
                    Database::update('wk_products', $data, 'id=?', [$existingId]);
                    $prodUpdated++;
                } else {
                    $data['slug'] = self::uniqueSlug($name, 'wk_products');
                    Database::insert('wk_products', $data);
                    $prodCreated++;
                }
            }
            $results[] = "{$prodCreated} products created" . ($prodUpdated ? ", {$prodUpdated} updated" : "");
        }

        // 3. Variant groups
        $affectedProducts = [];
        if (!empty($variantRows)) {
            $groupsCreated = 0;
            foreach ($variantRows as $row) {
                $sku = trim($row['sku'] ?? '');
                $groupName = trim($row['variant_group'] ?? '');
                $options = trim($row['options'] ?? '');
                if (empty($sku) || empty($groupName) || empty($options)) continue;

                $productId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
                if (!$productId) continue;

                if (Database::fetchValue("SELECT id FROM wk_variant_groups WHERE product_id=? AND name=?", [$productId, $groupName])) continue;

                $groupId = Database::insert('wk_variant_groups', [
                    'product_id' => $productId, 'name' => $groupName, 'sort_order' => $groupsCreated,
                ]);
                foreach (array_map('trim', explode(',', $options)) as $oi => $val) {
                    if (empty($val)) continue;
                    Database::insert('wk_variant_options', [
                        'group_id' => $groupId, 'value' => $val, 'sort_order' => $oi,
                    ]);
                }
                $affectedProducts[$productId] = true;
                $groupsCreated++;
            }
            if ($groupsCreated > 0) $results[] = "{$groupsCreated} variant groups";
        }

        // Generate combos
        foreach (array_keys($affectedProducts) as $pid) {
            \App\Services\VariantService::generateCombos($pid);
        }

        // 4. Combo overrides
        if (!empty($comboRows)) {
            $combosUpdated = 0;
            foreach ($comboRows as $row) {
                $sku = trim($row['sku'] ?? '');
                $comboSku = trim($row['combo_sku'] ?? '');
                if (empty($sku) || empty($comboSku)) continue;

                $productId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
                if (!$productId) continue;

                $update = [];
                if (!empty($comboSku)) $update['sku'] = $comboSku;
                if (!empty($row['combo_price'])) $update['price_override'] = (float)$row['combo_price'];
                if (!empty($row['combo_stock'])) $update['stock_quantity'] = (int)$row['combo_stock'];
                if (empty($update)) continue;

                $comboId = Database::fetchValue("SELECT id FROM wk_variant_combos WHERE product_id=? AND sku=?", [$productId, $comboSku]);
                if (!$comboId) {
                    $comboId = Database::fetchValue(
                        "SELECT id FROM wk_variant_combos WHERE product_id=? AND (sku IS NULL OR sku='') ORDER BY id LIMIT 1",
                        [$productId]
                    );
                }
                if ($comboId) {
                    Database::update('wk_variant_combos', $update, 'id=?', [$comboId]);
                    $combosUpdated++;
                }
            }
            if ($combosUpdated > 0) $results[] = "{$combosUpdated} combo overrides";
        }

        // Sync stock
        foreach (array_keys($affectedProducts) as $pid) {
            $totalStock = (int)Database::fetchValue(
                "SELECT COALESCE(SUM(stock_quantity), 0) FROM wk_variant_combos WHERE product_id=? AND is_active=1", [$pid]
            );
            Database::update('wk_products', ['stock_quantity' => $totalStock], 'id=?', [$pid]);
        }

        return "All-in-one import complete: " . (empty($results) ? "nothing to import" : implode(', ', $results)) . ".";
    }

    // ═══════════════════════════════════════════
    //  INDIVIDUAL IMPORTS
    // ═══════════════════════════════════════════

    private static function importCategories(array $rows, bool $skipExisting): string
    {
        $created = 0; $skipped = 0;

        // Parents first
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? ''); $parent = trim($row['parent'] ?? '');
            if (empty($name) || !empty($parent)) continue;
            $exists = Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$name]);
            if ($exists && $skipExisting) { $skipped++; continue; }
            if (!$exists) {
                Database::insert('wk_categories', [
                    'name' => $name, 'slug' => self::uniqueSlug($name, 'wk_categories'),
                    'description' => $row['description'] ?? '', 'sort_order' => (int)($row['sort_order'] ?? 0),
                    'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                ]);
                $created++;
            }
        }
        // Children
        foreach ($rows as $row) {
            $name = trim($row['name'] ?? ''); $parent = trim($row['parent'] ?? '');
            if (empty($name) || empty($parent)) continue;
            $parentId = Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$parent]);
            if (!$parentId) continue;
            $exists = Database::fetchValue("SELECT id FROM wk_categories WHERE name=? AND parent_id=?", [$name, $parentId]);
            if ($exists && $skipExisting) { $skipped++; continue; }
            if (!$exists) {
                Database::insert('wk_categories', [
                    'parent_id' => $parentId, 'name' => $name, 'slug' => self::uniqueSlug($name, 'wk_categories'),
                    'description' => $row['description'] ?? '', 'sort_order' => (int)($row['sort_order'] ?? 0),
                    'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                ]);
                $created++;
            }
        }
        return "Categories: {$created} created, {$skipped} skipped.";
    }

    private static function importProducts(array $rows, bool $skipExisting): string
    {
        $created = 0; $updated = 0; $skipped = 0;
        foreach ($rows as $row) {
            $sku = trim($row['sku'] ?? ''); $name = trim($row['name'] ?? '');
            if (empty($sku) || empty($name)) continue;

            $existingId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
            if ($existingId && $skipExisting) { $skipped++; continue; }

            $categoryId = null;
            $catName = trim($row['category'] ?? '');
            if (!empty($catName)) $categoryId = Database::fetchValue("SELECT id FROM wk_categories WHERE name=?", [$catName]);

            $data = [
                'sku' => $sku, 'name' => $name,
                'description' => $row['description'] ?? '', 'short_description' => $row['short_description'] ?? '',
                'price' => (float)($row['price'] ?? 0),
                'sale_price' => !empty($row['sale_price']) ? (float)$row['sale_price'] : null,
                'stock_quantity' => (int)($row['stock_quantity'] ?? 0),
                'weight' => !empty($row['weight']) ? (float)$row['weight'] : null,
                'is_active' => ($row['is_active'] ?? '1') === '1' ? 1 : 0,
                'is_featured' => ($row['is_featured'] ?? '0') === '1' ? 1 : 0,
                'meta_title' => !empty($row['meta_title']) ? $row['meta_title'] : null,
                'meta_description' => !empty($row['meta_description']) ? $row['meta_description'] : null,
                'meta_keywords' => !empty($row['meta_keywords']) ? $row['meta_keywords'] : null,
            ];
            if ($categoryId) $data['category_id'] = $categoryId;

            if ($existingId) {
                Database::update('wk_products', $data, 'id=?', [$existingId]);
                $updated++;
            } else {
                $data['slug'] = self::uniqueSlug($name, 'wk_products');
                Database::insert('wk_products', $data);
                $created++;
            }
        }
        return "Products: {$created} created, {$updated} updated, {$skipped} skipped.";
    }

    private static function importVariants(array $rows): string
    {
        $groupsCreated = 0; $combosUpdated = 0;
        $groupRows = []; $comboRows = [];
        foreach ($rows as $row) {
            if (!empty(trim($row['variant_group'] ?? ''))) $groupRows[] = $row;
            elseif (!empty(trim($row['combo_sku'] ?? ''))) $comboRows[] = $row;
        }

        $affectedProducts = [];
        foreach ($groupRows as $row) {
            $sku = trim($row['product_sku'] ?? '');
            $productId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
            if (!$productId) continue;
            $groupName = trim($row['variant_group']); $options = trim($row['options'] ?? '');
            if (empty($groupName) || empty($options)) continue;
            if (Database::fetchValue("SELECT id FROM wk_variant_groups WHERE product_id=? AND name=?", [$productId, $groupName])) continue;

            $groupId = Database::insert('wk_variant_groups', ['product_id' => $productId, 'name' => $groupName, 'sort_order' => $groupsCreated]);
            foreach (array_map('trim', explode(',', $options)) as $oi => $val) {
                if (empty($val)) continue;
                Database::insert('wk_variant_options', ['group_id' => $groupId, 'value' => $val, 'sort_order' => $oi]);
            }
            $affectedProducts[$productId] = true;
            $groupsCreated++;
        }

        foreach (array_keys($affectedProducts) as $pid) \App\Services\VariantService::generateCombos($pid);

        foreach ($comboRows as $row) {
            $sku = trim($row['product_sku'] ?? ''); $comboSku = trim($row['combo_sku'] ?? '');
            if (empty($sku) || empty($comboSku)) continue;
            $productId = Database::fetchValue("SELECT id FROM wk_products WHERE sku=?", [$sku]);
            if (!$productId) continue;
            $update = [];
            if (!empty($comboSku)) $update['sku'] = $comboSku;
            if (!empty($row['combo_price'])) $update['price_override'] = (float)$row['combo_price'];
            if (!empty($row['combo_stock'])) $update['stock_quantity'] = (int)$row['combo_stock'];
            if (empty($update)) continue;
            $comboId = Database::fetchValue("SELECT id FROM wk_variant_combos WHERE product_id=? AND sku=?", [$productId, $comboSku])
                ?: Database::fetchValue("SELECT id FROM wk_variant_combos WHERE product_id=? AND (sku IS NULL OR sku='') ORDER BY id LIMIT 1", [$productId]);
            if ($comboId) { Database::update('wk_variant_combos', $update, 'id=?', [$comboId]); $combosUpdated++; }
        }

        foreach (array_keys($affectedProducts) as $pid) {
            $total = (int)Database::fetchValue("SELECT COALESCE(SUM(stock_quantity),0) FROM wk_variant_combos WHERE product_id=? AND is_active=1", [$pid]);
            Database::update('wk_products', ['stock_quantity' => $total], 'id=?', [$pid]);
        }
        return "Variants: {$groupsCreated} groups, combos generated. {$combosUpdated} combo overrides applied.";
    }

    // ═══════════════════════════════════════════

    private static function parseCSV(string $file): array
    {
        $rows = [];
        $handle = fopen($file, 'r');
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);
        $header = fgetcsv($handle);
        if (!$header) return [];
        $header = array_map(fn($h) => strtolower(trim($h)), $header);
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($header)) {
                // Sanitize CSV injection: strip leading formula characters
                $row = array_map(fn($cell) => self::sanitizeCsvCell($cell), $row);
                $rows[] = array_combine($header, $row);
            }
        }
        fclose($handle);
        return $rows;
    }

    /**
     * Prevent CSV injection — strip leading characters that spreadsheets interpret as formulas.
     */
    private static function sanitizeCsvCell(string $value): string
    {
        $value = trim($value);
        // Strip leading =, +, -, @, tab, carriage return that trigger formula execution
        if ($value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"])) {
            $value = "'" . $value; // Prefix with single quote (Excel safe)
        }
        return $value;
    }

    private static function uniqueSlug(string $name, string $table): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        $base = $slug; $i = 1;
        while (Database::fetchValue("SELECT COUNT(*) FROM {$table} WHERE slug=?", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}