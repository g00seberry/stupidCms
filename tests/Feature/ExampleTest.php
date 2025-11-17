<?php

namespace Tests\Feature;

use Tests\Support\FeatureTestCase;

class ExampleTest extends FeatureTestCase
{    /**
     * A basic test example.
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
    }
}
