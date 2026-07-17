<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Api;

interface RandomIdGeneratorInterface
{
    public function uuid(): string;

    public function bytes(int $length): string;
}
