<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250930134045 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE board (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE card (id INT AUTO_INCREMENT NOT NULL, parent_column_id INT NOT NULL, title VARCHAR(200) DEFAULT NULL, description LONGTEXT DEFAULT NULL, position INT NOT NULL, INDEX IDX_161498D34FC5A13B (parent_column_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `column` (id INT AUTO_INCREMENT NOT NULL, board_id INT NOT NULL, title VARCHAR(100) NOT NULL, position INT NOT NULL, INDEX IDX_7D53877EE7EC5785 (board_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(190) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', available_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', delivered_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_75EA56E0FB7336F0 (queue_name), INDEX IDX_75EA56E0E3BD61CE (available_at), INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE card ADD CONSTRAINT FK_161498D34FC5A13B FOREIGN KEY (parent_column_id) REFERENCES `column` (id)');
        $this->addSql('ALTER TABLE `column` ADD CONSTRAINT FK_7D53877EE7EC5785 FOREIGN KEY (board_id) REFERENCES board (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE card DROP FOREIGN KEY FK_161498D34FC5A13B');
        $this->addSql('ALTER TABLE `column` DROP FOREIGN KEY FK_7D53877EE7EC5785');
        $this->addSql('DROP TABLE board');
        $this->addSql('DROP TABLE card');
        $this->addSql('DROP TABLE `column`');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
