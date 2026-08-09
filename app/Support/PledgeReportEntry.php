<?php

namespace App\Support;

use App\Models\Pledge;
use Illuminate\Support\Collection;

/**
 * Adapts a guest-less Pledge to the shape the pledge report expects.
 *
 * The report groups by guest, but a pledge does not need a guest record:
 * imported ledgers and pledges taken by name only have none. Rather than
 * duplicating the template for two sources, this exposes the handful of
 * members the view uses so both render through the same markup.
 */
class PledgeReportEntry
{
    public function __construct(public Pledge $pledge) {}

    public function fullName(): string
    {
        return $this->pledge->pledger_name;
    }

    /** The report shows party size for guests; a bare pledge has none. */
    public function __get(string $name): mixed
    {
        if ($name === 'party_count') {
            return null;
        }

        if ($name === 'pledges') {
            return collect([$this->pledge]);
        }

        return $this->pledge->{$name};
    }

    /**
     * Mirrors the guest relation the view calls as $entry->pledges()->sum(...).
     */
    public function pledges(): PledgeReportEntryQuery
    {
        return new PledgeReportEntryQuery(collect([$this->pledge]));
    }

    public function contributions(): PledgeReportEntryQuery
    {
        return new PledgeReportEntryQuery($this->pledge->contributions);
    }

    public function totalContributed(): float
    {
        return (float) $this->pledge->contributions->sum('amount');
    }

    public function balance(): float
    {
        return (float) $this->pledge->amount - $this->totalContributed();
    }
}

/**
 * The tiny slice of a query builder the report actually uses.
 */
class PledgeReportEntryQuery
{
    public function __construct(private Collection $items) {}

    public function sum(string $column): float
    {
        return (float) $this->items->sum($column);
    }

    public function count(): int
    {
        return $this->items->count();
    }

    /**
     * Accepts eager-loading calls and ignores them.
     *
     * The view chains ->with('contributions')->get() on what it assumes is a
     * relation. Here the models are already in memory, so there is nothing to
     * load; this exists purely so the same template works for both sources.
     */
    public function with(string|array ...$relations): self
    {
        return $this;
    }

    public function get(): Collection
    {
        return $this->items;
    }
}
