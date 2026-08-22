<?php
namespace App\Controllers\Admin;

use Core\{Request, View, Database, Response, Session, Validator};

class CategoryController
{
    public function index(Request $request, array $params = []): void
    {
        $categories = Database::fetchAll(
            "SELECT c.*, p.name AS parent_name,
                    (SELECT COUNT(*) FROM wk_products WHERE category_id = c.id) AS product_count
             FROM wk_categories c
             LEFT JOIN wk_categories p ON p.id = c.parent_id
             ORDER BY c.parent_id IS NULL DESC, c.parent_id, c.sort_order, c.name"
        );
        View::render('admin/categories/index', [
            'pageTitle'  => 'Categories',
            'categories' => $categories,
        ], 'admin/layouts/main');
    }

    public function create(Request $request, array $params = []): void
    {
        $parents = Database::fetchAll("SELECT id, name FROM wk_categories WHERE parent_id IS NULL ORDER BY name");
        View::render('admin/categories/create', [
            'pageTitle' => 'Add Category',
            'parents'   => $parents,
        ], 'admin/layouts/main');
    }

    public function store(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/categories/create'));
            return;
        }

        $v = new Validator($request->all(), [
            'name' => 'required|min:2|max:100',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/categories/create'));
            return;
        }

        // parent_id must be 0/null or reference an existing top-level
        // category. Categories are a flat 2-level structure (parent → child,
        // no grandchildren), which prevents cycles and orphans by
        // construction — a child can never be picked as a parent.
        $parentId = $this->validParentId($request->input('parent_id'), null);
        if ($parentId === false) {
            Session::flash('error', 'Selected parent category is invalid.');
            Response::redirect(View::url('admin/categories/create'));
            return;
        }

        $name = $request->clean('name');
        $slug = $this->uniqueSlug($name);

        Database::insert('wk_categories', [
            'parent_id'   => $parentId,
            'name'        => $name,
            'slug'        => $slug,
            'description' => $request->input('description') ?? '',
            'sort_order'  => (int)($request->input('sort_order') ?? 0),
            'is_active'   => $request->input('is_active') ? 1 : 0,
        ]);

        Session::flash('success', 'Category created!');
        Response::redirect(View::url('admin/categories'));
    }

    public function edit(Request $request, array $params = []): void
    {
        $category = Database::fetch("SELECT * FROM wk_categories WHERE id = ?", [$params['id']]);
        if (!$category) { Response::notFound(); return; }

        $parents = Database::fetchAll(
            "SELECT id, name FROM wk_categories WHERE parent_id IS NULL AND id != ? ORDER BY name",
            [$params['id']]
        );

        View::render('admin/categories/edit', [
            'pageTitle' => 'Edit Category',
            'category'  => $category,
            'parents'   => $parents,
        ], 'admin/layouts/main');
    }

    public function update(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/categories/edit/' . $params['id']));
            return;
        }

        $v = new Validator($request->all(), [
            'name' => 'required|min:2|max:100',
        ]);
        if ($v->fails()) {
            Session::flash('error', $v->firstError());
            Response::redirect(View::url('admin/categories/edit/' . $params['id']));
            return;
        }

        // parent_id must be a valid top-level category id and must not be
        // the row itself; passing the current id lets validParentId()
        // reject the self-cycle case.
        $parentId = $this->validParentId($request->input('parent_id'), (int)$params['id']);
        if ($parentId === false) {
            Session::flash('error', 'Selected parent category is invalid.');
            Response::redirect(View::url('admin/categories/edit/' . $params['id']));
            return;
        }

        Database::update('wk_categories', [
            'parent_id'        => $parentId,
            'name'             => $request->clean('name'),
            'description'      => $request->input('description') ?? '',
            'sort_order'       => (int)($request->input('sort_order') ?? 0),
            'is_active'        => $request->input('is_active') ? 1 : 0,
            'meta_title'       => trim($request->input('meta_title') ?? '') ?: null,
            'meta_description' => trim($request->input('meta_description') ?? '') ?: null,
            'meta_keywords'    => trim($request->input('meta_keywords') ?? '') ?: null,
        ], 'id = ?', [$params['id']]);

        Session::flash('success', 'Category updated!');
        Response::redirect(View::url('admin/categories'));
    }

    /**
     * Validate a parent_id input.
     *
     * Returns:
     *   null  — top-level category (no parent)
     *   int   — a valid top-level parent category id
     *   false — invalid (non-existent, self-reference, or not a top-level row)
     */
    private function validParentId($rawInput, ?int $selfId)
    {
        $id = (int)($rawInput ?: 0);
        if ($id === 0) return null;
        if ($selfId !== null && $id === $selfId) return false; // self-parent
        $row = Database::fetch(
            "SELECT id FROM wk_categories WHERE id=? AND parent_id IS NULL",
            [$id]
        );
        return $row ? $id : false;
    }

    public function delete(Request $request, array $params = []): void
    {
        if (!Session::verifyCsrf($request->input('wk_csrf'))) {
            Session::flash('error', 'Session expired.');
            Response::redirect(View::url('admin/categories'));
            return;
        }
        // Move child categories to no parent
        Database::update('wk_categories', ['parent_id' => null], 'parent_id = ?', [$params['id']]);
        // Unlink products
        Database::update('wk_products', ['category_id' => null], 'category_id = ?', [$params['id']]);
        // Delete
        Database::delete('wk_categories', 'id = ?', [$params['id']]);

        Session::flash('success', 'Category deleted.');
        Response::redirect(View::url('admin/categories'));
    }

    private function uniqueSlug(string $name): string
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
        $slug = trim($slug, '-');
        $base = $slug;
        $i = 1;
        while (Database::fetchValue("SELECT COUNT(*) FROM wk_categories WHERE slug = ?", [$slug])) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}
