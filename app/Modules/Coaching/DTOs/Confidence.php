<?php

namespace App\Modules\Coaching\DTOs;

/**
 * Confidence level attached to each coaching insight.
 *
 * Phase 1 derives confidence from how strong the underlying signal is.
 * A later phase can scale confidence by an assistant's quality once a
 * staff hiring layer lands.
 */
enum Confidence: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';

    public function translationKey(): string
    {
        return 'coaching.confidence_'.$this->value;
    }
}
