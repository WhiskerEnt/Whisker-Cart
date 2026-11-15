<?php
namespace App\Controllers\Admin;

use App\Services\AccountFieldService as AccountFields;
use Core\{Request, View, Database, Response, Session};

/**
 * Signup and sign-in: which fields the registration form asks for, which of
 * them are required, the shop's own extra fields, and whether customers may
 * sign in with a username.
 */
class AccountSettingsController
{
    private const BACK = 'admin/accounts';

    public function index(Request $request, array $params = []): void
    {
        $states = [];
        foreach (AccountFields::builtInKeys() as $key) {
            $states[$key] = AccountFields::state($key);
        }

        View::render('admin/accounts/index', [
            'pageTitle'      => 'Signup & Sign-in',
            'states'         => $states,
            'usernameLogin'  => Database::setting('accounts', 'login_with_username') === '1',
            'fields'         => AccountFields::allCustom(),
            'answerCounts'   => self::answerCounts(),
        ], 'admin/layouts/main');
    }

    public function updateSettings(Request $request, array $params = []): void
    {
        if (!$this->csrfOk($request)) return;

        $allowed = [AccountFields::OFF, AccountFields::OPTIONAL, AccountFields::REQUIRED];
        $states = [];
        foreach (AccountFields::builtInKeys() as $key) {
            $value = (string) $request->input('field_' . $key);
            $states[$key] = in_array($value, $allowed, true) ? $value : AccountFields::state($key);
        }

        $usernameLogin = $request->input('login_with_username') === '1';
        $note = '';
        // Signing in with a username needs somewhere to have chosen one.
        if ($usernameLogin && $states['username'] === AccountFields::OFF) {
            $states['username'] = AccountFields::OPTIONAL;
            $note = ' The username field is now on the signup form, since customers need it to sign in with one.';
        }

        foreach ($states as $key => $value) {
            self::put('field_' . $key, $value);
        }
        self::put('login_with_username', $usernameLogin ? '1' : '0');

        Session::flash('success', 'Signup and sign-in settings saved.' . $note);
        Response::redirect(View::url(self::BACK));
    }

    public function storeField(Request $request, array $params = []): void
    {
        if (!$this->csrfOk($request)) return;

        $row = self::fieldFromRequest($request);
        if (is_string($row)) { $this->fail($row); return; }

        // The key is what the form submits under. It is made once from the
        // label and never changes, so renaming a field keeps its answers.
        $base = AccountFields::keyFrom($row['label']);
        $key = $base;
        for ($n = 2; Database::fetchValue('SELECT COUNT(*) FROM wk_customer_fields WHERE field_key=?', [$key]); $n++) {
            $key = substr($base, 0, 36) . '_' . $n;
        }
        $row['field_key'] = $key;
        $row['sort_order'] = (int) Database::fetchValue('SELECT COALESCE(MAX(sort_order), 0) + 10 FROM wk_customer_fields');

        Database::insert('wk_customer_fields', $row);
        Session::flash('success', '"' . $row['label'] . '" added to the signup form.');
        Response::redirect(View::url(self::BACK));
    }

    public function updateField(Request $request, array $params = []): void
    {
        if (!$this->csrfOk($request)) return;

        $id = (int) ($params['id'] ?? 0);
        if (!Database::fetchValue('SELECT COUNT(*) FROM wk_customer_fields WHERE id=?', [$id])) {
            $this->fail('That field no longer exists.'); return;
        }

        $row = self::fieldFromRequest($request);
        if (is_string($row)) { $this->fail($row); return; }
        $row['sort_order'] = (int) $request->input('sort_order');

        Database::update('wk_customer_fields', $row, 'id=?', [$id]);
        Session::flash('success', '"' . $row['label'] . '" saved.');
        Response::redirect(View::url(self::BACK));
    }

    public function deleteField(Request $request, array $params = []): void
    {
        if (!$this->csrfOk($request)) return;

        $id = (int) ($params['id'] ?? 0);
        $label = (string) Database::fetchValue('SELECT label FROM wk_customer_fields WHERE id=?', [$id]);
        // Answers go with the field, by the foreign key.
        Database::delete('wk_customer_fields', 'id=?', [$id]);

        Session::flash('success', $label !== '' ? '"' . $label . '" removed, with its answers.' : 'Field removed.');
        Response::redirect(View::url(self::BACK));
    }

    /**
     * Read and check a field definition from the form.
     *
     * @return array|string the row to write, or what is wrong with it
     */
    private static function fieldFromRequest(Request $request)
    {
        $label = trim((string) $request->input('label'));
        if ($label === '') return 'Give the field a label.';
        if (mb_strlen($label) > 80) return 'Keep the label under 80 characters.';

        $type = (string) $request->input('type');
        if (!in_array($type, AccountFields::TYPES, true)) return 'Choose a field type.';

        $options = trim((string) $request->input('options'));
        if ($type === 'select') {
            if (count(AccountFields::choices(['options' => $options])) < 2) {
                return 'A dropdown needs at least two choices, one per line.';
            }
        } else {
            $options = '';
        }

        return [
            'label'            => $label,
            'type'             => $type,
            'options'          => $options !== '' ? $options : null,
            'placeholder'      => mb_substr(trim((string) $request->input('placeholder')), 0, 120) ?: null,
            'help_text'        => mb_substr(trim((string) $request->input('help_text')), 0, 255) ?: null,
            'is_required'      => $request->input('is_required') === '1' ? 1 : 0,
            'show_on_register' => $request->input('show_on_register') === '1' ? 1 : 0,
            'is_active'        => $request->input('is_active') === '0' ? 0 : 1,
        ];
    }

    /** How many customers have answered each field, so a delete is an informed one. */
    private static function answerCounts(): array
    {
        try {
            $rows = Database::fetchAll(
                "SELECT field_id, COUNT(*) AS n FROM wk_customer_field_values WHERE value <> '' GROUP BY field_id"
            );
        } catch (\Throwable $e) {
            return [];
        }
        return array_column($rows, 'n', 'field_id');
    }

    private static function put(string $key, string $value): void
    {
        Database::query(
            "INSERT INTO wk_settings (setting_group, setting_key, setting_value) VALUES ('accounts', ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
            [$key, $value]
        );
    }

    private function csrfOk(Request $request): bool
    {
        if (Session::verifyCsrf($request->input('wk_csrf'))) return true;
        $this->fail('Session expired.');
        return false;
    }

    private function fail(string $message): void
    {
        Session::flash('error', $message);
        Response::redirect(View::url(self::BACK));
    }
}
