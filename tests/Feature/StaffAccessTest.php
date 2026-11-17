<?php

namespace Tests\Feature;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class StaffAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_active_user_can_access_the_admin_panel(): void
    {
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_inactive_user_cannot_access_the_admin_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_guest_is_redirected_to_the_filament_login(): void
    {
        $this->get('/admin')
            ->assertRedirect('/admin/login');
    }

    public function test_creating_a_user_through_the_resource_hashes_the_password(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'New Staff',
                'email' => 'new-staff@example.com',
                'password' => 'password',
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'new-staff@example.com')->firstOrFail();

        $this->assertNotSame('password', $created->password);
        $this->assertTrue(Hash::check('password', $created->password));
    }

    public function test_user_cannot_deactivate_or_delete_themselves(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin);

        $this->assertFalse(Gate::allows('deactivate', $admin));
        $this->assertFalse(Gate::allows('delete', $admin));

        Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
            ->assertFormFieldDisabled('is_active')
            ->assertActionHidden('delete');
    }

    public function test_last_active_user_cannot_be_deactivated_or_deleted(): void
    {
        $lastActive = User::factory()->create(['is_active' => true]);
        $inactive = User::factory()->create(['is_active' => false]);

        $this->assertSame(1, User::where('is_active', true)->count());
        $this->assertFalse(Gate::forUser($inactive)->allows('deactivate', $lastActive));
        $this->assertFalse(Gate::forUser($inactive)->allows('delete', $lastActive));
    }

    public function test_active_user_can_deactivate_or_delete_another_user(): void
    {
        $admin = User::factory()->create();
        $other = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('deactivate', $other));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $other));
    }
}
