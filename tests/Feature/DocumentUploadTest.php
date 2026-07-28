<?php

namespace Tests\Feature;

use App\Jobs\ProcessDocumentJob;
use App\Models\Document;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_hr_can_upload_a_pdf_policy(): void
    {
        Storage::fake('public');
        Queue::fake();

        $response = $this->actingAs(User::factory()->create())->postJson(route('documents.upload'), [
            'name' => 'Annual Leave Policy',
            'input_type' => 'pdf',
            'file' => UploadedFile::fake()->create('annual-leave.pdf', 100, 'application/pdf'),
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('input_type', 'pdf');

        $document = Document::firstOrFail();

        Storage::disk('public')->assertExists($document->file_path);
        $this->assertSame('pdf', $document->metadata['input_type']);
        Queue::assertPushed(ProcessDocumentJob::class);
    }

    public function test_hr_can_enter_policy_text_directly(): void
    {
        Storage::fake('public');
        Queue::fake();

        $content = 'Employees are entitled to 14 days of annual leave per calendar year.';

        $response = $this->actingAs(User::factory()->create())->postJson(route('documents.upload'), [
            'name' => 'Annual Leave Policy',
            'input_type' => 'text',
            'content' => $content,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('input_type', 'text');

        $document = Document::firstOrFail();

        Storage::disk('public')->assertExists($document->file_path);
        $this->assertSame($content, Storage::disk('public')->get($document->file_path));
        $this->assertSame('text', $document->metadata['input_type']);
        Queue::assertPushed(ProcessDocumentJob::class);
    }

    public function test_hr_must_choose_one_valid_policy_input(): void
    {
        Storage::fake('public');
        Queue::fake();

        $response = $this->actingAs(User::factory()->create())->postJson(route('documents.upload'), [
            'name' => 'Annual Leave Policy',
            'input_type' => 'text',
            'content' => 'short',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseCount('documents', 0);
        Queue::assertNothingPushed();
    }
}
