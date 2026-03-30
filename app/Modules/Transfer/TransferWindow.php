<?php

namespace App\Modules\Transfer;

use App\Modules\Transfer\Enums\TransferWindowType;
use Carbon\Carbon;

final readonly class TransferWindow
{
    public function __construct(
        public Carbon $date,
    ) {}

    public function type(): ?TransferWindowType
    {
        return TransferWindowType::fromDate($this->date);
    }

    public function isOpen(): bool
    {
        return $this->type() !== null;
    }

    public function isSummer(): bool
    {
        return $this->type() === TransferWindowType::SUMMER;
    }

    public function isWinter(): bool
    {
        return $this->type() === TransferWindowType::WINTER;
    }

    /**
     * True if within the first 7 days of a window month.
     */
    public function isWindowStart(): bool
    {
        return $this->isOpen() && $this->date->day <= 7;
    }

    /**
     * January through May — players in their last contract year can be approached.
     */
    public function isPreContractPeriod(): bool
    {
        $month = $this->date->month;

        return $month >= 1 && $month <= 5;
    }

    public function displayName(): ?string
    {
        return $this->type()?->label();
    }

    public function nextWindowType(): TransferWindowType
    {
        $month = $this->date->month;

        return ($month >= 1 && $month <= 6)
            ? TransferWindowType::SUMMER
            : TransferWindowType::WINTER;
    }

    public function nextWindowDisplayName(): string
    {
        return $this->nextWindowType()->label();
    }

    /**
     * If a window is open, returns the first day of the month after it closes.
     */
    public function closingBoundaryDate(): ?Carbon
    {
        $type = $this->type();
        if (! $type) {
            return null;
        }

        return Carbon::createFromDate($this->date->year, $type->closeMonth(), 1);
    }

    /**
     * If no window is open, returns the date the next window opens.
     */
    public function openingBoundaryDate(): ?Carbon
    {
        if ($this->isOpen()) {
            return null;
        }

        $month = $this->date->month;
        $year = $this->date->year;

        if ($month >= 2 && $month <= 6) {
            return Carbon::createFromDate($year, 7, 1);
        }

        if ($month >= 9 && $month <= 12) {
            return Carbon::createFromDate($year + 1, 1, 1);
        }

        return null;
    }
}
