<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseEditorMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_unauthenticated_user_is_redirected_from_editor_routes(): void
    {
        $response = $this->get(route('editor.player-templates.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_regular_user_gets_403_on_editor_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_edit_database' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('editor.player-templates.index'));

        $response->assertForbidden();
    }

    public function test_database_editor_can_access_editor_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_edit_database' => true,
        ]);

        $response = $this->actingAs($user)
            ->get(route('editor.player-templates.index'));

        $response->assertOk();
    }

    public function test_admin_can_access_editor_routes(): void
    {
        $user = User::factory()->create([
            'is_admin' => true,
            'can_edit_database' => false,
        ]);

        $response = $this->actingAs($user)
            ->get(route('editor.player-templates.index'));

        $response->assertOk();
    }

    public function test_regular_user_gets_403_on_editor_squad_route(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_edit_database' => false,
        ]);
        $team = Team::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('editor.player-templates.squad', $team->id));

        $response->assertForbidden();
    }

    public function test_database_editor_can_access_editor_squad_route(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
            'can_edit_database' => true,
        ]);
        $team = Team::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('editor.player-templates.squad', $team->id));

        $response->assertOk();
    }
}
