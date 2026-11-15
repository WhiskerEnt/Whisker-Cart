<?php
namespace App\Services;

use Core\Database;
use Core\View;

/**
 * WHISKER — 2-Level Variant System
 *
 * Primary variant (e.g. Color): each option has its own image gallery
 * Secondary variant (e.g. Size): each primary+secondary combo has stock/price/SKU
 *
 * Example:
 *   Primary: Color [Green, Red]
 *   Secondary: Size [XS, M, L]
 *   Combos: Green/XS(10), Green/M(5), Green/L(10), Red/XS(5), Red/M(5), Red/L(10)
 *   Images: Green has 3 photos, Red has 3 photos
 */
class VariantService
{
    /**
     * Get full variant data for a product
     */
    public static function getForProduct(int $productId): array
    {
        $empty = ['groups' => [], 'primary' => null, 'secondary' => null, 'combos' => [], 'has_variants' => false];

        try {
            $groups = Database::fetchAll(
                "SELECT * FROM wk_variant_groups WHERE product_id=? ORDER BY sort_order",
                [$productId]
            );
        } catch (\Exception $e) {
            return $empty;
        }

        if (empty($groups)) return $empty;

        $primary = null;
        $secondary = null;

        foreach ($groups as &$group) {
            $group['options'] = Database::fetchAll(
                "SELECT * FROM wk_variant_options WHERE group_id=? ORDER BY sort_order",
                [$group['id']]
            );

            // First group = primary (has images), second = secondary
            if (!$primary) {
                $primary = $group;
                // Load images for each primary option
                foreach ($primary['options'] as &$opt) {
                    $opt['images'] = Database::fetchAll(
                        "SELECT * FROM wk_product_images WHERE product_id=? AND alt_text=? ORDER BY sort_order",
                        [$productId, 'variant_opt_' . $opt['id']]
                    );
                }
                unset($opt);
            } elseif (!$secondary) {
                $secondary = $group;
            }
        }
        unset($group);

        $combos = [];
        try {
            $combos = Database::fetchAll(
                "SELECT * FROM wk_variant_combos WHERE product_id=? AND is_active=1 ORDER BY id",
                [$productId]
            );
        } catch (\Exception $e) {}

        return [
            'groups'    => $groups,
            'primary'   => $primary,
            'secondary' => $secondary,
            'combos'    => $combos,
            'has_variants' => !empty($groups),
        ];
    }

    /**
     * Save variant groups from form data
     */
    public static function saveGroups(int $productId, array $data): void
    {
        // Delete existing groups (cascades to options)
        Database::delete('wk_variant_groups', 'product_id=?', [$productId]);

        if (empty($data['variant_group_name'])) return;

        foreach ($data['variant_group_name'] as $gi => $groupName) {
            $groupName = trim($groupName);
            if (empty($groupName)) continue;

            $groupId = Database::insert('wk_variant_groups', [
                'product_id' => $productId,
                'name'       => htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8'),
                'sort_order' => $gi,
            ]);

            $options = $data['variant_options'][$gi] ?? '';
            $optionValues = array_map('trim', explode(',', $options));
            foreach ($optionValues as $oi => $val) {
                if (empty($val)) continue;
                Database::insert('wk_variant_options', [
                    'group_id'   => $groupId,
                    'value'      => htmlspecialchars($val, ENT_QUOTES, 'UTF-8'),
                    'sort_order' => $oi,
                ]);
            }
        }
    }

    /**
     * Generate combos from primary × secondary options
     */
    public static function generateCombos(int $productId): array
    {
        $groups = Database::fetchAll(
            "SELECT * FROM wk_variant_groups WHERE product_id=? ORDER BY sort_order",
            [$productId]
        );

        if (empty($groups)) {
            Database::delete('wk_variant_combos', 'product_id=?', [$productId]);
            return [];
        }

        // Get options per group
        $allOptions = [];
        foreach ($groups as $g) {
            $opts = Database::fetchAll("SELECT * FROM wk_variant_options WHERE group_id=? ORDER BY sort_order", [$g['id']]);
            if (!empty($opts)) $allOptions[] = $opts;
        }
        if (empty($allOptions)) return [];

        // Cartesian product
        $combinations = [[]];
        foreach ($allOptions as $opts) {
            $new = [];
            foreach ($combinations as $combo) {
                foreach ($opts as $opt) {
                    $new[] = array_merge($combo, [$opt]);
                }
            }
            $combinations = $new;
        }

        // Preserve existing combo data
        $existing = Database::fetchAll("SELECT * FROM wk_variant_combos WHERE product_id=?", [$productId]);
        $existingMap = [];
        foreach ($existing as $ex) {
            $existingMap[$ex['option_ids']] = $ex;
        }

        Database::delete('wk_variant_combos', 'product_id=?', [$productId]);

        // Get product's current stock to distribute to new combos
        $productStock = (int)Database::fetchValue("SELECT stock_quantity FROM wk_products WHERE id=?", [$productId]);
        $comboCount = count($combinations);
        $stockPerCombo = $comboCount > 0 ? (int)floor($productStock / $comboCount) : 0;

        $results = [];
        foreach ($combinations as $combo) {
            $optionIds = array_map(fn($o) => $o['id'], $combo);
            $idsStr = implode(',', $optionIds);
            $label = implode(' / ', array_map(fn($o) => $o['value'], $combo));

            $old = $existingMap[$idsStr] ?? null;

            $id = Database::insert('wk_variant_combos', [
                'product_id'     => $productId,
                'option_ids'     => $idsStr,
                'label'          => $label,
                'sku'            => $old['sku'] ?? null,
                'price_override' => $old['price_override'] ?? null,
                'stock_quantity' => $old['stock_quantity'] ?? $stockPerCombo,
                'image_id'       => null,
                'is_active'      => 1,
            ]);

            $results[] = ['id' => $id, 'label' => $label];
        }

        return $results;
    }

    /**
     * Update a single combo
     */
    public static function updateCombo(int $comboId, array $data): void
    {
        $update = [];
        if (isset($data['sku'])) $update['sku'] = $data['sku'] ?: null;
        if (isset($data['price_override'])) $update['price_override'] = $data['price_override'] !== '' ? (float)$data['price_override'] : null;
        if (isset($data['stock_quantity'])) $update['stock_quantity'] = (int)$data['stock_quantity'];
        if (isset($data['image_id'])) $update['image_id'] = $data['image_id'] ?: null;
        if (isset($data['is_active'])) $update['is_active'] = (int)$data['is_active'];
        if (!empty($update)) {
            Database::update('wk_variant_combos', $update, 'id=?', [$comboId]);
        }
    }

    /**
     * Upload an image for a primary variant option
     */
    public static function uploadOptionImage(int $productId, int $optionId, string $filename): int
    {
        $count = Database::fetchValue(
            "SELECT COUNT(*) FROM wk_product_images WHERE product_id=? AND alt_text=?",
            [$productId, 'variant_opt_' . $optionId]
        );

        return Database::insert('wk_product_images', [
            'product_id' => $productId,
            'image_path' => $filename,
            'alt_text'   => 'variant_opt_' . $optionId,
            'sort_order' => $count,
            'is_primary' => 0,
        ]);
    }

    /**
     * Delete a variant option image
     */
    public static function deleteOptionImage(int $imageId): void
    {
        $img = Database::fetch("SELECT * FROM wk_product_images WHERE id=?", [$imageId]);
        if ($img) {
            ImageService::deleteFiles($img['image_path']);
            Database::delete('wk_product_images', 'id=?', [$imageId]);
        }
    }

    /**
     * Get images for a specific primary option
     */
    public static function getOptionImages(int $productId, int $optionId): array
    {
        return Database::fetchAll(
            "SELECT * FROM wk_product_images WHERE product_id=? AND alt_text=? ORDER BY sort_order",
            [$productId, 'variant_opt_' . $optionId]
        );
    }

    /**
     * Get storefront data (for JS)
     */
    public static function getStorefrontData(int $productId): array
    {
        $data = self::getForProduct($productId);
        if (!$data['has_variants']) return [];

        $primaryOptions = [];
        if ($data['primary']) {
            foreach ($data['primary']['options'] as $opt) {
                $images = array_map(
                    fn($img) => View::url('storage/uploads/products/' . ImageService::bestName($img['image_path'])),
                    $opt['images'] ?? []
                );
                $primaryOptions[] = [
                    'id'     => $opt['id'],
                    'value'  => $opt['value'],
                    'color'  => $opt['color_hex'] ?? null,
                    'images' => $images,
                ];
            }
        }

        $secondaryOptions = [];
        if ($data['secondary']) {
            foreach ($data['secondary']['options'] as $opt) {
                $secondaryOptions[] = ['id' => $opt['id'], 'value' => $opt['value']];
            }
        }

        $combos = [];
        foreach ($data['combos'] as $combo) {
            $combos[] = [
                'id'         => $combo['id'],
                'label'      => $combo['label'],
                'option_ids' => $combo['option_ids'],
                'price'      => $combo['price_override'],
                'stock'      => $combo['stock_quantity'],
                'sku'        => $combo['sku'],
            ];
        }

        return [
            'primary_name'    => $data['primary']['name'] ?? '',
            'primary_options' => $primaryOptions,
            'secondary_name'  => $data['secondary']['name'] ?? '',
            'secondary_options'=> $secondaryOptions,
            'combos'          => $combos,
        ];
    }
}