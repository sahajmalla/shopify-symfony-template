<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\Migrations\AbstractMigration;

final class Version20250317120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create shopify_access_token table for Shopify app access token storage';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->createTable('shopify_access_token');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('shop', 'string', ['length' => 255]);
        $table->addColumn('access_mode', 'string', ['length' => 16]);
        $table->addColumn('token', 'text');
        $table->addColumn('scope', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('refresh_token', 'text', ['notnull' => false]);
        $table->addColumn('expires', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('refresh_token_expires', 'string', ['length' => 32, 'notnull' => false]);
        $table->addColumn('user_id', 'string', ['length' => 255, 'notnull' => false]);
        $table->addColumn('user', 'json', ['notnull' => false]);
        $table->addColumn('created_at', 'datetime_immutable');
        $table->addColumn('updated_at', 'datetime_immutable');
        $primaryKey = PrimaryKeyConstraint::editor()
            ->setUnquotedColumnNames('id')
            ->create();
        $table->addPrimaryKeyConstraint($primaryKey);
        $table->addUniqueIndex(['shop', 'access_mode'], 'shop_access_mode');
        $table->addIndex(['shop'], 'idx_shopify_access_token_shop');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('shopify_access_token');
    }
}
