<?php

namespace Tests\Unit;

use App\Console\Commands\DdtStage;
use PHPUnit\Framework\TestCase;

// Penyaring dump set stage: Restore hanya memuat tabel yang isinya berbeda, jadi blok tabel lain harus terbuang utuh
// sementara kepala & ekor dump (pengaturan sesi) tetap ikut. Fungsi murni atas teks contoh, tidak menyentuh database.
class DdtStageTest extends TestCase
{
    private const DUMP = <<<'SQL'
        -- MySQL dump 10.13  Distrib 8.0.46, for Win64 (x86_64)
        /*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;

        --
        -- Dumping data for table `co_order`
        --

        LOCK TABLES `co_order` WRITE;
        INSERT INTO `co_order` (`id_order`, `notes`) VALUES (1,'-- Dumping data for table `pr_line`');
        UNLOCK TABLES;

        --
        -- Dumping data for table `pr_line`
        --

        LOCK TABLES `pr_line` WRITE;
        INSERT INTO `pr_line` (`pr_line_id`) VALUES (7);
        UNLOCK TABLES;

        --
        -- Dumping data for table `pr_requisition`
        --

        LOCK TABLES `pr_requisition` WRITE;
        INSERT INTO `pr_requisition` (`pr_id`) VALUES (3);
        UNLOCK TABLES;
        /*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;
        /*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;

        -- Dump completed on 2026-10-02 19:38:35
        SQL;

    private function lines(): array
    {
        return array_map(fn ($l) => preg_replace('/^ {8}/', '', $l)."\n", explode("\n", self::DUMP));
    }

    public function test_only_the_requested_tables_are_loaded(): void
    {
        $sql = implode('', iterator_to_array(DdtStage::dumpOf($this->lines(), ['pr_line']), false));

        $this->assertStringContainsString('INSERT INTO `pr_line`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `co_order`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `pr_requisition`', $sql);
    }

    public function test_session_settings_at_both_ends_are_kept(): void
    {
        $sql = implode('', iterator_to_array(DdtStage::dumpOf($this->lines(), ['pr_line']), false));

        $this->assertStringContainsString('FOREIGN_KEY_CHECKS=0', $sql);
        $this->assertStringContainsString('SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS', $sql);
    }

    public function test_a_table_name_inside_row_data_does_not_switch_tables(): void
    {
        $sql = implode('', iterator_to_array(DdtStage::dumpOf($this->lines(), ['co_order']), false));

        $this->assertStringContainsString('INSERT INTO `co_order`', $sql);
        $this->assertStringNotContainsString('INSERT INTO `pr_line`', $sql);
    }

    public function test_set_names(): void
    {
        $this->assertTrue(DdtStage::validKey('stage-01'));
        $this->assertTrue(DdtStage::validKey('_before-build'));
        $this->assertFalse(DdtStage::validKey('../baseline'));
        $this->assertFalse(DdtStage::validKey('Stage 1'));
        $this->assertFalse(DdtStage::validKey(''));
    }
}
