<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Erasure;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataEraserInterface;

class EraserPool
{
    /** @var PersonalDataEraserInterface[] */
    private array $erasers;

    /** @param PersonalDataEraserInterface[] $erasers */
    public function __construct(array $erasers = [])
    {
        $byCode = [];
        foreach ($erasers as $eraser) {
            if (!$eraser instanceof PersonalDataEraserInterface) {
                throw new InvalidArgumentException('Every GDPR eraser must implement PersonalDataEraserInterface.');
            }
            $code = $eraser->getCode();
            if ($code === '' || isset($byCode[$code])) {
                throw new InvalidArgumentException(sprintf('Duplicate or empty GDPR eraser code "%s".', $code));
            }
            $byCode[$code] = $eraser;
        }
        uasort($byCode, static function (
            PersonalDataEraserInterface $left,
            PersonalDataEraserInterface $right
        ): int {
            return [$left->getPriority(), $left->getCode()] <=> [$right->getPriority(), $right->getCode()];
        });
        $this->erasers = array_values($byCode);
    }

    /** @return PersonalDataEraserInterface[] */
    public function all(): array
    {
        return $this->erasers;
    }
}
