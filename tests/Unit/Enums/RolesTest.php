<?php

declare(strict_types=1);

use App\Enums\Roles;

it('has all expected cases', function (): void {
    expect(Roles::cases())->toHaveCount(3);

    expect(Roles::Admin->value)->toBe('admin');
    expect(Roles::Owner->value)->toBe('owner');
    expect(Roles::Member->value)->toBe('member');
});

it('returns correct label for each case', function (): void {
    expect(Roles::Admin->label())->toBe('Administrator');
    expect(Roles::Owner->label())->toBe('Owner');
    expect(Roles::Member->label())->toBe('Member');
});
