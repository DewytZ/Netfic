<?php

namespace Tests\Feature;

use App\Models\Title;
use Database\Seeders\TitleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_the_sample_catalog(): void
    {
        $this->seed(TitleSeeder::class);

        $this->assertSame(20, Title::where('type', 'Película')->count());
        $this->assertSame(10, Title::where('type', 'Serie')->count());
        $this->assertSame(0, Title::where('type', 'Película')->whereNull('duration')->count());
        $this->assertSame(0, Title::where('type', 'Serie')->whereNull('seasons')->count());
    }

    public function test_seeder_can_run_again_without_duplicating_or_deleting_titles(): void
    {
        $addedFromThePanel = Title::create([
            'title' => 'Título del admin',
            'type' => 'Película',
            'genre' => 'Drama',
            'year' => 2025,
            'duration' => '1 h 30 min',
            'maturity' => '13+',
        ]);

        $this->seed(TitleSeeder::class);
        $this->seed(TitleSeeder::class);

        $this->assertSame(31, Title::count());
        $this->assertModelExists($addedFromThePanel);
    }
}
