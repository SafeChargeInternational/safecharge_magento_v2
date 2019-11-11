<?php

namespace Safecharge\Safecharge\Setup;

use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Ddl\Table;
use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;

/**
 * Safecharge Safecharge install schema.
 *
 * @category Safecharge
 * @package  Safecharge_Safecharge
 */
class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     *
     * @param SchemaSetupInterface   $setup
     * @param ModuleContextInterface $context
     *
     * @return void
     * @throws \Zend_Db_Exception
     */
    public function install(
        SchemaSetupInterface $setup,
        ModuleContextInterface $context
    ) {
        $setup->startSetup();

        if (!$setup->tableExists('safecharge_safecharge_api_request_log_grid')) {
            $table = $setup->getConnection()
                ->newTable(
                    $setup->getTable('safecharge_safecharge_api_request_log_grid')
                )
                ->addColumn(
                    'request_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'identity' => true,
                        'nullable' => false,
                        'primary' => true,
                        'unsigned' => true,
                    ],
                    'Request Id'
                )
                ->addColumn(
                    'parent_request_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Parent Request Id'
                )
                ->addColumn(
                    'method',
                    Table::TYPE_TEXT,
                    30,
                    [
                        'nullable' => false,
                    ],
                    'Method'
                )
                ->addColumn(
                    'request',
                    Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => true,
                    ],
                    'Request'
                )
                ->addColumn(
                    'response',
                    Table::TYPE_TEXT,
                    null,
                    [
                        'nullable' => true,
                    ],
                    'Response'
                )
                ->addColumn(
                    'increment_id',
                    Table::TYPE_TEXT,
                    32,
                    [],
                    'Increment Id'
                )
                ->addColumn(
                    'customer_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => true,
                        'unsigned' => true,
                    ],
                    'Customer Id'
                )
                ->addColumn(
                    'website_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => false,
                        'unsigned' => true,
                    ],
                    'Website Id'
                )
                ->addColumn(
                    'store_id',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => false,
                        'unsigned' => true,
                    ],
                    'Store Id'
                )
                ->addColumn(
                    'status',
                    Table::TYPE_INTEGER,
                    null,
                    [
                        'nullable' => false,
                        'unsigned' => true,
                    ],
                    'Status'
                )
                ->addColumn(
                    'created_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    [
                        'nullable' => false,
                        'default' => Table::TIMESTAMP_INIT,
                    ],
                    'Created At'
                )
                ->addColumn(
                    'updated_at',
                    Table::TYPE_TIMESTAMP,
                    null,
                    [
                        'nullable' => false,
                        'default' => Table::TIMESTAMP_INIT_UPDATE,
                    ],
                    'Updated At'
                )
                ->setComment('Safecharge Safecharge Api Request Log Grid Table');
            $setup->getConnection()->createTable($table);

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                    $setup->getIdxName(
                        $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                        ['method', 'request', 'response', 'increment_id'],
                        AdapterInterface::INDEX_TYPE_FULLTEXT
                    ),
                    ['method', 'request', 'response', 'increment_id'],
                    AdapterInterface::INDEX_TYPE_FULLTEXT
                );

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                    $setup->getIdxName(
                        $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                        ['parent_request_id'],
                        AdapterInterface::INDEX_TYPE_INDEX
                    ),
                    ['parent_request_id'],
                    AdapterInterface::INDEX_TYPE_INDEX
                );

            $setup->getConnection()
                ->addIndex(
                    $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                    $setup->getIdxName(
                        $setup->getTable('safecharge_safecharge_api_request_log_grid'),
                        ['status'],
                        AdapterInterface::INDEX_TYPE_INDEX
                    ),
                    ['status'],
                    AdapterInterface::INDEX_TYPE_INDEX
                );
        }
        $setup->endSetup();
    }
}
