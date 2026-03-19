<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20250317130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add webhooks_config_hash and webhooks_registered_at to shopify_access_token';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('shopify_access_token');
        $table->addColumn('webhooks_config_hash', 'string', ['length' => 64, 'notnull' => false]);
        $table->addColumn('webhooks_registered_at', 'datetime_immutable', ['notnull' => false]);
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('shopify_access_token');
        $table->dropColumn('webhooks_registered_at');
        $table->dropColumn('webhooks_config_hash');
    }
}
