<?php

namespace Xigen\Export\Console\Command;

use Magento\Catalog\Model\Product\Type as ProductType;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\CatalogInventory\Helper\Stock;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\App\State;
use Magento\Framework\File\Csv;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Phrase;
use Magento\Framework\Stdlib\DateTime\DateTime;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\ProgressBarFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Product extends Command
{
    const STORE_OPTION = 'store';
    const LIMIT_OPTION = 'limit';
    const FILE_PATH = 'xigen/product-export.csv';
    const ROW_DELIMITER = ",";
    const ROW_ENCLOSURE = '"';
    const ROW_END = "\n";

    /**
     * @var array
     */
    public $attributes = [
        'entity_id',
        'sku',
        'color',
        'cost',
        'country_of_manufacture',
        'created_at',
        'custom_design',
        'custom_design_from',
        'custom_design_to',
        'custom_layout',
        'custom_layout_update',
        'custom_layout_update_file',
        'description',
        'gallery',
        'gift_message_available',
        'image',
        'image_label',
        'links_exist',
        'links_purchased_separately',
        'links_title',
        'manufacturer',
        'meta_description',
        'meta_keyword',
        'meta_title',
        'minimal_price',
        'msrp',
        'msrp_display_actual_price_type',
        'name',
        'news_from_date',
        'news_to_date',
        'options_container',
        'page_layout',
        'price',
        'price_type',
        'price_view',
        'samples_title',
        'shipment_type',
        'short_description',
        'sku_type',
        'small_image',
        'small_image_label',
        'special_from_date',
        'special_price',
        'special_to_date',
        'status',
        'swatch_image',
        'tax_class_id',
        'thumbnail',
        'thumbnail_label',
        'updated_at',
        'url_key',
        'url_path',
        'visibility',
        'weight',
        'weight_type'
    ];

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * @var \Magento\Framework\App\State
     */
    protected $state;

    /**
     * @var \Magento\Framework\Stdlib\DateTime\DateTime
     */
    protected $dateTime;

    /**
     * @var InputInterface
     */
    protected $input;

    /**
     * @var OutputInterface
     */
    protected $output;

    /**
     * @var ProgressBarFactory
     */
    protected $progressBarFactory;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $productCollectionFactory;

    /**
     * @var \Magento\CatalogInventory\Helper\Stock
     */
    protected $stockFilter;

    /**
     * @var \Magento\Framework\File\Csv
     */
    protected $csv;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var \Magento\Framework\Filesystem
     */
    protected $filesystem;

    /**
     * @var \Magento\Framework\Filesystem\Driver\File;
     */
    protected $file;

    /**
     * @var string
     */
    protected $mediaUrlPath;

    /**
     * @var string
     */
    protected $mediaPath;

    /**
     * @var string
     */
    protected $fullPath;

    /**
     * @var int
     */
    protected $storeId;

    /**
     * Product constructor.
     * @param \Psr\Log\LoggerInterface $logger
     * @param \Magento\Framework\App\State $state
     * @param \Magento\Framework\Stdlib\DateTime\DateTime $dateTime
     * @param \Symfony\Component\Console\Helper\ProgressBarFactory $progressBarFactory
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param \Magento\CatalogInventory\Helper\Stock $stockFilter
     * @param \Magento\Framework\File\Csv $csv
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Framework\Filesystem\Driver\File $file
     */
    public function __construct(
        LoggerInterface $logger,
        State $state,
        DateTime $dateTime,
        ProgressBarFactory $progressBarFactory,
        CollectionFactory $productCollectionFactory,
        Stock $stockFilter,
        Csv $csv,
        StoreManagerInterface $storeManager,
        Filesystem $filesystem,
        File $file
    ) {
        $this->logger = $logger;
        $this->state = $state;
        $this->dateTime = $dateTime;
        $this->progressBarFactory = $progressBarFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->stockFilter = $stockFilter;
        $this->csv = $csv;
        $this->storeManager = $storeManager;
        $this->filesystem = $filesystem;
        $this->file = $file;
        parent::__construct();
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ) {
        $this->input = $input;
        $this->output = $output;
        $this->state->setAreaCode(Area::AREA_GLOBAL);
        $storeId = $this->input->getOption(self::STORE_OPTION) ?: 1;
        $this->storeManager->setCurrentStore($storeId);

        $limit = $this->input->getOption(self::LIMIT_OPTION) ?: null;

        $this->mediaPath = $this->filesystem
            ->getDirectoryRead(DirectoryList::MEDIA)
            ->getAbsolutePath();

        $this->fullPath = $this->mediaPath . self::FILE_PATH;
        $this->storeId = $this->storeManager->getStore()->getId();
        $this->mediaUrlPath = $this->storeManager
            ->getStore()
            ->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . 'catalog/product';

        $this->output->writeln((string) __(
            '[%1] Start',
            $this->dateTime->gmtDate()
        ));

        $products = $this->getCollection(
            $limit,
            false,
            false,
            $this->attributes
        );

        /** @var ProgressBar $progress */
        $progress = $this->progressBarFactory->create(
            [
                'output' => $this->output,
                'max' => count($products)
            ]
        );

        $progress->setFormat(
            "%current%/%max% [%bar%] %percent:3s%% %elapsed% %memory:6s% \t| <info>%message%</info>"
        );

        if ($output->getVerbosity() !== OutputInterface::VERBOSITY_NORMAL) {
            $progress->setOverwrite(false);
        }

        $entries = [];
        foreach ($products as $product) {
            $entries[] = $this->cleanse($product);
            $progress->setMessage((string) __('Product: %1', $product->getSku()));
            $progress->advance();
        }

        $this->generateFile($entries, self::ROW_ENCLOSURE, self::ROW_DELIMITER, $this->storeId, $this->fullPath);

        $progress->finish();
        $this->output->writeln('');

        $this->output->writeln((string) __(
            '[%1] Finish',
            $this->dateTime->gmtDate()
        ));
    }

    /**
     * Resolve and flatten export data
     * @return array
     */
    public function cleanse($product) // phpcs:ignore 
    {
        $productArray = [];
        foreach ($this->attributes as $attribute) {
            $value = $product->getData($attribute);

            $productAttribute = $product->getResource()->getAttribute($attribute);

            //If multiselect or dropdown, look up value text
            if ($productAttribute->usesSource()) {
                $value = $productAttribute->getSource()->getOptionText($value);
                if ($value instanceof Phrase) {
                    $value = (string) $value;
                }
            }

            // awful # 1
            if ($attribute == 'tier_price') {
                $tierArray = [];
                foreach ($value as $key => $item) {
                    $itemArray = [];
                    foreach ($item as $field => $string) {
                        $itemArray[] = $field . ":" . $string;
                    }
                    $tierArray[] = implode(",", $itemArray);
                }
                $value = implode("|", $tierArray);
            }

            // awful # 2
            if ($attribute == 'media_gallery') {
                $imageArray = [];
                if (isset($value['images'])) {
                    foreach ($value['images'] as $key => $item) {
                        $itemArray = [];
                        foreach ($item as $field => $string) {
                            if (strlen($string) < 1) {
                                continue;
                            }
                            if ($field == 'file') {
                                $string = $this->mediaUrlPath . $string;
                            }

                            $itemArray[] = $field . ":" . $string;
                        }
                        $imageArray[] = implode(",", $itemArray);
                    }
                }
                $value = implode("|", $imageArray);
            }

            if (is_array($value)) {
                $value = implode("|", $value);
            }

            $productArray[$attribute] = $value;
        }

        return $productArray;
    }

    /**
     * Return collection of products.
     * @param int $limit
     * @param bool $inStockOnly
     * @param bool $simpleOnly
     * @param array $attributes
     * @return \Magento\Catalog\Model\ResourceModel\Product\Collection
     */
    public function getCollection($limit = null, $inStockOnly = false, $simpleOnly = true, $attributes = [])
    {
        $collection = $this->productCollectionFactory
            ->create()
            ->addAttributeToSelect(empty($attributes) ? '*' : $attributes);

        if ($limit) {
            $collection->setPageSize($limit);
            $collection->setCurPage(1);
        }

        $collection->addMediaGalleryData();
        $collection->addTierPriceData();

        if ($simpleOnly) {
            $collection->addAttributeToFilter('type_id', ['eq' => ProductType::TYPE_SIMPLE]);
        }

        if ($inStockOnly) {
            $this->stockFilter->addInStockFilterToCollection($collection);
        }

        return $collection;
    }

    /**
     * generateFile method
     * @param arary $entries
     * @param string $enclosure
     * @param string $delimeter
     * @param int $storeId
     * @param string $fileName
     */
    public function generateFile($entries, $enclosure, $delimeter, $storeId, $fileName)
    {
        $fileObj = $this->csv;
        $fileObj->setLineLength(5000);
        $fileObj->setEnclosure($enclosure);
        $fileObj->setDelimiter($delimeter);

        if (!empty($entries)) {
            $headings = array_keys($entries[0]);
            $fileObj->saveData($fileName, [$headings]);
            foreach ($entries as $entry) {
                $dataRow = array_values($entry);
                $this->appendData($fileName, [$dataRow], 'a');
            }
        }
    }

    /**
     * Missing from current version of magento
     * Replace the saveData method by allowing to select the input mode
     * @param string $file
     * @param array $data
     * @param string $mode
     * @return $this
     * @throws \Magento\Framework\Exception\FileSystemException
     */
    public function appendData($file, $data, $mode = 'w')
    {
        // phpcs:disable
        $fileHandler = fopen($file, $mode);
        foreach ($data as $dataRow) {
            $this->file->filePutCsv($fileHandler, $dataRow, self::ROW_DELIMITER, self::ROW_ENCLOSURE);
        }
        fclose($fileHandler);

        return $this;
        // phpcs:enable
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName("xigen:export:product");
        $this->setDescription("Export product data to CSV");
        $this->setDefinition([
            new InputOption(self::STORE_OPTION, '-s', InputOption::VALUE_REQUIRED, 'Store Id'),
            new InputOption(self::LIMIT_OPTION, '-l', InputOption::VALUE_OPTIONAL, 'Limit')
        ]);
        parent::configure();
    }
}
