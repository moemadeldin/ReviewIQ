<?php

declare(strict_types=1);

use App\Traits\APIResponder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final class ApiResponderTestInstance
{
    use APIResponder;

    public function callSuccess(array|object $data, string $message, int $code = Response::HTTP_OK): JsonResponse
    {
        return $this->success($data, $message, $code);
    }

    public function callFail(string $message, int $code = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->fail($message, $code);
    }

    public function callNoContent(): Illuminate\Http\Response
    {
        return $this->noContent();
    }
}

it('returns success response', function (): void {
    $instance = new ApiResponderTestInstance();

    $response = $instance->callSuccess(data: ['key' => 'value'], message: 'OK');

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->status())->toBe(200)
        ->and($response->getData(true))->toBe([
            'status' => 'Success',
            'message' => 'OK',
            'data' => ['key' => 'value'],
        ]);
});

it('returns fail response', function (): void {
    $instance = new ApiResponderTestInstance();

    $response = $instance->callFail(message: 'Something went wrong', code: 422);

    expect($response)->toBeInstanceOf(JsonResponse::class)
        ->and($response->status())->toBe(422)
        ->and($response->getData(true))->toBe([
            'status' => 'Failed',
            'message' => 'Something went wrong',
        ]);
});

it('returns no content response', function (): void {
    $instance = new ApiResponderTestInstance();

    $response = $instance->callNoContent();

    expect($response)->toBeInstanceOf(Response::class)
        ->and($response->status())->toBe(204);
});
