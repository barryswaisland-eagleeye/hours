<?php

declare(strict_types=1);

namespace App;

use Carbon\CarbonInterface;
use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * @property int                        $id
 * @property CarbonInterface            $started_at
 * @property CarbonInterface|null       $stopped_at
 * @property int                        $project_id
 * @property string                     $notes
 * @property CarbonInterval             $estimate
 * @property-read CarbonInterval        $elapsed
 * @property CarbonInterface|null       $created_at
 * @property CarbonInterface|null       $updated_at
 * @property-read \App\Project          $project
 * @property-read \App\Tag[]|Collection $tags
 * @method static Builder active()
 * @method static Builder between(CarbonInterface $start, CarbonInterface $end)
 * @method static Builder activeBetween(CarbonInterface $start)
 * @method static Builder forProject($project)
 * @method static Builder latestClosed()
 */
class Frame extends Model
{
    protected $fillable = [
        'started_at',
        'stopped_at',
        'project_id',
        'notes',
        'estimate',
    ];

    protected $dates = [
        'started_at',
        'stopped_at',
    ];

    protected $casts = [
        'estimate' => Casts\CarbonInterval::class,
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Scope a query to only include active frames.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->whereNull('stopped_at')
            ->orderByDesc('started_at');
    }

    /**
     * Scope a query to only include frames captured between the given dates.
     *
     * @param Builder         $query
     * @param CarbonInterface $start
     * @param CarbonInterface $end
     *
     * @return Builder
     */
    public function scopeBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $query
            ->whereDate('started_at', '>=', $start)
            ->whereDate('stopped_at', '<=', $end)
            ->whereNotNull('stopped_at');
    }

    /**
     * Scope a query to only include frames started between the given dates.
     *
     * @param Builder         $query
     * @param CarbonInterface $start
     * @param CarbonInterface $end
     *
     * @return Builder
     */
    public function scopeActiveBetween(Builder $query, CarbonInterface $start, CarbonInterface $end): Builder
    {
        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $query
            ->whereDate('started_at', '>=', $start)
            ->whereDate('started_at', '<=', $end)
            ->whereNull('stopped_at');
    }

    /**
     * Scope a query to only include frames for the given project(s).
     *
     * @param Builder                           $query
     * @param Project|string|Project[]|string[] $project
     *
     * @return Builder
     */
    public function scopeForProject(Builder $query, $project): Builder
    {
        $project = array_map(function ($project) {
            return $project instanceof Project ? $project->name : $project;
        }, Arr::wrap($project));

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $query
            ->whereHas('project', function (Builder $query) use ($project): Builder {
                return $query->whereIn('name', $project);
            });
    }

    /**
     * Scope a query to only include frames for the given tag(s).
     *
     * @param Builder                   $query
     * @param Tag|string|Tag[]|string[] $tag
     *
     * @return Builder
     */
    public function scopeForTag(Builder $query, $tag): Builder
    {
        $tag = array_map(function ($tag) {
            return $tag instanceof Tag ? $tag->name : $tag;
        }, Arr::wrap($tag));

        /* @noinspection PhpIncompatibleReturnTypeInspection */
        return $query
            ->whereHas('tags', function (Builder $query) use ($tag): Builder {
                return $query->whereIn('name', $tag);
            });
    }

    /**
     * Scope a query to only closed frames and sort by stopped_at.
     *
     * @param Builder $query
     *
     * @return Builder
     */
    public function scopeLatestClosed(Builder $query): Builder
    {
        return $query
            ->whereNotNull('stopped_at')
            ->orderByDesc('stopped_at');
    }

    /**
     * Start a new frame for the given project.
     *
     * @param Project|string       $project
     * @param CarbonInterface|null $startedAt
     *
     * @return Frame
     */
    public static function start($project, ?CarbonInterface $startedAt = null): self
    {
        if (! $project instanceof Project) {
            $project = Project::firstOrCreate([
                'name' => $project,
            ]);
        }

        return $project->frames()->create([
            'started_at' => $startedAt ?? Date::now(),
        ]);
    }

    /**
     * Stop the frame.
     *
     * @param CarbonInterface|null $stoppedAt
     *
     * @return bool
     */
    public function stop(CarbonInterface $stoppedAt = null): bool
    {
        $this->stopped_at = $stoppedAt ?? Date::now();

        return $this->save();
    }

    /**
     * Restart the current frame.  Because a closed frame cannot be re-opened
     * a new frame will be created with the attributes of the current frame.
     *
     * @param CarbonInterface|null $startedAt
     *
     * @return Frame
     */
    public function restart(?CarbonInterface $startedAt = null): self
    {
        $except = [
            'started_at',
            'stopped_at',
        ];

        return tap($this->replicate($except), function (self $frame) use ($startedAt) {
            $frame->started_at = $startedAt ?? Date::now();
            $frame->save();
            $frame->tags()->saveMany($this->tags);
        });
    }

    /**
     * Add a frame that was not tracked live.
     *
     * @param Project|string       $project
     * @param CarbonInterface      $startedAt
     * @param CarbonInterface|null $stoppedAt
     *
     * @return Frame
     */
    public static function add($project, CarbonInterface $startedAt, CarbonInterface $stoppedAt = null): self
    {
        return tap(static::start($project, $startedAt), function (self $frame) use ($stoppedAt) {
            $frame->stop($stoppedAt);
        });
    }

    /**
     * Add the given tags to the frame.
     *
     * @param Tag|string|Tag[]|string[] $tags
     *
     * @return Frame
     */
    public function addTags($tags): self
    {
        collect(Arr::wrap($tags))
            ->map(function ($tag) {
                return Tag::firstOrCreate(['name' => $tag instanceof Tag ? $tag->name : $tag]);
            })
            ->each(function (Tag $tag) {
                $this->tags()->save($tag);
            });

        return $this;
    }

    public function addNotes(?string $notes): self
    {
        $this->notes = $notes;

        $this->save();

        return $this;
    }

    /**
     * Returns the elapsed time between the start and stop of the frame.
     * If the frame is active the elapsed time between the start and now is returned.
     *
     * @return CarbonInterval
     */
    public function getElapsedAttribute(): CarbonInterval
    {
        return $this->started_at->diffAsCarbonInterval($this->stopped_at);
    }

    /**
     * Calculates the velocity for the given frame.
     *
     * @return float
     */
    public function getVelocityAttribute(): float
    {
        return round(
            $this->estimate->totalMicroseconds / $this->elapsed->totalMicroseconds,
            1
        );
    }
}
