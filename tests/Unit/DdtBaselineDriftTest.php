<?php

namespace Tests\Unit;

use App\Console\Commands\DdtBaseline;
use PHPUnit\Framework\TestCase;

// Pembaca struktur dump baseline + pembanding dengan struktur sekarang (dasar penolakan Reset data dummy).
// Fungsi murni atas teks contoh: tidak menyentuh database, tidak menjalankan restore.
class DdtBaselineDriftTest extends TestCase
{
    private const DUMP = <<<'SQL'
        -- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
        /*!40101 SET NAMES utf8mb4 */;

        --
        -- Table structure for table `do_line`
        --

        DROP TABLE IF EXISTS `do_line`;
        /*!50503 SET character_set_client = utf8mb4 */;
        CREATE TABLE `do_line` (
          `id_do_line` bigint unsigned NOT NULL AUTO_INCREMENT,
          `do_id` bigint unsigned NOT NULL,
          `qty` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT 'see `co_order_item`.`qty`',
          `net` decimal(14,2) GENERATED ALWAYS AS ((`qty` - 1)) STORED,
          PRIMARY KEY (`id_do_line`),
          UNIQUE KEY `do_line_do_id_qty_unique` (`do_id`,`qty`),
          KEY `do_line_do_id_index` (`do_id`),
          CONSTRAINT `do_line_do_id_foreign` FOREIGN KEY (`do_id`) REFERENCES `do_order` (`id_do`) ON DELETE CASCADE
        ) ENGINE=InnoDB AUTO_INCREMENT=42 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        LOCK TABLES `do_line` WRITE;
        INSERT INTO `do_line` VALUES (1,1,10.00,9.00),(2,1,5.00,'CREATE TABLE `fake` (\n  `x` int\n)');
        UNLOCK TABLES;

        DROP TABLE IF EXISTS `do_order`;
        CREATE TABLE `do_order` (
          `id_do` bigint unsigned NOT NULL AUTO_INCREMENT,
          `do_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
          PRIMARY KEY (`id_do`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

        --
        -- Temporary view structure for view `v_do_open`
        --

        DROP TABLE IF EXISTS `v_do_open`;
        /*!50001 DROP VIEW IF EXISTS `v_do_open`*/;
        /*!50001 CREATE VIEW `v_do_open` AS SELECT
         1 AS `id_do`,
         1 AS `do_no`*/;
        SQL;

    private function dump(): array
    {
        return DdtBaseline::dumpSchema(explode("\n", self::DUMP));
    }

    public function test_dump_parser_reads_only_columns_of_real_tables(): void
    {
        $this->assertSame([
            'do_line' => ['id_do_line', 'do_id', 'qty', 'net'],
            'do_order' => ['id_do', 'do_no'],
        ], $this->dump());
    }

    public function test_dump_parser_accepts_windows_line_endings(): void
    {
        $this->assertSame($this->dump(), DdtBaseline::dumpSchema(explode("\n", str_replace("\n", "\r\n", self::DUMP))));
    }

    public function test_no_drift_when_structure_is_the_same(): void
    {
        $this->assertSame([], DdtBaseline::diffSchema('ddtsdi', $this->dump(), $this->dump()));
    }

    public function test_drift_lists_columns_and_tables_added_and_removed_since_the_baseline(): void
    {
        $live = [
            'do_line' => ['id_do_line', 'do_id', 'qty', 'qty_received', 'receive_note'],   // + 2 kolom, - net
            'do_receipt' => ['id_do_receipt'],                                              // tabel baru
        ];                                                                                  // do_order dihapus

        $this->assertSame([
            ['db' => 'ddtsdi', 'table' => 'do_line', 'kind' => 'column_added', 'columns' => ['qty_received', 'receive_note']],
            ['db' => 'ddtsdi', 'table' => 'do_line', 'kind' => 'column_removed', 'columns' => ['net']],
            ['db' => 'ddtsdi', 'table' => 'do_receipt', 'kind' => 'table_added', 'columns' => []],
            ['db' => 'ddtsdi', 'table' => 'do_order', 'kind' => 'table_removed', 'columns' => []],
        ], DdtBaseline::diffSchema('ddtsdi', $this->dump(), $live));
    }

    public function test_every_drift_kind_has_a_name_and_an_effect(): void
    {
        foreach (DdtBaseline::diffSchema('ddtsdi', $this->dump(), ['do_line' => ['id_do_line', 'x'], 'new_t' => ['id']]) as $row) {
            $this->assertArrayHasKey($row['kind'], DdtBaseline::DRIFT);
            $this->assertStringStartsWith("ddtsdi.{$row['table']}: ", DdtBaseline::describeDrift($row));
        }
    }
}
