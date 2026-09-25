<?php

namespace App\Core;

/**
 * Validator — server-side validation. Client-side rules mirror these,
 * they never replace them.
 *
 *   $v = Validator::make($data, ['email' => 'required|email']);
 *   if ($v->fails()) { $v->errors(); $v->firstMessage(); }
 */
class Validator
{
    private $data;
    private $rules;
    private $errors = [];
    private $labels = [];

    private function __construct(array $data, array $rules, array $labels = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->labels = $labels;
        $this->run();
    }

    public static function make(array $data, array $rules, array $labels = [])
    {
        return new self($data, $rules, $labels);
    }

    public function fails()
    {
        return !empty($this->errors);
    }

    public function passes()
    {
        return empty($this->errors);
    }

    public function errors()
    {
        return $this->errors;
    }

    public function firstMessage($default = 'Please correct the highlighted fields.')
    {
        foreach ($this->errors as $messages) {
            if (!empty($messages[0])) {
                return $messages[0];
            }
        }
        return $default;
    }

    private function label($field)
    {
        return isset($this->labels[$field])
            ? $this->labels[$field]
            : ucwords(str_replace(['_', '-'], ' ', $field));
    }

    private function value($field)
    {
        return array_key_exists($field, $this->data) ? $this->data[$field] : null;
    }

    private function addError($field, $message)
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }
        $this->errors[$field][] = $message;
    }

    private function run()
    {
        foreach ($this->rules as $field => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', (string) $ruleString);
            $value = $this->value($field);
            $isRequired = in_array('required', $rules, true);

            // Optional fields are skipped when empty (except 'required').
            if (!$isRequired && ($value === null || $value === '')) {
                continue;
            }

            foreach ($rules as $rule) {
                $param = null;
                if (strpos($rule, ':') !== false) {
                    list($rule, $param) = explode(':', $rule, 2);
                }
                $this->applyRule($field, $value, $rule, $param);
            }
        }
    }

    private function applyRule($field, $value, $rule, $param)
    {
        $label = $this->label($field);
        $text = is_scalar($value) ? (string) $value : '';

        switch ($rule) {
            case 'required':
                if ($value === null
                    || (is_string($value) && trim($value) === '')
                    || (is_array($value) && !$value)) {
                    $this->addError($field, $label . ' is required.');
                }
                break;

            case 'email':
                if (!filter_var($text, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, $label . ' must be a valid email address.');
                }
                break;

            case 'min':
                if (mb_strlen($text) < (int) $param) {
                    $this->addError($field, $label . ' must be at least ' . (int) $param . ' characters.');
                }
                break;

            case 'max':
                if (mb_strlen($text) > (int) $param) {
                    $this->addError($field, $label . ' may not exceed ' . (int) $param . ' characters.');
                }
                break;

            case 'integer':
                if (!preg_match('/^-?\d+$/', $text)) {
                    $this->addError($field, $label . ' must be a whole number.');
                }
                break;

            case 'numeric':
                if (!is_numeric($text)) {
                    $this->addError($field, $label . ' must be a number.');
                }
                break;

            case 'min_value':
                if ((float) $text < (float) $param) {
                    $this->addError($field, $label . ' must be at least ' . $param . '.');
                }
                break;

            case 'max_value':
                if ((float) $text > (float) $param) {
                    $this->addError($field, $label . ' must not exceed ' . $param . '.');
                }
                break;

            case 'in':
                if (!in_array($text, explode(',', (string) $param), true)) {
                    $this->addError($field, $label . ' is not a valid option.');
                }
                break;

            case 'same':
                if ($text !== (string) $this->value($param)) {
                    $this->addError($field, $label . ' does not match ' . $this->label($param) . '.');
                }
                break;

            case 'different':
                if (array_key_exists($param, $this->data) && $text === (string) $this->data[$param]) {
                    $this->addError($field, $label . ' must be different from ' . $this->label($param) . '.');
                }
                break;

            case 'cnic':
                if (strlen(preg_replace('/[^0-9]/', '', $text)) !== 13) {
                    $this->addError($field, 'CNIC must contain exactly 13 digits (12345-1234567-1).');
                }
                break;

            case 'phone':
                $digits = preg_replace('/[^0-9]/', '', $text);
                if ($digits !== '' && (strlen($digits) < 10 || strlen($digits) > 15)) {
                    $this->addError($field, $label . ' must be 10 to 15 digits.');
                }
                break;

            case 'password':
                if (mb_strlen($text) < 8) {
                    $this->addError($field, 'Password must be at least 8 characters.');
                } elseif (!preg_match('/[A-Za-z]/', $text) || !preg_match('/\d/', $text)) {
                    $this->addError($field, 'Password must contain both letters and numbers.');
                }
                break;

            case 'date':
                if (strtotime($text) === false) {
                    $this->addError($field, $label . ' must be a valid date.');
                }
                break;

            case 'datetime':
                if ($text !== '' && strtotime(str_replace('T', ' ', $text)) === false) {
                    $this->addError($field, $label . ' must be a valid date and time.');
                }
                break;

            case 'url':
                if ($text !== '' && !filter_var($text, FILTER_VALIDATE_URL)) {
                    $this->addError($field, $label . ' must be a valid URL.');
                }
                break;

            case 'unique':
                $this->checkDatabaseRule($field, $text, $param, true, $label);
                break;

            case 'exists':
                $this->checkDatabaseRule($field, $text, $param, false, $label);
                break;

            default:
                break;
        }
    }

    /** Shared implementation for unique / exists. param = "table,column[,ignoreId]". */
    private function checkDatabaseRule($field, $value, $param, $mustBeUnique, $label)
    {
        $parts = explode(',', (string) $param);
        if (count($parts) < 2) {
            return;
        }
        $table = preg_replace('/[^a-z0-9_]/i', '', trim($parts[0]));
        $column = preg_replace('/[^a-z0-9_]/i', '', trim($parts[1]));
        if ($table === '' || $column === '') {
            return;
        }

        $sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE `' . $column . '` = ?';
        $params = [$value];

        if (isset($parts[2]) && (int) $parts[2] > 0) {
            $sql .= ' AND id <> ?';
            $params[] = (int) $parts[2];
        }

        $count = (int) Database::value($sql, $params);

        if ($mustBeUnique && $count > 0) {
            $this->addError($field, $label . ' is already in use.');
        }
        if (!$mustBeUnique && $count === 0) {
            $this->addError($field, $label . ' is invalid.');
        }
    }
}

