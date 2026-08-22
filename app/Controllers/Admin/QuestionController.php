<?php
namespace App\Controllers\Admin;

use App\Services\QuestionService;
use Core\{Request, View, Database, Response, Session};

class QuestionController
{
    public function index(Request $request, array $params = []): void
    {
        $filter = (string) $request->input('status');
        if (!in_array($filter, ['pending', 'published', 'rejected'], true)) $filter = 'pending';

        $questions = [];
        $counts = ['pending' => 0, 'published' => 0, 'rejected' => 0];
        try {
            $questions = Database::fetchAll(
                "SELECT q.*, p.name AS product_name, p.slug AS product_slug, a.username AS answered_by_name
                   FROM wk_questions q
                   LEFT JOIN wk_products p ON p.id = q.product_id
                   LEFT JOIN wk_admins a ON a.id = q.answered_by
                  WHERE q.status = ?
                  ORDER BY q.created_at DESC
                  LIMIT 200",
                [$filter]
            );
            foreach (Database::fetchAll("SELECT status, COUNT(*) AS n FROM wk_questions GROUP BY status") as $row) {
                $counts[$row['status']] = (int) $row['n'];
            }
        } catch (\Exception $e) {
            // Table arrives with a migration.
        }

        $settings = [];
        foreach (Database::fetchAll("SELECT setting_key, setting_value FROM wk_settings WHERE setting_group='questions'") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        View::render('admin/questions/index', [
            'pageTitle' => 'Customer Questions',
            'questions' => $questions,
            'counts'    => $counts,
            'filter'    => $filter,
            'settings'  => $settings,
        ], 'admin/layouts/main');
    }

    /** Answer and publish together — the only way a question becomes public. */
    public function publish(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        $result = QuestionService::publish(
            (int) $params['id'],
            (string) $request->input('answer'),
            Session::adminId()
        );
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->back($request);
    }

    public function draft(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        QuestionService::saveAnswer((int) $params['id'], (string) $request->input('answer'), Session::adminId());
        Session::flash('success', 'Draft answer saved. It is not public until you publish it.');
        $this->back($request);
    }

    public function reject(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        QuestionService::reject((int) $params['id']);
        Session::flash('success', 'Question rejected.');
        $this->back($request);
    }

    public function unpublish(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        QuestionService::unpublish((int) $params['id']);
        Session::flash('success', 'Question removed from the product page.');
        $this->back($request);
    }

    public function destroy(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        QuestionService::delete((int) $params['id']);
        Session::flash('success', 'Question deleted.');
        $this->back($request);
    }

    public function updateSettings(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;

        foreach ([
            'questions_enabled' => $request->input('questions_enabled') === '1' ? '1' : '0',
            'notify_on_answer'  => $request->input('notify_on_answer') === '1' ? '1' : '0',
        ] as $key => $value) {
            Database::query(
                "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('questions',?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [$key, $value]
            );
        }
        Database::clearSettingsCache();

        Session::flash('success', 'Question settings saved.');
        Response::redirect(View::url('admin/questions'));
    }

    private function guard(Request $request): bool
    {
        if (Session::verifyCsrf($request->input('wk_csrf'))) return true;
        Session::flash('error', 'Session expired. Please try again.');
        Response::redirect(View::url('admin/questions'));
        return false;
    }

    private function back(Request $request): void
    {
        $from = (string) $request->input('from');
        $tab = in_array($from, ['pending', 'published', 'rejected'], true) ? $from : 'pending';
        Response::redirect(View::url('admin/questions') . '?status=' . $tab);
    }
}
