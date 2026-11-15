<?php

namespace Tests\Feature;

use App\Models\DataProgram;
use App\Models\Draft;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePermissionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_permission_does_not_allow_edit_or_delete(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => 'scm', 'permissions' => ['drafts']]));
        $this->getJson('/api/drafts')->assertOk();
        $this->postJson('/api/drafts', [])->assertForbidden();
        $this->deleteJson('/api/drafts/999')->assertForbidden();
        $this->getJson('/api/invoices')->assertForbidden();
    }

    public function test_delete_permission_can_be_given_to_ar_without_edit_permission(): void
    {
        $record = DataProgram::create(['area' => 'jabar', 'brand' => 'realme', 'row_hash' => 'permission-delete', 'dealer_name' => 'Dealer']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => 'ar', 'permissions' => ['data_program', 'data_program_delete']]));
        $this->putJson('/api/data-program/'.$record->id, [])->assertForbidden();
        $this->deleteJson('/api/data-program/'.$record->id)->assertOk();
        $this->assertDatabaseMissing('data_programs', ['id' => $record->id]);
        $this->deleteJson('/api/data-program/'.$record->id)->assertNotFound();
    }

    public function test_empty_permissions_grant_no_module_access(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => 'admin', 'permissions' => []]));
        $this->getJson('/api/drafts')->assertForbidden();
        $this->deleteJson('/api/invoices/999')->assertForbidden();
    }

    public function test_invoice_deletion_keeps_its_draft_and_allows_it_to_be_processed_again(): void
    {
        $draft = Draft::create(['area' => 'jabar', 'brand' => 'realme', 'dealer_name' => 'Dealer', 'cn_number' => 'DELETE-1', 'support_amount' => 1000, 'invoice_type' => 'DSA', 'status' => 'invoiced']);
        $invoice = Invoice::create(['area' => 'jabar', 'brand' => 'realme', 'draft_id' => $draft->id, 'invoice_number' => 'DELETE-1', 'invoice_type' => 'DSA', 'status' => 'generated']);
        $this->actingAs(User::factory()->create(['is_active' => true, 'role' => 'scm', 'permissions' => ['invoices', 'invoices_delete']]));
        $this->deleteJson('/api/invoices/'.$invoice->id)->assertOk();
        $this->assertDatabaseMissing('invoices', ['id' => $invoice->id]);
        $this->assertSame('ready', $draft->fresh()->status);
    }
}
