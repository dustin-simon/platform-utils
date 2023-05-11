<?php

namespace Dustin\PlatformUtils\Core\Framework;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Migration\MigrationCollection;
use Shopware\Core\Framework\Migration\MigrationCollectionLoader;
use Shopware\Core\Framework\Migration\MigrationSource;
use Shopware\Core\Framework\Plugin\Context\ActivateContext;
use Shopware\Core\Framework\Plugin\Context\DeactivateContext;
use Shopware\Core\Framework\Plugin\Context\InstallContext;
use Shopware\Core\Framework\Plugin\Context\UninstallContext;
use Shopware\Core\Framework\Plugin\Context\UpdateContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\System\SystemConfig\Util\ConfigReader;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class AdditionalBundle extends Bundle
{
    public function install(InstallContext $installContext, ContainerInterface $container): void
    {
        $this->runMigrations($installContext, $container->get(MigrationCollectionLoader::class));
        $this->saveConfigs($container->get(SystemConfigService::class));
    }

    public function postInstall(InstallContext $installContext, ContainerInterface $container): void
    {
    }

    public function update(UpdateContext $updateContext, ContainerInterface $container): void
    {
        $this->runMigrations($updateContext, $container->get(MigrationCollectionLoader::class));
        $this->saveConfigs($container->get(SystemConfigService::class));
    }

    public function postUpdate(UpdateContext $updateContext, ContainerInterface $container): void
    {
    }

    public function activate(ActivateContext $activateContext, ContainerInterface $container): void
    {
    }

    public function deactivate(DeactivateContext $deactivateContext, ContainerInterface $container): void
    {
    }

    public function uninstall(UninstallContext $uninstallContext, ContainerInterface $container): void
    {
        $connection = $container->get(Connection::class);

        if (!$uninstallContext->keepUserData()) {
            $this->removeMigrations($connection);
            $this->removeTables($connection);
            $this->removeConfigs($connection);
        }
    }

    final protected function runMigrations(InstallContext $context, MigrationCollectionLoader $migrationLoader): void
    {
        if ($context->isAutoMigrate()) {
            $this->createMigrationCollection($migrationLoader)->migrateInPlace();
        }
    }

    final protected function removeMigrations(Connection $connection): void
    {
        $class = addcslashes($this->getMigrationNamespace(), '\\_%').'%';
        $connection->executeUpdate('DELETE FROM migration WHERE class LIKE :class', ['class' => $class]);
    }

    final protected function removeTables(Connection $connection): void
    {
        foreach ($this->getTablesToRemove() as $table) {
            $connection->executeUpdate(sprintf('DELETE FROM `%s`', $table));
            $connection->executeUpdate(sprintf('DROP TABLE IF EXISTS `%s`', $table));
        }
    }

    final protected function saveConfigs(SystemConfigService $configService): void
    {
        $reader = new ConfigReader();

        foreach ($this->getConfigs() as $name) {
            $config = $reader->getConfigFromBundle($this, $name);
            $configService->saveConfig($config, $this->getName().".$name.", false);
        }
    }

    final protected function removeConfigs(Connection $connection): void
    {
        $connection->executeUpdate('DELETE FROM `system_config` WHERE `configuration_key` LIKE "%'.$this->getName().'%"');
    }

    /**
     * @return string[]
     */
    protected function getConfigs(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    protected function getTablesToRemove(): array
    {
        return [];
    }

    private function createMigrationCollection(MigrationCollectionLoader $migrationLoader): MigrationCollection
    {
        $migrationPath = $this->getMigrationPath();

        if (!is_dir($migrationPath)) {
            return $migrationLoader->collect('null');
        }

        $migrationLoader->addSource(new MigrationSource($this->getName(), [
            $migrationPath => $this->getMigrationNamespace(),
        ]));

        $collection = $migrationLoader->collect($this->getName());
        $collection->sync();

        return $collection;
    }
}
