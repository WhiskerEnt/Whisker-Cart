<?php
namespace App\Services;

use Core\Database;

/**
 * What a shop asks for when somebody signs up.
 *
 * One answer for the form, the validation and the profile screen, so a field
 * cannot be shown in one place and ignored in another. Built-in fields map to
 * columns on wk_customers; a shop's own fields live in wk_customer_fields and
 * their answers in wk_customer_field_values.
 *
 * Email and password are not listed: an account is reached by email and
 * proved by password, so neither is a shopkeeper's to switch off.
 */
final class AccountFieldService
{
    public const OFF = 'off';
    public const OPTIONAL = 'optional';
    public const REQUIRED = 'required';

    /** Built-in fields, in the order the form shows them. */
    private const BUILT_IN = [
        'first_name' => ['label' => 'First Name', 'default' => self::REQUIRED, 'placeholder' => 'John'],
        'last_name'  => ['label' => 'Last Name',  'default' => self::REQUIRED, 'placeholder' => 'Doe'],
        'username'   => ['label' => 'Username',   'default' => self::OFF,      'placeholder' => 'johndoe'],
        'phone'      => ['label' => 'Phone',      'default' => self::OPTIONAL, 'placeholder' => ''],
    ];

    public const TYPES = ['text', 'textarea', 'number', 'select', 'checkbox', 'date'];

    /** A username is a sign-in name, so it must not be mistakable for an email. */
    public const USERNAME_PATTERN = '/^[a-zA-Z0-9][a-zA-Z0-9._-]{2,39}$/';

    public static function builtInKeys(): array
    {
        return array_keys(self::BUILT_IN);
    }

    public static function label(string $key): string
    {
        return self::BUILT_IN[$key]['label'] ?? ucfirst(str_replace('_', ' ', $key));
    }

    public static function placeholder(string $key): string
    {
        return self::BUILT_IN[$key]['placeholder'] ?? '';
    }

    /** off, optional or required. Anything unrecognised falls back to the default. */
    public static function state(string $key): string
    {
        if (!isset(self::BUILT_IN[$key])) return self::OFF;

        $stored = Database::setting('accounts', 'field_' . $key);
        return in_array($stored, [self::OFF, self::OPTIONAL, self::REQUIRED], true)
            ? $stored
            : self::BUILT_IN[$key]['default'];
    }

    public static function isShown(string $key): bool
    {
        return self::state($key) !== self::OFF;
    }

    public static function isRequired(string $key): bool
    {
        return self::state($key) === self::REQUIRED;
    }

    /** Whether sign-in accepts a username as well as an email address. */
    public static function usernameLogin(): bool
    {
        return Database::setting('accounts', 'login_with_username') === '1'
            && self::isShown('username');
    }

    /**
     * A shop's own fields. $registerOnly limits it to the ones the signup
     * form asks for; the profile screen shows all of them.
     */
    public static function custom(bool $registerOnly = false): array
    {
        try {
            $rows = Database::fetchAll(
                'SELECT * FROM wk_customer_fields WHERE is_active = 1'
                . ($registerOnly ? ' AND show_on_register = 1' : '')
                . ' ORDER BY sort_order, id'
            );
        } catch (\Throwable $e) {
            // A shop that has not run the migration yet simply has none.
            return [];
        }
        return $rows;
    }

    /** Every custom field, including the ones switched off, for the admin. */
    public static function allCustom(): array
    {
        try {
            return Database::fetchAll('SELECT * FROM wk_customer_fields ORDER BY sort_order, id');
        } catch (\Throwable $e) {
            return [];
        }
    }

    /** A select's choices, one per line. */
    public static function choices(array $field): array
    {
        $raw = (string) ($field['options'] ?? '');
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', $raw) as $line) {
            $line = trim($line);
            if ($line !== '') $out[] = $line;
        }
        return $out;
    }

    /** What this customer has answered, keyed by field_key. */
    public static function valuesFor(int $customerId): array
    {
        try {
            $rows = Database::fetchAll(
                'SELECT f.field_key, v.value
                   FROM wk_customer_field_values v
                   JOIN wk_customer_fields f ON f.id = v.field_id
                  WHERE v.customer_id = ?',
                [$customerId]
            );
        } catch (\Throwable $e) {
            return [];
        }
        $out = [];
        foreach ($rows as $row) {
            $out[$row['field_key']] = $row['value'];
        }
        return $out;
    }

    /**
     * Check what was submitted for the shop's own fields.
     *
     * @return string|null the first problem, or null when everything is fine
     */
    public static function validateCustom(array $fields, array $input): ?string
    {
        foreach ($fields as $field) {
            $key   = $field['field_key'];
            $label = $field['label'];
            $value = trim((string) ($input[self::inputName($key)] ?? ''));

            if ($field['type'] === 'checkbox') {
                if ($field['is_required'] && $value === '') {
                    return $label . ' is required.';
                }
                continue;
            }

            if ($value === '') {
                if ($field['is_required']) return $label . ' is required.';
                continue;
            }

            if ($field['type'] === 'number' && !is_numeric($value)) {
                return $label . ' must be a number.';
            }
            if ($field['type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                return $label . ' must be a date.';
            }
            if ($field['type'] === 'select') {
                $choices = self::choices($field);
                if ($choices && !in_array($value, $choices, true)) {
                    return 'Choose one of the options for ' . $label . '.';
                }
            }
            if (mb_strlen($value) > 2000) {
                return $label . ' is too long.';
            }
        }
        return null;
    }

    /** Write this customer's answers, replacing whatever was there. */
    public static function saveCustom(int $customerId, array $fields, array $input): void
    {
        foreach ($fields as $field) {
            $name  = self::inputName($field['field_key']);
            $value = $field['type'] === 'checkbox'
                ? (isset($input[$name]) && $input[$name] !== '' ? '1' : '')
                : trim((string) ($input[$name] ?? ''));

            try {
                Database::query(
                    'INSERT INTO wk_customer_field_values (customer_id, field_id, value) VALUES (?, ?, ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)',
                    [$customerId, (int) $field['id'], $value]
                );
            } catch (\Throwable $e) {
                // A field deleted mid-submission is not worth failing a signup over.
            }
        }
    }

    /** The form input name for a shop's own field. */
    public static function inputName(string $fieldKey): string
    {
        return 'cf_' . $fieldKey;
    }

    /**
     * Turn a label into a key. Used when a shopkeeper adds a field, so they
     * never have to think about one.
     */
    public static function keyFrom(string $label): string
    {
        $key = strtolower($label);
        $key = preg_replace('/[^a-z0-9]+/', '_', $key);
        $key = trim((string) $key, '_');
        return substr($key, 0, 40) ?: 'field';
    }

    public static function usernameError(string $username): ?string
    {
        if (!preg_match(self::USERNAME_PATTERN, $username)) {
            return 'A username must be 3 to 40 characters: letters, numbers, dots, dashes and underscores, starting with a letter or number.';
        }
        return null;
    }
}
