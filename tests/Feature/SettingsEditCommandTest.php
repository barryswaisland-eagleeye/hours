<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Settings\Repository;
use Tests\TestCase;

class SettingsEditCommandTest extends TestCase
{
    public function testSettingsEdit()
    {
        $this
            ->artisan('settings:edit time_format \'g:i\'')
            ->assertExitCode(0)
            ->expectsOutput('Updated time_format to g:i');

        $this->assertEquals('g:i', $this->app->make(Repository::class)->get('time_format'));
    }
}
