<?php
/**
 * Request body parsing and field validation.
 *
 * Collects every failure rather than bailing on the first one, so the client
 * can show all problems on a form at once.
 */

class Validator
{
    /** @var array<string, string> field => message */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Decode the JSON request body.
     * Exits with 400 if the body is not a JSON object.
     */
    public static function fromJsonBody(): self
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);

        if (!is_array($decoded)) {
            Response::error('Request body must be a JSON object', 400);
        }

        return new self($decoded);
    }

    private function raw(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    private function fail(string $field, string $message): void
    {
        // Keep the first message per field — it is the most specific.
        $this->errors[$field] ??= $message;
    }

    /** Trimmed string, must be non-empty and within length bounds. */
    public function string(string $field, string $label, int $min = 1, int $max = 255): ?string
    {
        $value = $this->raw($field);

        if (!is_string($value) || trim($value) === '') {
            $this->fail($field, "{$label} is required");
            return null;
        }

        $value = trim($value);
        $length = mb_strlen($value);

        if ($length < $min) {
            $this->fail($field, "{$label} must be at least {$min} characters");
            return null;
        }
        if ($length > $max) {
            $this->fail($field, "{$label} must be at most {$max} characters");
            return null;
        }

        return $value;
    }

    /** Normalized (lowercased, trimmed) email address. */
    public function email(string $field, string $label): ?string
    {
        $value = $this->string($field, $label, 3, 255);
        if ($value === null) {
            return null;
        }

        $value = mb_strtolower($value);
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->fail($field, "{$label} must be a valid email address");
            return null;
        }

        return $value;
    }

    /**
     * Password. Only a length floor is enforced — length beats composition
     * rules, and arbitrary character requirements push users toward
     * predictable substitutions.
     */
    public function password(string $field, string $label, int $min = 8): ?string
    {
        $value = $this->raw($field);

        if (!is_string($value) || $value === '') {
            $this->fail($field, "{$label} is required");
            return null;
        }

        if (strlen($value) < $min) {
            $this->fail($field, "{$label} must be at least {$min} characters");
            return null;
        }

        // bcrypt silently truncates beyond 72 bytes; reject instead of
        // accepting a password whose tail is ignored.
        if (strlen($value) > 72) {
            $this->fail($field, "{$label} must be at most 72 characters");
            return null;
        }

        return $value;
    }

    /** Optional free-text field. Returns null when absent or blank. */
    public function optionalString(string $field, string $label, int $max = 255): ?string
    {
        $value = $this->raw($field);

        if ($value === null || (is_string($value) && trim($value) === '')) {
            return null;
        }

        if (!is_string($value)) {
            $this->fail($field, "{$label} must be text");
            return null;
        }

        $value = trim($value);
        if (mb_strlen($value) > $max) {
            $this->fail($field, "{$label} must be at most {$max} characters");
            return null;
        }

        return $value;
    }

    /** Value must be one of $allowed. */
    public function inList(string $field, string $label, array $allowed): ?string
    {
        $value = $this->raw($field);

        if (!is_string($value) || $value === '') {
            $this->fail($field, "{$label} is required");
            return null;
        }

        if (!in_array($value, $allowed, true)) {
            $this->fail($field, "{$label} is not a valid option");
            return null;
        }

        return $value;
    }

    /**
     * Toggle that may be either on or off (unlike accepted(), which requires
     * true). Accepts real booleans plus the common JSON/form spellings.
     */
    public function boolean(string $field, string $label): ?bool
    {
        $value = $this->raw($field);

        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }
        if ($value === false || $value === 0 || $value === '0' || $value === 'false') {
            return false;
        }

        $this->fail($field, "{$label} must be true or false");
        return null;
    }

    /** Checkbox that must be affirmatively true (e.g. privacy consent). */
    public function accepted(string $field, string $label): bool
    {
        $value = $this->raw($field);

        if ($value !== true && $value !== 1 && $value !== '1' && $value !== 'true') {
            $this->fail($field, "{$label} must be accepted");
            return false;
        }

        return true;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /** @return array<string, string> */
    public function errors(): array
    {
        return $this->errors;
    }

    /**
     * Terminate with 422 and the field errors if anything failed.
     * Handlers call this once after collecting all fields.
     */
    public function stopIfInvalid(): void
    {
        if (!$this->hasErrors()) {
            return;
        }

        http_response_code(422);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Please correct the highlighted fields',
            'fields' => $this->errors,
        ]);
        exit;
    }
}
