<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\HandlesBrazilianDateTimes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

final class Client extends Model
{
    use HasFactory;
    use HandlesBrazilianDateTimes;

    private const NAME_MAX_LENGTH = 150;
    private const EMAIL_MAX_LENGTH = 150;
    private const DOCUMENT_MAX_LENGTH = 20;

    protected $table = 'clients';

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'document',
    ];

    /**
     * Get the model attribute casts.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Return the transactions associated with this client.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Mutate and validate the client name before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setNameAttribute(mixed $value): void
    {
        $name = $this->normalizeString($value);

        if ($name === null) {
            throw new InvalidArgumentException('Client name is required.');
        }

        if (mb_strlen($name) > self::NAME_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Client name may not be greater than %d characters.', self::NAME_MAX_LENGTH)
            );
        }

        $this->attributes['name'] = $name;
    }

    /**
     * Mutate and validate the client email before persistence.
     *
     * @throws InvalidArgumentException
     */
    public function setEmailAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['email'] = null;

            return;
        }

        $email = $this->normalizeEmail($value);

        if ($email === null) {
            throw new InvalidArgumentException('Invalid client email.');
        }

        if (mb_strlen($email) > self::EMAIL_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Client email may not be greater than %d characters.', self::EMAIL_MAX_LENGTH)
            );
        }

        $this->attributes['email'] = $email;
    }

    /**
     * Mutate and validate the client document before persistence.
     */
    public function setDocumentAttribute(mixed $value): void
    {
        if ($value === null || $value === '') {
            $this->attributes['document'] = null;

            return;
        }

        $document = $this->normalizeDocument($value);

        if ($document === null) {
            throw new InvalidArgumentException('Invalid client document.');
        }

        if (mb_strlen($document) > self::DOCUMENT_MAX_LENGTH) {
            throw new InvalidArgumentException(
                sprintf('Client document may not be greater than %d characters.', self::DOCUMENT_MAX_LENGTH)
            );
        }

        $this->attributes['document'] = $document;
    }

    /**
     * Scope clients by name, email, or document.
     */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        $search = trim($term);

        return $query->where(function (Builder $builder) use ($search): void {
            $builder
                ->where('name', 'like', '%' . $search . '%')
                ->orWhere('email', 'like', '%' . $search . '%')
                ->orWhere('document', 'like', '%' . $search . '%');
        });
    }

    /**
     * Return the masked client document.
     */
    public function maskedDocument(): ?string
    {
        if (! is_string($this->document) || trim($this->document) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $this->document);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        $length = strlen($digits);

        if ($length <= 4) {
            return str_repeat('*', $length);
        }

        return str_repeat('*', $length - 4) . substr($digits, -4);
    }

    /**
     * Normalize a generic string input.
     */
    private function normalizeString(mixed $value): ?string
    {
        if ($value === null || ! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        $normalized = preg_replace('/\s+/u', ' ', $normalized);

        if (! is_string($normalized) || $normalized === '') {
            return null;
        }

        return $normalized;
    }

    /**
     * Normalize and validate an email input.
     */
    private function normalizeEmail(mixed $value): ?string
    {
        $email = $this->normalizeString($value);

        if ($email === null) {
            return null;
        }

        $sanitized = filter_var($email, FILTER_SANITIZE_EMAIL);

        if (! is_string($sanitized) || $sanitized === '') {
            return null;
        }

        return filter_var($sanitized, FILTER_VALIDATE_EMAIL)
            ? mb_strtolower($sanitized)
            : null;
    }

    /**
     * Normalize a CPF/CNPJ-like document input by keeping only digits.
     */
    private function normalizeDocument(mixed $value): ?string
    {
        $document = $this->normalizeString($value);

        if ($document === null) {
            return null;
        }

        $digits = preg_replace('/\D+/u', '', $document);

        if (! is_string($digits) || $digits === '') {
            return null;
        }

        return $digits;
    }
}