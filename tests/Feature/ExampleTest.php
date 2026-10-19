<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_health_endpoint_is_available_without_api_token(): void
    {
        $response = $this->get('/up');

        $response->assertStatus(200);
    }
}
