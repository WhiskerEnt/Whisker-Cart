<?php
namespace App\Controllers\Admin;

use App\Services\ReviewService;
use Core\{Request, View, Database, Response, Session};

class ReviewController
{
    public function index(Request $request, array $params = []): void
    {
        $filter = (string) $request->input('status');
        if (!in_array($filter, ['pending', 'approved', 'rejected'], true)) $filter = 'pending';

        $reviews = [];
        $counts  = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
        try {
            $reviews = Database::fetchAll(
                "SELECT r.*, p.name AS product_name, p.slug AS product_slug, o.order_number
                   FROM wk_reviews r
                   LEFT JOIN wk_products p ON p.id = r.product_id
                   LEFT JOIN wk_orders o ON o.id = r.order_id
                  WHERE r.status = ?
                  ORDER BY r.created_at DESC
                  LIMIT 200",
                [$filter]
            );
            foreach (Database::fetchAll("SELECT status, COUNT(*) AS n FROM wk_reviews GROUP BY status") as $row) {
                $counts[$row['status']] = (int) $row['n'];
            }
        } catch (\Exception $e) {
            // Table arrives with a migration; an un-migrated store still loads.
        }

        $settings = [];
        foreach (Database::fetchAll("SELECT setting_key, setting_value FROM wk_settings WHERE setting_group='reviews'") as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        View::render('admin/reviews/index', [
            'pageTitle' => 'Reviews',
            'reviews'   => $reviews,
            'counts'    => $counts,
            'filter'    => $filter,
            'settings'  => $settings,
        ], 'admin/layouts/main');
    }

    public function status(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        $status = (string) $request->input('status');
        if (!ReviewService::setStatus((int) $params['id'], $status)) {
            Session::flash('error', 'Could not update that review.');
        } else {
            Session::flash('success', 'Review ' . $status . '.');
        }
        $this->back($request);
    }

    public function reply(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        ReviewService::reply((int) $params['id'], (string) $request->input('reply'));
        Session::flash('success', 'Reply saved.');
        $this->back($request);
    }

    public function destroy(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;
        ReviewService::delete((int) $params['id']);
        Session::flash('success', 'Review deleted.');
        $this->back($request);
    }

    public function updateSettings(Request $request, array $params = []): void
    {
        if (!$this->guard($request)) return;

        $policy = (string) $request->input('review_policy');
        if (!in_array($policy, [ReviewService::POLICY_ANYONE, ReviewService::POLICY_PURCHASED, ReviewService::POLICY_RECEIVED], true)) {
            $policy = ReviewService::POLICY_PURCHASED;
        }

        $values = [
            'reviews_enabled' => $request->input('reviews_enabled') === '1' ? '1' : '0',
            'review_policy'   => $policy,
            'auto_approve'    => $request->input('auto_approve') === '1' ? '1' : '0',
            'show_on_cards'   => $request->input('show_on_cards') === '1' ? '1' : '0',
        ];
        foreach ($values as $key => $value) {
            Database::query(
                "INSERT INTO wk_settings (setting_group,setting_key,setting_value) VALUES('reviews',?,?)
                 ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)",
                [$key, $value]
            );
        }
        Database::clearSettingsCache();

        Session::flash('success', 'Review settings saved.');
        Response::redirect(View::url('admin/reviews'));
    }

    private function guard(Request $request): bool
    {
        if (Session::verifyCsrf($request->input('wk_csrf'))) return true;
        Session::flash('error', 'Session expired. Please try again.');
        Response::redirect(View::url('admin/reviews'));
        return false;
    }

    /** Return to the tab the action was taken from. */
    private function back(Request $request): void
    {
        $from = (string) $request->input('from');
        $tab = in_array($from, ['pending', 'approved', 'rejected'], true) ? $from : 'pending';
        Response::redirect(View::url('admin/reviews') . '?status=' . $tab);
    }
}
