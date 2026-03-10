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

        $name = $request->clean('name');
        $slug = $this->uniqueSlug($name);

        Database::insert('wk_categories', [
            'parent_id'   => $request->input('parent_id') ?: null,
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

        Database::update('wk_categories', [
            'parent_id'        => $request->input('parent_id') ?: null,
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
