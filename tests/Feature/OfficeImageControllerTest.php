<?php

namespace Tests\Feature;

use App\Models\Office;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfficeImageControllerTest extends TestCase {

    use RefreshDatabase;

    /**
     * @test
     */
    public function itUploadsAnImageAndStoreItUnderOffice()
    {

        Storage::fake();

        $user = User::factory()->create();
        $office = Office::factory()->for($user)->create();

        $this->actingAs($user);

        $response = $this->post("/api/offices/{$office->id}/image", [
            'image' => UploadedFile::fake()->image('image.png')
        ]);

        $response->assertCreated();

        Storage::disk('public')->assertExists([
            $response->json('data.path')
        ]);
    }
}
