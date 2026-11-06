<?php

declare(strict_types=1);

namespace Laravel\Passport;

use Closure;

class Passport
{
    public static $scopes = [];

    public static $clientModel = Client::class;

    public static $clientMetadataDocuments;

    /**
     * @var (Closure(array<string, mixed>, string): (string|null))|null
     */
    public static ?Closure $clientMetadataDocumentValidator = null;

    public static function tokensCan($scopes): void
    {
        self::$scopes = $scopes;
    }

    public static function clientMetadataDocuments(bool $enabled = true): void
    {
        static::$clientMetadataDocuments = ['enabled' => $enabled];
    }

    public static function clientMetadataDocumentsEnabled(): bool
    {
        return static::$clientMetadataDocuments['enabled'] ?? false;
    }

    /**
     * @param  (Closure(array<string, mixed>, string): (string|null))|null  $callback
     */
    public static function validateClientMetadataDocumentsUsing(?Closure $callback): void
    {
        static::$clientMetadataDocumentValidator = $callback;
    }

    public static function useClientModel(string $clientModel): void
    {
        static::$clientModel = $clientModel;
    }

    public static function client(): Client
    {
        return new static::$clientModel;
    }
}
