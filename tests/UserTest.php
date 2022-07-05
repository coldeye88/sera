<?php

use Laravel\Lumen\Testing\DatabaseMigrations;
use Laravel\Lumen\Testing\DatabaseTransactions;

class UserTest extends TestCase
{
    /**
     * A basic test example.
     *
     * @return void
     */
    public function testIndex()
    {
        $this->get('/api/users')
            ->seeStatusCode(200);

        // $this->assertEquals(
        //     $this->app->version(), $this->response->getContent()
        // );
    }

    public function testStoringData()
    {
        $this->json('POST', '/api/users/', [
            'name' => 'devops',
            'email' => 'devops@admin.dev',
            'password' => 'password'
        ])->seeJson([
            'success' => true,
        ]);
    }

    public function testStoringDataValidation()
    {
        $response = $this->call('POST', '/api/users/', ['name' => null]);
        $response->assertJsonValidationErrors('name', $responseKey = "error");
    }
}
