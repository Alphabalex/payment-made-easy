<?php

namespace NexusPay\PaymentMadeEasy\Tests\Unit\Commands;

use NexusPay\PaymentMadeEasy\Tests\TestCase;

class PaymentGatewaysCommandTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Basic execution
    // -------------------------------------------------------------------------

    public function test_command_exits_successfully(): void
    {
        $this->artisan('payment:gateways')
            ->assertSuccessful();
    }

    public function test_command_displays_gateway_table(): void
    {
        // The table rows contain gateway names like 'paystack'.
        // Table row content is captured by expectsOutputToContain.
        $this->artisan('payment:gateways')
            ->expectsOutputToContain('paystack')
            ->expectsOutputToContain('stripe')
            ->assertSuccessful();
    }

    public function test_command_lists_default_gateway(): void
    {
        // TestCase sets default to 'paystack'
        $this->artisan('payment:gateways')
            ->expectsOutputToContain('paystack')
            ->assertSuccessful();
    }

    // -------------------------------------------------------------------------
    // --configured flag
    // -------------------------------------------------------------------------

    public function test_configured_option_exits_successfully(): void
    {
        $this->artisan('payment:gateways', ['--configured' => true])
            ->assertSuccessful();
    }

    public function test_configured_option_shows_gateways_with_non_empty_credentials(): void
    {
        // TestCase seeds paystack with secret_key = 'sk_test_xxx' (non-empty),
        // so it should appear when --configured is used.
        $this->artisan('payment:gateways', ['--configured' => true])
            ->expectsOutputToContain('paystack')
            ->assertSuccessful();
    }
}
