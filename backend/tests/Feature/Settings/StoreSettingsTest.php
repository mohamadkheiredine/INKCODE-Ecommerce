<?php

use App\Enums\AuditAction;
use App\Enums\DeductionStage;
use App\Enums\Role;
use App\Models\StoreSettings;
use App\Services\Settings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * A complete, valid settings form, with any value replaced.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsForm(array $overrides = []): array
{
    return [
        'name' => 'Cedar & Co.',
        'contact_email' => 'hello@cedar.test',
        'contact_phone' => '+961 1 234 567',
        'address' => "12 Hamra Street\nBeirut, Lebanon",
        'currency_code' => 'EUR',
        'currency_symbol' => '€',
        'vat_rate' => '11.5',
        'deduction_stage' => 'preparing',
        'return_window_days' => 30,
        'low_stock_threshold' => 8,
        'default_locale' => 'ar',
        'default_theme' => 'dark',
        ...$overrides,
    ];
}

/**
 * Every Role but the Admin: none of them may see or change all settings.
 *
 * @return list<Role>
 */
function rolesThatMayNotManageSettings(): array
{
    return array_values(array_filter(Role::cases(), fn (Role $role): bool => $role !== Role::Admin));
}

describe('reading the settings', function () {
    it('starts every store with the defaults the other modules rely on', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->getJson('/api/v1/settings')
            ->assertOk()
            ->assertJsonPath('data.currency_code', 'USD')
            ->assertJsonPath('data.currency_symbol', '$')
            ->assertJsonPath('data.vat_rate', '5.00')
            ->assertJsonPath('data.deduction_stage', 'shipped')
            ->assertJsonPath('data.return_window_days', 14)
            ->assertJsonPath('data.low_stock_threshold', 5)
            ->assertJsonPath('data.default_locale', 'en')
            ->assertJsonPath('data.default_theme', 'light')
            ->assertJsonPath('data.logo_url', null);
    });

    it('gives other modules the same defaults, typed', function () {
        $settings = app(Settings::class);

        expect($settings->vatRate())->toBe('5.00')
            ->and($settings->currencyCode())->toBe('USD')
            ->and($settings->deductionStage())->toBe(DeductionStage::Shipped)
            ->and($settings->returnWindowDays())->toBe(14)
            ->and($settings->lowStockThreshold())->toBe(5);
    });

    it('shows all settings to the Admin only', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->getJson('/api/v1/settings')
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    })->with(rolesThatMayNotManageSettings());
});

describe('changing the settings', function () {
    it('saves every setting, records what changed, and other modules see it at once', function () {
        $admin = userWithRole(Role::Admin);
        $settings = app(Settings::class);
        expect($settings->vatRate())->toBe('5.00');

        $this->actingAs($admin)
            ->putJson('/api/v1/settings', settingsForm())
            ->assertOk()
            ->assertJsonPath('data.name', 'Cedar & Co.')
            ->assertJsonPath('data.currency_code', 'EUR')
            ->assertJsonPath('data.currency_symbol', '€')
            ->assertJsonPath('data.vat_rate', '11.50')
            ->assertJsonPath('data.deduction_stage', 'preparing')
            ->assertJsonPath('data.return_window_days', 30)
            ->assertJsonPath('data.default_locale', 'ar');

        expect($settings->vatRate())->toBe('11.50')
            ->and($settings->deductionStage())->toBe(DeductionStage::Preparing)
            ->and($settings->lowStockThreshold())->toBe(8);

        $entry = auditTrailOf('store_settings', 1)->sole();
        expect($entry->action)->toBe(AuditAction::Updated)
            ->and($entry->user_id)->toBe($admin->id)
            ->and($entry->before)->toMatchArray(['vat_rate' => '5.00', 'deduction_stage' => 'shipped', 'currency_code' => 'USD'])
            ->and($entry->after)->toMatchArray(['vat_rate' => '11.50', 'deduction_stage' => 'preparing', 'currency_code' => 'EUR']);
    });

    it('records only the settings that changed, and nothing when none did', function () {
        $this->actingAs(userWithRole(Role::Admin));
        $this->putJson('/api/v1/settings', settingsForm())->assertOk();

        $this->putJson('/api/v1/settings', settingsForm(['vat_rate' => '7']))->assertOk();
        $this->putJson('/api/v1/settings', settingsForm(['vat_rate' => '7.00']))->assertOk();

        $entries = auditTrailOf('store_settings', 1);
        expect($entries)->toHaveCount(2)
            ->and($entries->last()?->before)->toBe(['vat_rate' => '11.50'])
            ->and($entries->last()?->after)->toBe(['vat_rate' => '7.00']);
    });

    it('lets the Admin clear the optional contact details', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->putJson('/api/v1/settings', settingsForm(['contact_email' => null, 'contact_phone' => '', 'address' => null]))
            ->assertOk()
            ->assertJsonPath('data.contact_email', null)
            ->assertJsonPath('data.contact_phone', null)
            ->assertJsonPath('data.address', null);
    });

    it('refuses values the other modules cannot work with', function (array $values, string $field, string $message) {
        $this->actingAs(userWithRole(Role::Admin))
            ->putJson('/api/v1/settings', settingsForm($values))
            ->assertUnprocessable()
            ->assertJsonValidationErrors([$field => $message]);

        expect(app(Settings::class)->vatRate())->toBe('5.00');
    })->with([
        'no store name' => [['name' => ''], 'name', 'The store name field is required.'],
        'a currency code that is not three capital letters' => [['currency_code' => 'usd'], 'currency_code', 'Use the three-letter ISO code, for example USD or EUR.'],
        'no currency symbol' => [['currency_symbol' => ''], 'currency_symbol', 'The currency symbol field is required.'],
        'a VAT rate over 100%' => [['vat_rate' => '100.01'], 'vat_rate', 'The VAT rate field must be between 0 and 100.'],
        'a negative VAT rate' => [['vat_rate' => '-1'], 'vat_rate', 'The VAT rate field must be between 0 and 100.'],
        'a VAT rate with three decimals' => [['vat_rate' => '5.125'], 'vat_rate', 'The VAT rate field must have 0-2 decimal places.'],
        'an unknown Deduction stage' => [['deduction_stage' => 'delivered'], 'deduction_stage', 'The selected Deduction stage is invalid.'],
        'a Return window of no days' => [['return_window_days' => 0], 'return_window_days', 'The Return window field must be between 1 and 365.'],
        'a fractional Low-stock threshold' => [['low_stock_threshold' => 2.5], 'low_stock_threshold', 'The Low-stock threshold field must be an integer.'],
        'a negative Low-stock threshold' => [['low_stock_threshold' => -1], 'low_stock_threshold', 'The Low-stock threshold field must be at least 0.'],
        'an invalid email' => [['contact_email' => 'not-an-email'], 'contact_email', 'The contact email field must be a valid email address.'],
        'an unknown language' => [['default_locale' => 'fr'], 'default_locale', 'The selected default language is invalid.'],
    ]);

    it('refuses every Role but the Admin', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->putJson('/api/v1/settings', settingsForm())
            ->assertForbidden();

        expect(app(Settings::class)->currencyCode())->toBe('USD')
            ->and(auditTrailOf('store_settings', 1))->toBeEmpty();
    })->with(rolesThatMayNotManageSettings());

    it('refuses a guest', function () {
        $this->putJson('/api/v1/settings', settingsForm())->assertUnauthorized();
    });
});

describe('the store logo', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('uploads a logo, replacing the earlier file, and records the change', function () {
        Storage::disk('public')->put('store/old.png', 'old');
        StoreSettings::query()->whereKey(1)->update(['logo_path' => 'store/old.png']);

        $response = $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png', 200, 200)])
            ->assertOk();

        $path = app(Settings::class)->current()->logo_path;
        Storage::disk('public')->assertExists((string) $path);
        Storage::disk('public')->assertMissing('store/old.png');
        $response->assertJsonPath('data.logo_url', Storage::disk('public')->url((string) $path));
        expect(auditTrailOf('store_settings', 1)->sole()->after)->toBe(['logo_path' => $path]);
    });

    it('removes the logo', function () {
        Storage::disk('public')->put('store/old.png', 'old');
        StoreSettings::query()->whereKey(1)->update(['logo_path' => 'store/old.png']);

        $this->actingAs(userWithRole(Role::Admin))
            ->deleteJson('/api/v1/settings/logo')
            ->assertOk()
            ->assertJsonPath('data.logo_url', null);

        Storage::disk('public')->assertMissing('store/old.png');
    });

    it('accepts only PNG, JPEG or WebP images up to 1 MB', function () {
        $this->actingAs(userWithRole(Role::Admin))
            ->postJson('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'The logo field must be a file of type: png, jpg, jpeg, webp.']);

        $this->postJson('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')->size(1025)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['logo' => 'The logo field must not be greater than 1024 kilobytes.']);
    });

    it('refuses every Role but the Admin', function (Role $role) {
        $this->actingAs(userWithRole($role))
            ->postJson('/api/v1/settings/logo', ['logo' => UploadedFile::fake()->image('logo.png')])
            ->assertForbidden();
        $this->deleteJson('/api/v1/settings/logo')->assertForbidden();
    })->with([Role::Operations, Role::Viewer]);
});

describe('the public store profile', function () {
    it('shows every signed-in User the store name, logo, contact details (for Invoices) and currency', function (Role $role) {
        $this->actingAs(userWithRole(Role::Admin))->putJson('/api/v1/settings', settingsForm())->assertOk();

        $this->actingAs(userWithRole($role))
            ->getJson('/api/v1/store')
            ->assertOk()
            ->assertExactJson(['data' => [
                'name' => 'Cedar & Co.',
                'logo_url' => null,
                'contact_email' => 'hello@cedar.test',
                'contact_phone' => '+961 1 234 567',
                'address' => "12 Hamra Street\nBeirut, Lebanon",
                'currency' => ['code' => 'EUR', 'symbol' => '€'],
            ]]);
    })->with(Role::cases());

    it('is not shown to a guest', function () {
        $this->getJson('/api/v1/store')->assertUnauthorized();
    });
});

it('gives the demo store a profile and keeps the default business settings', function () {
    seedStore(demoMode: true);

    $settings = app(Settings::class);
    expect($settings->current()->name)->toBe('Inkcode Demo Store')
        ->and($settings->vatRate())->toBe('5.00')
        ->and($settings->deductionStage())->toBe(DeductionStage::Shipped);
});
