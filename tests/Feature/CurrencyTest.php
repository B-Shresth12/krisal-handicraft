<?php

namespace Tests\Feature;

use App\Filament\Pages\StoreSettings;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\CurrencyConverter;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    private CurrencyConverter $converter;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $this->converter = app(CurrencyConverter::class);
    }

    public function test_default_rate_is_created_once_at_135(): void
    {
        $this->assertSame(135, (int) $this->converter->rate());

        $setting = StoreSetting::current();

        $this->assertEquals(135, (float) $setting->usd_to_npr_rate);
        $this->assertSame(1, StoreSetting::query()->count());
        $this->assertSame($setting->id, StoreSetting::current()->id);
    }

    public function test_saving_the_rate_invalidates_the_cached_rate(): void
    {
        $this->assertSame(135, (int) $this->converter->rate());

        StoreSetting::current()->update(['usd_to_npr_rate' => 150]);

        $this->assertSame(150, (int) $this->converter->rate());
    }

    public function test_usd_to_npr_rounds_to_whole_rupees_half_up(): void
    {
        $this->assertSame(13500, $this->converter->usdToNpr(100));
        $this->assertSame(1350, $this->converter->usdToNpr('10.00'));
        // 1.50 * 135 = 202.5, which rounds up to 203.
        $this->assertSame(203, $this->converter->usdToNpr(1.5));
        $this->assertSame(1, $this->converter->usdToNpr(0.01));
        $this->assertNull($this->converter->usdToNpr(null));
        $this->assertNull($this->converter->usdToNpr(''));
    }

    public function test_npr_to_usd_rounds_to_two_decimals(): void
    {
        $this->assertSame(1.0, $this->converter->nprToUsd(135));
        $this->assertSame(2.0, $this->converter->nprToUsd(270));
        $this->assertSame(0.01, $this->converter->nprToUsd(1));
        $this->assertNull($this->converter->nprToUsd(null));
    }

    public function test_effective_npr_price_is_converted_when_no_override_exists(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
            'price_npr' => null,
        ]);

        $this->assertFalse($product->hasPriceNprOverride());
        $this->assertSame(13500, $product->effective_price_npr);
    }

    public function test_effective_npr_price_uses_the_override_when_present(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
            'price_npr' => 14000,
        ]);

        $this->assertTrue($product->hasPriceNprOverride());
        $this->assertSame(14000, $product->effective_price_npr);
    }

    public function test_effective_npr_price_follows_rate_changes_for_auto_values(): void
    {
        $product = Product::factory()->create([
            'price' => 100,
            'price_npr' => null,
        ]);

        $this->assertSame(13500, $product->effective_price_npr);

        StoreSetting::current()->update(['usd_to_npr_rate' => 150]);

        $this->assertSame(15000, $product->fresh()->effective_price_npr);
    }

    public function test_effective_compare_at_npr_handles_missing_and_overrides(): void
    {
        $withoutCompare = Product::factory()->create([
            'price' => 100,
            'compare_at_price' => null,
            'compare_at_price_npr' => null,
        ]);

        $this->assertNull($withoutCompare->effective_compare_at_price_npr);

        $auto = Product::factory()->create([
            'price' => 100,
            'compare_at_price' => 120,
            'compare_at_price_npr' => null,
        ]);

        $this->assertFalse($auto->hasCompareAtPriceNprOverride());
        $this->assertSame(16200, $auto->effective_compare_at_price_npr);

        $manual = Product::factory()->create([
            'price' => 100,
            'compare_at_price' => 120,
            'compare_at_price_npr' => 17000,
        ]);

        $this->assertTrue($manual->hasCompareAtPriceNprOverride());
        $this->assertSame(17000, $manual->effective_compare_at_price_npr);
    }

    public function test_active_staff_can_update_the_rate_from_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(StoreSettings::class)
            ->assertFormSet(['usd_to_npr_rate' => '135'])
            ->fillForm(['usd_to_npr_rate' => 160])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEquals(160, (float) StoreSetting::current()->usd_to_npr_rate);
        $this->assertSame(160, (int) $this->converter->rate());
    }

    public function test_the_rate_must_be_greater_than_zero(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => true]));

        Livewire::test(StoreSettings::class)
            ->fillForm(['usd_to_npr_rate' => 0])
            ->call('save')
            ->assertHasFormErrors(['usd_to_npr_rate']);
    }

    public function test_guests_cannot_open_the_settings_page(): void
    {
        $this->get('/admin/store-settings')->assertRedirect('/admin/login');
    }

    public function test_inactive_staff_cannot_open_the_settings_page(): void
    {
        $this->actingAs(User::factory()->create(['is_active' => false]))
            ->get('/admin/store-settings')
            ->assertForbidden();
    }
}
