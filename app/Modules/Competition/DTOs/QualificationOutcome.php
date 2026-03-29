<?php

namespace App\Modules\Competition\DTOs;

readonly class QualificationOutcome
{
    private function __construct(
        public QualificationStatus $status,
        public string $translationKey,
        public array $translationParams = [],
    ) {}

    public static function advanced(string $translationKey, array $params = []): self
    {
        return new self(QualificationStatus::Advanced, $translationKey, $params);
    }

    public static function playoff(string $translationKey, array $params = []): self
    {
        return new self(QualificationStatus::Playoff, $translationKey, $params);
    }

    public static function eliminated(string $translationKey, array $params = []): self
    {
        return new self(QualificationStatus::Eliminated, $translationKey, $params);
    }

    public function isElimination(): bool
    {
        return $this->status === QualificationStatus::Eliminated;
    }
}
