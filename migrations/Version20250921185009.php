<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250921185009 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute (
              id INT AUTO_INCREMENT NOT NULL,
              attribute_group_id INT DEFAULT NULL,
              name VARCHAR(255) DEFAULT NULL,
              sort_order INT DEFAULT NULL,
              show_in_category TINYINT(1) DEFAULT NULL,
              short_name VARCHAR(255) DEFAULT NULL,
              code VARCHAR(100) DEFAULT NULL,
              UNIQUE INDEX UNIQ_FA7AEFFB77153098 (code),
              INDEX IDX_FA7AEFFB62D643B7 (attribute_group_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE attribute_group (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) DEFAULT NULL,
              sort_order INT DEFAULT NULL,
              code VARCHAR(100) DEFAULT NULL,
              UNIQUE INDEX UNIQ_8EF8A77377153098 (code),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE carousel (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              products_id JSON DEFAULT NULL,
              sort INT DEFAULT NULL,
              place VARCHAR(255) DEFAULT NULL,
              INDEX IDX_1DD747004584665A (product_id),
              INDEX carousel_place_idx (place),
              INDEX carousel_sort_idx (sort),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart (
              id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
              user_id INT DEFAULT NULL,
              token VARCHAR(36) DEFAULT NULL,
              currency VARCHAR(3) NOT NULL,
              pricing_policy VARCHAR(16) DEFAULT 'SNAPSHOT' NOT NULL,
              subtotal INT DEFAULT 0 NOT NULL,
              discount_total INT DEFAULT 0 NOT NULL,
              total INT DEFAULT 0 NOT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              expires_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)',
              version INT DEFAULT 1 NOT NULL,
              shipping_method VARCHAR(64) DEFAULT NULL,
              shipping_cost INT DEFAULT 0 NOT NULL,
              ship_to_city VARCHAR(128) DEFAULT NULL,
              shipping_data JSON DEFAULT NULL,
              UNIQUE INDEX UNIQ_BA388B75F37A13B (token),
              INDEX IDX_BA388B75F37A13B (token),
              INDEX IDX_BA388B7A76ED395 (user_id),
              INDEX IDX_BA388B7F9D83E2 (expires_at),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart_idempotency (
              id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
              idempotency_key VARCHAR(255) NOT NULL,
              cart_id VARCHAR(26) NOT NULL,
              endpoint VARCHAR(255) NOT NULL,
              request_hash VARCHAR(64) NOT NULL,
              status VARCHAR(16) NOT NULL,
              http_status SMALLINT UNSIGNED DEFAULT NULL,
              response_data JSON DEFAULT NULL,
              instance_id VARCHAR(64) DEFAULT NULL,
              created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              expires_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              UNIQUE INDEX uk_idem_key (idempotency_key),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart_item (
              id INT AUTO_INCREMENT NOT NULL,
              cart_id BINARY(16) NOT NULL COMMENT '(DC2Type:ulid)',
              product_id INT NOT NULL,
              product_name VARCHAR(255) NOT NULL,
              unit_price INT NOT NULL,
              qty INT NOT NULL,
              row_total INT NOT NULL,
              options_price_modifier INT DEFAULT 0 NOT NULL,
              effective_unit_price INT DEFAULT 0 NOT NULL,
              options_hash VARCHAR(32) DEFAULT '' NOT NULL,
              selected_options_data JSON DEFAULT NULL,
              options_snapshot JSON DEFAULT NULL,
              priced_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)',
              version INT DEFAULT 1 NOT NULL,
              INDEX IDX_F0FE25271AD5CDBF (cart_id),
              INDEX IDX_F0FE25274584665A (product_id),
              UNIQUE INDEX uniq_cart_product_options (
                cart_id, product_id, options_hash
              ),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE cart_item_option_assignment (
              cart_item_id INT NOT NULL,
              product_option_value_assignment_id INT NOT NULL,
              INDEX IDX_9CAE0419E9B59A59 (cart_item_id),
              INDEX IDX_9CAE04194859DA71 (
                product_option_value_assignment_id
              ),
              PRIMARY KEY(
                cart_item_id, product_option_value_assignment_id
              )
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE category (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) DEFAULT NULL,
              slug VARCHAR(255) DEFAULT NULL,
              visibility TINYINT(1) DEFAULT NULL,
              parent_category_id INT DEFAULT NULL,
              meta_title VARCHAR(255) DEFAULT NULL,
              meta_description VARCHAR(255) DEFAULT NULL,
              meta_keywords VARCHAR(255) DEFAULT NULL,
              sort_order INT DEFAULT NULL,
              meta_h1 VARCHAR(255) DEFAULT NULL,
              description VARCHAR(255) DEFAULT NULL,
              navbar_visibility TINYINT(1) DEFAULT 1,
              footer_visibility TINYINT(1) DEFAULT 1,
              INDEX category_name_idx (name),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE city (
              id INT AUTO_INCREMENT NOT NULL,
              address VARCHAR(255) DEFAULT NULL,
              postal_code VARCHAR(255) DEFAULT NULL,
              federal_district VARCHAR(255) DEFAULT NULL,
              region_type VARCHAR(255) DEFAULT NULL,
              region VARCHAR(255) DEFAULT NULL,
              city_type VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              kladr_id BIGINT DEFAULT NULL,
              fias_level INT DEFAULT NULL,
              capital_marker INT DEFAULT NULL,
              geo_lat DOUBLE PRECISION DEFAULT NULL,
              geo_lon DOUBLE PRECISION DEFAULT NULL,
              population BIGINT DEFAULT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE city_modal (
              id INT AUTO_INCREMENT NOT NULL,
              fias_id BIGINT DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              sort INT NOT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE delivery_type (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              code VARCHAR(255) NOT NULL,
              active TINYINT(1) NOT NULL,
              sort_order INT NOT NULL,
              is_default TINYINT(1) NOT NULL,
              UNIQUE INDEX UNIQ_5D429FB377153098 (code),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE fias (
              fias_id INT AUTO_INCREMENT NOT NULL,
              parent_id INT NOT NULL,
              postalcode VARCHAR(6) DEFAULT NULL,
              offname VARCHAR(120) DEFAULT NULL,
              shortname VARCHAR(10) DEFAULT NULL,
              level SMALLINT NOT NULL,
              INDEX postalcode_idx (postalcode),
              INDEX offname_idx (offname),
              INDEX level_idx (level),
              INDEX parent_id_idx (parent_id),
              INDEX osl_idx (offname, shortname, level),
              PRIMARY KEY(fias_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE manufacturer (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              UNIQUE INDEX UNIQ_3D0AE6DC5E237E06 (name),
              INDEX manufacturer_name_idx (name),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `option` (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              sort_order INT NOT NULL,
              code VARCHAR(100) DEFAULT NULL,
              UNIQUE INDEX UNIQ_5A8600B077153098 (code),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE option_value (
              id INT AUTO_INCREMENT NOT NULL,
              option_id INT NOT NULL,
              value VARCHAR(255) NOT NULL,
              sort_order INT NOT NULL,
              code VARCHAR(100) DEFAULT NULL,
              INDEX IDX_249CE55CA7C41D6F (option_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `order` (
              id INT AUTO_INCREMENT NOT NULL,
              customer_id INT DEFAULT NULL,
              delivery_id INT DEFAULT NULL,
              order_id INT NOT NULL,
              date_added DATETIME NOT NULL,
              comment VARCHAR(255) DEFAULT NULL,
              status INT DEFAULT NULL,
              total INT DEFAULT NULL,
              UNIQUE INDEX customer_id (customer_id),
              UNIQUE INDEX delivery_id (delivery_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE `order_customer` (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) DEFAULT NULL,
              phone VARCHAR(255) DEFAULT NULL,
              email VARCHAR(255) DEFAULT NULL,
              ip VARCHAR(255) DEFAULT NULL,
              user_agent VARCHAR(255) DEFAULT NULL,
              phone_normal BIGINT DEFAULT NULL,
              comment VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE order_delivery (
              id INT AUTO_INCREMENT NOT NULL,
              city_id INT DEFAULT NULL,
              type VARCHAR(255) DEFAULT NULL,
              address VARCHAR(255) DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              cost INT DEFAULT NULL,
              pvz VARCHAR(255) DEFAULT NULL,
              is_free TINYINT(1) DEFAULT 0 NOT NULL,
              is_custom_calculate TINYINT(1) DEFAULT 0 NOT NULL,
              pricing_source VARCHAR(32) DEFAULT NULL,
              pricing_trace JSON DEFAULT NULL,
              pvz_code VARCHAR(255) DEFAULT NULL,
              delivery_date DATE DEFAULT NULL,
              delivery_time TIME DEFAULT NULL,
              INDEX IDX_D6790EA18BAC62AF (city_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE order_product_options (
              id INT AUTO_INCREMENT NOT NULL,
              order_product_id INT NOT NULL,
              product_id INT DEFAULT NULL,
              option_name VARCHAR(255) DEFAULT NULL,
              value JSON DEFAULT NULL,
              price INT DEFAULT NULL,
              INDEX IDX_CAE5226BF65E9B0F (order_product_id),
              INDEX product_id (product_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE order_products (
              id INT AUTO_INCREMENT NOT NULL,
              orders_id INT DEFAULT NULL,
              product_id INT NOT NULL,
              product_name VARCHAR(255) NOT NULL,
              price INT DEFAULT NULL,
              quantity INT NOT NULL,
              sale_price INT DEFAULT NULL,
              INDEX orders_id (orders_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE order_status (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(20) NOT NULL,
              sort INT NOT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product (
              id INT AUTO_INCREMENT NOT NULL,
              manufacturer_id INT DEFAULT NULL,
              name VARCHAR(255) NOT NULL,
              slug VARCHAR(255) DEFAULT NULL,
              sort_order INT DEFAULT NULL,
              effective_price INT DEFAULT NULL,
              status TINYINT(1) DEFAULT 0 NOT NULL,
              type VARCHAR(32) DEFAULT 'simple' NOT NULL,
              quantity INT DEFAULT NULL,
              options_json JSON DEFAULT NULL,
              attribute_json JSON DEFAULT NULL,
              code BINARY(16) DEFAULT NULL COMMENT '(DC2Type:ulid)',
              description VARCHAR(255) DEFAULT NULL,
              price INT DEFAULT NULL,
              sale_price INT DEFAULT NULL,
              currency VARCHAR(3) DEFAULT 'RUB' NOT NULL,
              date_added DATETIME DEFAULT NULL,
              date_edited DATETIME DEFAULT NULL,
              UNIQUE INDEX UNIQ_D34A04AD989D9B62 (slug),
              UNIQUE INDEX UNIQ_D34A04AD77153098 (code),
              INDEX IDX_D34A04ADA23B42D (manufacturer_id),
              INDEX name (name),
              INDEX product_status_idx (status),
              INDEX product_date_added_idx (date_added),
              INDEX product_sort_order_idx (sort_order),
              INDEX idx_product_status_created (status, date_added),
              INDEX product_effective_price_idx (effective_price),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_attribute_assignment (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT NOT NULL,
              attribute_id INT NOT NULL,
              attribute_group_id INT DEFAULT NULL,
              data_type VARCHAR(16) DEFAULT 'string' NOT NULL,
              string_value VARCHAR(255) DEFAULT NULL,
              text_value LONGTEXT DEFAULT NULL,
              int_value INT DEFAULT NULL,
              decimal_value NUMERIC(15, 4) DEFAULT NULL,
              bool_value TINYINT(1) DEFAULT NULL,
              date_value DATE DEFAULT NULL,
              json_value JSON DEFAULT NULL,
              unit VARCHAR(32) DEFAULT NULL,
              position INT DEFAULT 0 NOT NULL,
              sort_order INT DEFAULT NULL,
              INDEX IDX_2AFBF05C4584665A (product_id),
              INDEX IDX_2AFBF05CB6E62EFA (attribute_id),
              INDEX IDX_2AFBF05C62D643B7 (attribute_group_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_image (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT DEFAULT NULL,
              image_url VARCHAR(255) NOT NULL,
              sort_order INT NOT NULL,
              INDEX product_id (product_id),
              INDEX product_image_sort_idx (sort_order),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_option_value_assignment (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT NOT NULL,
              option_id INT NOT NULL,
              value_id INT NOT NULL,
              height INT DEFAULT NULL,
              price INT DEFAULT NULL,
              set_price TINYINT(1) DEFAULT NULL,
              bulbs_count INT DEFAULT NULL,
              lighting_area INT DEFAULT NULL,
              sku VARCHAR(64) DEFAULT NULL,
              attributes JSON DEFAULT NULL,
              original_sku VARCHAR(64) DEFAULT NULL,
              sale_price INT DEFAULT NULL,
              sort_order INT DEFAULT NULL,
              quantity INT DEFAULT NULL,
              INDEX IDX_E85761E84584665A (product_id),
              INDEX IDX_E85761E8A7C41D6F (option_id),
              INDEX IDX_E85761E8F920BBA2 (value_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_seo (
              product_id INT NOT NULL,
              meta_title VARCHAR(255) DEFAULT NULL,
              meta_description VARCHAR(255) DEFAULT NULL,
              meta_keywords VARCHAR(255) DEFAULT NULL,
              h1 VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(product_id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE product_to_category (
              id INT AUTO_INCREMENT NOT NULL,
              product_id INT NOT NULL,
              category_id INT NOT NULL,
              is_parent TINYINT(1) DEFAULT 0,
              position INT DEFAULT 1,
              visibility TINYINT(1) DEFAULT 1,
              INDEX IDX_673A19704584665A (product_id),
              INDEX IDX_673A197012469DE2 (category_id),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pvz_points (
              id INT AUTO_INCREMENT NOT NULL,
              city_id INT DEFAULT NULL,
              code VARCHAR(255) DEFAULT NULL,
              name VARCHAR(255) DEFAULT NULL,
              city_code VARCHAR(255) DEFAULT NULL,
              address VARCHAR(255) DEFAULT NULL,
              tariff_zone VARCHAR(255) DEFAULT NULL,
              price INT DEFAULT NULL,
              delivery_period INT DEFAULT NULL,
              phone VARCHAR(255) DEFAULT NULL,
              region VARCHAR(255) DEFAULT NULL,
              type_of_office VARCHAR(20) DEFAULT NULL,
              metro VARCHAR(255) DEFAULT NULL,
              only_prepaid_orders VARCHAR(5) DEFAULT NULL,
              postal INT DEFAULT NULL,
              city VARCHAR(255) DEFAULT NULL,
              time VARCHAR(255) DEFAULT NULL,
              card INT DEFAULT NULL,
              shirota DOUBLE PRECISION DEFAULT NULL,
              dolgota DOUBLE PRECISION DEFAULT NULL,
              company VARCHAR(20) DEFAULT NULL,
              INDEX IDX_E80F6C3D8BAC62AF (city_id),
              INDEX city (city),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE pvz_price (
              id INT AUTO_INCREMENT NOT NULL,
              city_id INT DEFAULT NULL,
              city VARCHAR(255) NOT NULL,
              srok VARCHAR(255) DEFAULT NULL,
              city2 VARCHAR(255) DEFAULT NULL,
              code VARCHAR(20) DEFAULT NULL,
              alias VARCHAR(255) DEFAULT NULL,
              region VARCHAR(255) DEFAULT NULL,
              cost INT DEFAULT NULL,
              free INT DEFAULT NULL,
              calculate_price INT DEFAULT NULL,
              calculate_delivery_period VARCHAR(255) DEFAULT NULL,
              INDEX IDX_C5BFAFEF8BAC62AF (city_id),
              INDEX city (city),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE settings (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(255) NOT NULL,
              value VARCHAR(255) DEFAULT NULL,
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id INT AUTO_INCREMENT NOT NULL,
              name VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              password VARCHAR(255) NOT NULL,
              UNIQUE INDEX UNIQ_1483A5E95E237E06 (name),
              PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              attribute
            ADD
              CONSTRAINT FK_FA7AEFFB62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              carousel
            ADD
              CONSTRAINT FK_1DD747004584665A FOREIGN KEY (product_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item
            ADD
              CONSTRAINT FK_F0FE25271AD5CDBF FOREIGN KEY (cart_id) REFERENCES cart (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item
            ADD
              CONSTRAINT FK_F0FE25274584665A FOREIGN KEY (product_id) REFERENCES product (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item_option_assignment
            ADD
              CONSTRAINT FK_9CAE0419E9B59A59 FOREIGN KEY (cart_item_id) REFERENCES cart_item (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              cart_item_option_assignment
            ADD
              CONSTRAINT FK_9CAE04194859DA71 FOREIGN KEY (
                product_option_value_assignment_id
              ) REFERENCES product_option_value_assignment (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              option_value
            ADD
              CONSTRAINT FK_249CE55CA7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              `order`
            ADD
              CONSTRAINT FK_F52993989395C3F3 FOREIGN KEY (customer_id) REFERENCES `order_customer` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              `order`
            ADD
              CONSTRAINT FK_F529939812136921 FOREIGN KEY (delivery_id) REFERENCES order_delivery (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_delivery
            ADD
              CONSTRAINT FK_D6790EA18BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_product_options
            ADD
              CONSTRAINT FK_CAE5226BF65E9B0F FOREIGN KEY (order_product_id) REFERENCES order_products (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              order_products
            ADD
              CONSTRAINT FK_5242B8EBCFFE9AD6 FOREIGN KEY (orders_id) REFERENCES `order` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product
            ADD
              CONSTRAINT FK_D34A04ADA23B42D FOREIGN KEY (manufacturer_id) REFERENCES manufacturer (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_attribute_assignment
            ADD
              CONSTRAINT FK_2AFBF05C4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_attribute_assignment
            ADD
              CONSTRAINT FK_2AFBF05CB6E62EFA FOREIGN KEY (attribute_id) REFERENCES attribute (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_attribute_assignment
            ADD
              CONSTRAINT FK_2AFBF05C62D643B7 FOREIGN KEY (attribute_group_id) REFERENCES attribute_group (id) ON DELETE RESTRICT
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_image
            ADD
              CONSTRAINT FK_64617F034584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_option_value_assignment
            ADD
              CONSTRAINT FK_E85761E84584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_option_value_assignment
            ADD
              CONSTRAINT FK_E85761E8A7C41D6F FOREIGN KEY (option_id) REFERENCES `option` (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_option_value_assignment
            ADD
              CONSTRAINT FK_E85761E8F920BBA2 FOREIGN KEY (value_id) REFERENCES option_value (id)
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_seo
            ADD
              CONSTRAINT FK_8C5EB82F4584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_to_category
            ADD
              CONSTRAINT FK_673A19704584665A FOREIGN KEY (product_id) REFERENCES product (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              product_to_category
            ADD
              CONSTRAINT FK_673A197012469DE2 FOREIGN KEY (category_id) REFERENCES category (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pvz_points
            ADD
              CONSTRAINT FK_E80F6C3D8BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              pvz_price
            ADD
              CONSTRAINT FK_C5BFAFEF8BAC62AF FOREIGN KEY (city_id) REFERENCES fias (fias_id) ON DELETE
            SET
              NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE attribute DROP FOREIGN KEY FK_FA7AEFFB62D643B7');
        $this->addSql('ALTER TABLE carousel DROP FOREIGN KEY FK_1DD747004584665A');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25271AD5CDBF');
        $this->addSql('ALTER TABLE cart_item DROP FOREIGN KEY FK_F0FE25274584665A');
        $this->addSql('ALTER TABLE cart_item_option_assignment DROP FOREIGN KEY FK_9CAE0419E9B59A59');
        $this->addSql('ALTER TABLE cart_item_option_assignment DROP FOREIGN KEY FK_9CAE04194859DA71');
        $this->addSql('ALTER TABLE option_value DROP FOREIGN KEY FK_249CE55CA7C41D6F');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F52993989395C3F3');
        $this->addSql('ALTER TABLE `order` DROP FOREIGN KEY FK_F529939812136921');
        $this->addSql('ALTER TABLE order_delivery DROP FOREIGN KEY FK_D6790EA18BAC62AF');
        $this->addSql('ALTER TABLE order_product_options DROP FOREIGN KEY FK_CAE5226BF65E9B0F');
        $this->addSql('ALTER TABLE order_products DROP FOREIGN KEY FK_5242B8EBCFFE9AD6');
        $this->addSql('ALTER TABLE product DROP FOREIGN KEY FK_D34A04ADA23B42D');
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05C4584665A');
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05CB6E62EFA');
        $this->addSql('ALTER TABLE product_attribute_assignment DROP FOREIGN KEY FK_2AFBF05C62D643B7');
        $this->addSql('ALTER TABLE product_image DROP FOREIGN KEY FK_64617F034584665A');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E84584665A');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8A7C41D6F');
        $this->addSql('ALTER TABLE product_option_value_assignment DROP FOREIGN KEY FK_E85761E8F920BBA2');
        $this->addSql('ALTER TABLE product_seo DROP FOREIGN KEY FK_8C5EB82F4584665A');
        $this->addSql('ALTER TABLE product_to_category DROP FOREIGN KEY FK_673A19704584665A');
        $this->addSql('ALTER TABLE product_to_category DROP FOREIGN KEY FK_673A197012469DE2');
        $this->addSql('ALTER TABLE pvz_points DROP FOREIGN KEY FK_E80F6C3D8BAC62AF');
        $this->addSql('ALTER TABLE pvz_price DROP FOREIGN KEY FK_C5BFAFEF8BAC62AF');
        $this->addSql('DROP TABLE attribute');
        $this->addSql('DROP TABLE attribute_group');
        $this->addSql('DROP TABLE carousel');
        $this->addSql('DROP TABLE cart');
        $this->addSql('DROP TABLE cart_idempotency');
        $this->addSql('DROP TABLE cart_item');
        $this->addSql('DROP TABLE cart_item_option_assignment');
        $this->addSql('DROP TABLE category');
        $this->addSql('DROP TABLE city');
        $this->addSql('DROP TABLE city_modal');
        $this->addSql('DROP TABLE delivery_type');
        $this->addSql('DROP TABLE fias');
        $this->addSql('DROP TABLE manufacturer');
        $this->addSql('DROP TABLE `option`');
        $this->addSql('DROP TABLE option_value');
        $this->addSql('DROP TABLE `order`');
        $this->addSql('DROP TABLE `order_customer`');
        $this->addSql('DROP TABLE order_delivery');
        $this->addSql('DROP TABLE order_product_options');
        $this->addSql('DROP TABLE order_products');
        $this->addSql('DROP TABLE order_status');
        $this->addSql('DROP TABLE product');
        $this->addSql('DROP TABLE product_attribute_assignment');
        $this->addSql('DROP TABLE product_image');
        $this->addSql('DROP TABLE product_option_value_assignment');
        $this->addSql('DROP TABLE product_seo');
        $this->addSql('DROP TABLE product_to_category');
        $this->addSql('DROP TABLE pvz_points');
        $this->addSql('DROP TABLE pvz_price');
        $this->addSql('DROP TABLE settings');
        $this->addSql('DROP TABLE users');
    }
}
