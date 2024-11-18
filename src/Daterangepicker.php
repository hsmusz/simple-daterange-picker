<?php

namespace Rpj\Daterangepicker;

use Carbon\Carbon;
use Exception;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Rpj\Daterangepicker\DateHelper as Helper;

class Daterangepicker extends Filter
{
    /**
     * The filter's component.
     *
     * @var string
     */
    public $component = 'daterangepicker';

    private Carbon|null $maxDate = null;
    private Carbon|null $minDate = null;
    private array|null $ranges = null;

    public function __construct(
        private string $label,
        private string $column,
        private string $default = Helper::ALL,
    ) {
        //Often date range components use as default date the past dates
        $this->maxDate = Carbon::today();
    }

    /**
     * Apply the filter to the given query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param mixed $value
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(NovaRequest $request, $query, $value): Builder
    {
        [$start, $end] = Helper::getParsedDatesGroupedRanges($value);

        if ($start && $end) {
            return $query
                ->whereBetween($this->column, [$start, $end]);

        }

        return $query;
    }

    /**
     * Set the default options for the filter.
     *
     * @return array|mixed
     */
    public function default(): string|null
    {
        [$start, $end] = Helper::getParsedDatesGroupedRanges($this->default);

        if ($start && $end) {
            return $start->format('Y-m-d') . ' to ' . $end->format('Y-m-d');
        }

        return null;
    }

    /**
     * Convert the filter to its JSON representation.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return array_merge(parent::jsonSerialize(), [
            'minDate' => $this?->minDate?->format('Y-m-d'),
            'maxDate' => $this?->maxDate?->format('Y-m-d'),
        ]);
    }

    /**
     * Get the key for the filter.
     *
     * @return string
     */
    public function key()
    {
        return $this->column;
    }

    /**
     * Get the displayable name of the filter.
     *
     * @return string
     */
    public function name()
    {
        return $this->label;
    }

    /**
     * Get the filter's available options.
     *
     * @return array
     */
    public function options(NovaRequest $request): array|null
    {
        if (!$this->ranges) {
            $this->setRanges(Helper::defaultRanges());
        }

        return $this->ranges;
    }

    public function setMaxDate(Carbon $maxDate): self
    {
        $this->maxDate = $maxDate;

        if ($this->minDate && $this->maxDate->lt($this->minDate)) {
            throw new Exception('Date range picker: maxDate must be greater or equals than minDate.');
        }

        return $this;
    }

    public function setMinDate(Carbon $minDate): self
    {
        $this->minDate = $minDate;

        if ($this->maxDate && $this->minDate->gt($this->maxDate)) {
            throw new Exception('Date range picker: minDate must be lower or equals than maxDate.');
        }

        return $this;
    }

    /**
     * @param Carbon[] $periods
     */
    public function setRanges(array $ranges): self
    {
        $result = collect($ranges)->mapWithKeys(function (array $item, string $key) {
            return [
                $key => (collect($item)->map(function (Carbon $date) {
                    return $date->format('Y-m-d');
                })),
            ];
        })->toArray();

        $this->ranges = $result;

        return $this;
    }
}
