<?php

declare(strict_types=1);

namespace App\Events;

use App\Facades\Settings;
use Carbon\CarbonTimeZone;
use Illuminate\Console\Events\ArtisanStarting;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\OutputStyle;
use Illuminate\Contracts\Events\Dispatcher;
use Symfony\Component\Console\Question\Question;

/**
 * Prompts the user to configure a timezone if it isn't set.
 */
class ConfigureTimezone
{
    /**
     * @var string
     */
    private $environment;

    public function subscribe(Dispatcher $events)
    {
        $events->listen(ArtisanStarting::class, function (ArtisanStarting $event) {
            $this->handleArtisanStarting($event);
        });

        $events->listen(CommandStarting::class, function (CommandStarting $event) {
            $this->handleCommandStarting($event);
        });
    }

    public function handleArtisanStarting(ArtisanStarting $event): void
    {
        $this->environment = $event->artisan->getLaravel()->environment();
    }

    public function handleCommandStarting($event): void
    {
        if ($this->environment !== 'production' || Settings::get('timezone')) {
            return;
        }

        $output = new OutputStyle($event->input, $event->output);

        $timezone = $output->askQuestion(
            (new Question('What is your current timezone?', $this->guessTimezone()))
                ->setAutocompleterValues(CarbonTimeZone::listIdentifiers())
        );

        Settings::set('timezone', $timezone);
    }

    private function guessTimezone(): ?string
    {
        if (PHP_OS_FAMILY === 'win') {
            // TODO: something like `systeminfo | findstr ”Time Zone”`?  Maybe we can use tzutil?
            return null;
        }

        $offset = (int) exec('date +%z');
        $abbr = exec('date +%Z');

        if (! $offset || ! $abbr) {
            return null;
        }

        if ($timezone = timezone_name_from_abbr($abbr, (int) $offset)) {
            return $timezone;
        }

        return null;
    }
}
