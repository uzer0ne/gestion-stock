<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251202093538 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE mouvement_stock ADD COLUMN type VARCHAR(50) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TEMPORARY TABLE __temp__mouvement_stock AS SELECT id, quantite, date_mouvement, produit_id, entrepot_id FROM mouvement_stock');
        $this->addSql('DROP TABLE mouvement_stock');
        $this->addSql('CREATE TABLE mouvement_stock (id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL, quantite INTEGER NOT NULL, date_mouvement DATETIME NOT NULL, produit_id INTEGER NOT NULL, entrepot_id INTEGER NOT NULL, CONSTRAINT FK_61E2C8EBF347EFB FOREIGN KEY (produit_id) REFERENCES produit (id) NOT DEFERRABLE INITIALLY IMMEDIATE, CONSTRAINT FK_61E2C8EB72831E97 FOREIGN KEY (entrepot_id) REFERENCES entrepot (id) NOT DEFERRABLE INITIALLY IMMEDIATE)');
        $this->addSql('INSERT INTO mouvement_stock (id, quantite, date_mouvement, produit_id, entrepot_id) SELECT id, quantite, date_mouvement, produit_id, entrepot_id FROM __temp__mouvement_stock');
        $this->addSql('DROP TABLE __temp__mouvement_stock');
        $this->addSql('CREATE INDEX IDX_61E2C8EBF347EFB ON mouvement_stock (produit_id)');
        $this->addSql('CREATE INDEX IDX_61E2C8EB72831E97 ON mouvement_stock (entrepot_id)');
    }
}
