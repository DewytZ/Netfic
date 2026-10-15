<?php

namespace Tests\Feature;

use App\Models\Title;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TitleCrudTest extends TestCase
{
    use RefreshDatabase;

    private function movieData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Pulso',
            'type' => 'Película',
            'genre' => 'Thriller',
            'year' => 2025,
            'duration' => '1 h 43 min',
            'maturity' => '16+',
            'colors' => ['#e11d48', '#4a044e'],
            'description' => null,
            'seasons' => null,
        ], $overrides);
    }

    private function seriesData(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Señal Perdida',
            'type' => 'Serie',
            'genre' => 'Misterio',
            'year' => 2025,
            'duration' => null,
            'maturity' => '13+',
            'colors' => null,
            'description' => null,
            'seasons' => 2,
        ], $overrides);
    }

    public function test_guests_can_create_titles(): void
    {
        $this->postJson('/api/titles', $this->movieData())->assertCreated();

        $this->assertDatabaseHas('titles', ['title' => 'Pulso']);
    }

    public function test_user_creates_a_movie(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/titles', $this->movieData())
            ->assertCreated()
            ->assertJsonPath('colors', ['#e11d48', '#4a044e']);

        $this->assertDatabaseHas('titles', ['title' => 'Pulso', 'duration' => '1 h 43 min', 'seasons' => null]);
    }

    public function test_user_creates_a_series_without_duration(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/titles', $this->seriesData())->assertCreated();

        $this->assertDatabaseHas('titles', ['title' => 'Señal Perdida', 'duration' => null, 'seasons' => 2]);
    }

    public function test_a_movie_requires_duration(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/titles', $this->movieData(['duration' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('duration');
    }

    public function test_a_series_requires_seasons(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/titles', $this->seriesData(['seasons' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('seasons');
    }

    public function test_year_must_be_between_1900_and_2099(): void
    {
        $this->actingAs(User::factory()->create());

        $this->postJson('/api/titles', $this->movieData(['year' => 1800]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('year');
    }

    public function test_user_updates_a_title(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::create($this->movieData());

        $this->putJson("/api/titles/{$title->id}", $this->movieData(['title' => 'Pulso Editado', 'genre' => 'Drama']))
            ->assertOk()
            ->assertJsonPath('title', 'Pulso Editado');

        $this->assertDatabaseHas('titles', ['id' => $title->id, 'title' => 'Pulso Editado', 'genre' => 'Drama']);
        $this->assertDatabaseCount('titles', 1); // editar no crea un duplicado
    }

    public function test_changing_to_a_series_clears_the_duration(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::create($this->movieData());

        $this->putJson("/api/titles/{$title->id}", $this->seriesData(['duration' => '1 h 43 min']))
            ->assertOk();

        $this->assertDatabaseHas('titles', ['id' => $title->id, 'type' => 'Serie', 'duration' => null, 'seasons' => 2]);
    }

    public function test_user_deletes_a_title(): void
    {
        $this->actingAs(User::factory()->create());
        $title = Title::create($this->movieData());

        $this->deleteJson("/api/titles/{$title->id}")->assertNoContent();

        $this->assertDatabaseMissing('titles', ['id' => $title->id]);
    }

    public function test_updating_or_deleting_a_missing_title_returns_404(): void
    {
        $this->actingAs(User::factory()->create());

        $this->putJson('/api/titles/999', $this->movieData())->assertNotFound();
        $this->deleteJson('/api/titles/999')->assertNotFound();
    }
}
