<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class WebInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('db:ensure-schema');
    }

    public function test_root_redirects_to_emails_index(): void
    {
        $response = $this->get('/');
        $response->assertRedirect(route('emails.index'));
    }

    public function test_emails_index_returns_200_and_shows_search_ui(): void
    {
        $response = $this->get(route('emails.index'));
        $response->assertStatus(200);
        $response->assertSee('Email search', false);
        $response->assertSee('Filters', false);
        $response->assertSee('Migration dashboard', false);
        $response->assertSee('Sender email', false);
        $response->assertSee('Apply filters', false);
    }

    public function test_emails_index_with_filters_returns_200(): void
    {
        $response = $this->get(route('emails.index', [
            'sender_email' => 'test@example.com',
            'receiver_email' => '',
            'date_from' => '',
            'date_to' => '',
        ]));
        $response->assertStatus(200);
        $response->assertSee('Email search', false);
    }
}
