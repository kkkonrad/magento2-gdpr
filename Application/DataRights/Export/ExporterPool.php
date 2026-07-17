<?php
declare(strict_types=1);

namespace Kkkonrad\Gdpr\Application\DataRights\Export;

use InvalidArgumentException;
use Kkkonrad\Gdpr\Api\DataRights\PersonalDataExporterInterface;

class ExporterPool
{
    /** @var PersonalDataExporterInterface[] */
    private array $exporters;

    /** @param PersonalDataExporterInterface[] $exporters */
    public function __construct(array $exporters = [])
    {
        $byCode = [];
        foreach ($exporters as $exporter) {
            if (!$exporter instanceof PersonalDataExporterInterface) {
                throw new InvalidArgumentException('Every GDPR exporter must implement PersonalDataExporterInterface.');
            }
            $code = $exporter->getCode();
            if ($code === '' || isset($byCode[$code])) {
                throw new InvalidArgumentException(sprintf('Duplicate or empty GDPR exporter code "%s".', $code));
            }
            $byCode[$code] = $exporter;
        }
        uasort($byCode, static function (
            PersonalDataExporterInterface $left,
            PersonalDataExporterInterface $right
        ): int {
            return [$left->getPriority(), $left->getCode()] <=> [$right->getPriority(), $right->getCode()];
        });
        $this->exporters = array_values($byCode);
    }

    /** @return PersonalDataExporterInterface[] */
    public function all(): array
    {
        return $this->exporters;
    }
}
