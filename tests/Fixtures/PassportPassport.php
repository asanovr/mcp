<?php

declare(strict_types=1);

namespace Laravel\Passport;

class Passport
{
    public static $scopes = [];

    public static $clientModel = Client::class;

    public static $clientMetadataDocuments;

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

    public static function useClientModel(string $clientModel): void
    {
        static::$clientModel = $clientModel;
    }

    public static function client(): Client
    {
        return new static::$clientModel;
    }
}
