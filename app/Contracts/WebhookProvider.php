<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

interface WebhookProvider
{
    /**
     * Handle the incoming webhook request.
     *
     * @throws HttpException
     */
    public function handle(Request $request): void;
}
