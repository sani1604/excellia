<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_loads_successfully(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->get('/')->assertRedirect('/login');
        $this->get('/login')
            ->assertOk()
            ->assertSee('Excellia School')
            ->assertSee('Portal Sign In');
    }
}
