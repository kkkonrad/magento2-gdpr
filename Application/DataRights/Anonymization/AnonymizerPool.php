<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Anonymization;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataAnonymizerInterface;

class AnonymizerPool
{
    /** @var PersonalDataAnonymizerInterface[] */
    private array $processors;

    /** @param PersonalDataAnonymizerInterface[] $processors */
    public function __construct(array $processors = [])
    {
        $byCode = [];
        foreach ($processors as $processor) {
            if (!$processor instanceof PersonalDataAnonymizerInterface) {
                throw new InvalidArgumentException(
                    'Every GDPR anonymizer must implement PersonalDataAnonymizerInterface.'
                );
            }
            $code = $processor->getCode();
            if ($code === '' || isset($byCode[$code])) {
                throw new InvalidArgumentException(sprintf('Duplicate or empty GDPR anonymizer code "%s".', $code));
            }
            $byCode[$code] = $processor;
        }
        uasort($byCode, static function (
            PersonalDataAnonymizerInterface $left,
            PersonalDataAnonymizerInterface $right
        ): int {
            return [$left->getPriority(), $left->getCode()] <=> [$right->getPriority(), $right->getCode()];
        });
        $this->processors = array_values($byCode);
    }

    /** @return PersonalDataAnonymizerInterface[] */
    public function all(): array
    {
        return $this->processors;
    }
}
