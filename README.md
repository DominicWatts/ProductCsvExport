# Magento 2 product CSV export

Command line tool to export product CSV data in logical format. Useful for dumping large product collections.

## Install instructions

    composer require dominicwatts/productcsvexport

    php bin/magento setup:upgrade

    php bin/magento setup:di:compile

## Usage instructions

    xigen:export:product [-s|--store STORE] [-l|--limit [LIMIT]]

    php bin/magento xigen:export:product 

    php bin/magento xigen:export:product -l 10

    php bin/magento xigen:export:product -s 1 -l 10    

Check `./pub/media/xigen/product-export.csv`

Create `./pub/media/xigen` if not exist