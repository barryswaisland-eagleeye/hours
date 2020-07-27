<?php

declare(strict_types=1);

namespace Tests\Unit\Report;

use App\Facades\Settings;
use App\Frame;
use App\Project;
use App\Report;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Date;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class JsonRendererTest extends TestCase
{
    public function testRender()
    {
        Settings::set('timezone', 'America/New_York');

        $project = factory(Project::class)->create(['name' => 'blog']);

        factory(Frame::class)->create([
            'project_id' => $project->id,
            'notes' => 'Starting work on the new theme',
            'started_at' => Date::parse('2019-05-04 12:00 PM', 'America/New_York')->utc(),
            'stopped_at' => Date::parse('2019-05-04 12:30 PM', 'America/New_York')->utc(),
            'estimate' => CarbonInterval::create(0)->add('minutes', 30),
        ]);

        factory(Frame::class)->create([
            'project_id' => $project->id,
            'notes' => 'Adding the mailing list signup component',
            'started_at' => Date::parse('2019-05-05 12:00 PM', 'America/New_York')->utc(),
            'stopped_at' => Date::parse('2019-05-05 1:30 PM', 'America/New_York')->utc(),
            'estimate' => CarbonInterval::create(0)->add('minutes', 30),
        ]);

        $output = new BufferedOutput();

        Report::build()
            ->from(Date::create(2019, 05, 03))
            ->to(Date::create(2019, 05, 05))
            ->create()
            ->render($output, 'json');

        $expected = [
            'date_range' => [
                'from' => 'May 2, 2019 8:00 pm',
                'to' => 'May 4, 2019 8:00 pm',
            ],
            'frames' => [
                [
                    'Project' => 'blog',
                    'Tags' => '',
                    'Notes' => 'Starting work on the new theme',
                    'Date' => 'May 4, 2019',
                    'Start' => '12:00 pm',
                    'End' => '12:30 pm',
                    'Elapsed' => '0:30',
                    'Estimate' => '0:30',
                    'Velocity' => 1,
                ],
                [
                    'Project' => 'blog',
                    'Tags' => '',
                    'Notes' => 'Adding the mailing list signup component',
                    'Date' => 'May 5, 2019',
                    'Start' => '12:00 pm',
                    'End' => '1:30 pm',
                    'Elapsed' => '1:30',
                    'Estimate' => '0:30',
                    'Velocity' => 0.3,
                ],
            ],
            'totals' => [
                'Elapsed' => '2:00',
                'Estimate' => '1:00',
                'Velocity' => 0.85,
            ],
        ];

        $this->assertSame($expected, json_decode($output->fetch(), true));
    }
}
