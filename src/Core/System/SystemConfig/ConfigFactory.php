<?php

namespace Dustin\PlatformUtils\Core\System\SystemConfig;

use Dustin\Encapsulation\ArrayEncapsulation;
use Dustin\Encapsulation\Encapsulation;
use Dustin\Encapsulation\ObjectMapping;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ConfigFactory
{
    public const GLOBAL = 'global';

    public const PER_SALES_CHANNEL = 'perSalesChannel';

    /**
     * @var EntityRepository
     */
    private $salesChannelRepository;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    /**
     * @var string|null
     */
    private $bundleName = null;

    /**
     * @var array|null
     */
    private $salesChannelIds = null;

    public function __construct(
        EntityRepository $salesChannelRepository,
        SystemConfigService $systemConfigService,
        string $bundleName = null
    ) {
        $this->salesChannelRepository = $salesChannelRepository;
        $this->systemConfigService = $systemConfigService;
        $this->bundleName = $bundleName;
    }

    public function createConfig(string $name, string $mode = self::GLOBAL): ArrayEncapsulation
    {
        $name = $this->convertToConfigName($name);

        if ($mode === self::GLOBAL) {
            return $this->buildConfig($name, null);
        }

        if ($mode !== self::PER_SALES_CHANNEL) {
            throw new \InvalidArgumentException(sprintf('%s is not a valid mode', $mode));
        }

        $this->loadSalesChannelIds();

        $config = ObjectMapping::create(ArrayEncapsulation::class);

        foreach ($this->salesChannelIds as $salesChannelId) {
            $config->set(
                $salesChannelId,
                $this->buildConfig($name, $salesChannelId)
            );
        }

        return $config;
    }

    private function buildConfig(string $name, ?string $salesChannelId): ArrayEncapsulation
    {
        $configValues = $this->systemConfigService->get(
            $name,
            $salesChannelId
        );

        return new Encapsulation((array) $configValues);
    }

    private function convertToConfigName(string $name): string
    {
        if ($this->bundleName === null) {
            return $name;
        }

        if (strpos($name, $this->bundleName) === 0) {
            return $name;
        }

        return $this->bundleName.'.'.$name;
    }

    private function loadSalesChannelIds(): void
    {
        if ($this->salesChannelIds !== null) {
            return;
        }

        $criteria = new Criteria();
        $criteria->addAggregation(new TermsAggregation('salesChannelIds', 'id'));
        $criteria->setLimit(1);

        $result = $this->salesChannelRepository->search($criteria, Context::createDefaultContext());
        $aggregation = $result->getAggregations()->get('salesChannelIds');

        $this->salesChannelIds = [];

        foreach ($aggregation->getBuckets() as $bucket) {
            $value = $bucket->getKey();
            $this->salesChannelIds[$value] = $value;
        }
    }
}
