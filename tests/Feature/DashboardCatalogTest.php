<?php

namespace Tests\Feature;

use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_sends_movies_and_series_from_the_database(): void
    {
        Title::create(['title' => 'Pulso', 'type' => 'Película', 'genre' => 'Thriller', 'year' => 2025, 'duration' => '1 h 43 min', 'maturity' => '16+', 'colors' => ['#e11d48', '#4a044e']]);
        Title::create(['title' => 'Señal Perdida', 'type' => 'Serie', 'genre' => 'Misterio', 'year' => 2025, 'seasons' => 1, 'maturity' => '13+']);

        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('titles', fn ($titles) => $titles->count() === 2
                && $titles->pluck('title')->all() === ['Pulso', 'Señal Perdida']);
    }

    public function test_dashboard_works_without_titles(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/dashboard')
            ->assertOk()
            ->assertViewHas('titles', fn ($titles) => $titles->isEmpty());
    }
}
