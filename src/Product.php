<?php

namespace ABM\Wasabi;

use Analog\Analog;

class Product extends Prestashop
{
  /**
     * @param string $data
     */
    public function getProductData($data)
    {
        $category = (int) substr($data, 0, strpos($data, ','));
        if ($category != 0) {
            $products = $this->getProducts($category);
        } else {
            $product = substr($data, strpos($data, ',') + 1);
            $products = $this->getProducts($product);
        }
        Analog::log("Product variables: $data");

        return $products;
    }

    /**
     * @param string $category
     */
    private function getProducts($category)
    {
        $product_ids = $this->getProductIDs($category);
        $products = $this->getProduct($product_ids);

        return $products;
    }

    /**
     * @param string $category
     */
    private function getProductIDs($category)
    {
        $sql = 'SELECT DISTINCT p.id_product
                from '._DB_PREFIX_.'product as p
                LEFT JOIN '._DB_PREFIX_.'image AS i ON i.id_product = p.id_product 
                LEFT JOIN '._DB_PREFIX_.'product_lang as pl ON pl.id_product = p.id_product
                WHERE p.active = 1
                AND p.id_category_default = '.(int) $category.'
                GROUP BY p.id_product';
        $pcats = $this->dbConn->fetchRowMany($sql);
        $ids = '';
        if (is_array($pcats) && (!empty($pcats))) {
            foreach ($pcats as $row) {
                $ids .= $row['id_product'].',';
            }
        }

        $ids = rtrim($ids, ',');

        return $ids;
    }

    /**
     * @param string $ids
     */
    private function getProduct($ids)
    {
        $sql = 'SELECT p.id_product, p.id_supplier, p.ean13, p.upc, p.price, p.wholesale_price, p.on_sale, p.quantity, p.id_category_default,
                    p.show_price, p.available_for_order, p.minimal_quantity, p.customizable,
                    p.out_of_stock, pl.link_rewrite, pl.name, i.id_image, il.legend
                    FROM '._DB_PREFIX_.'product as p                 
                    LEFT JOIN '._DB_PREFIX_.'image AS i ON i.id_product = p.id_product 
                    LEFT JOIN '._DB_PREFIX_.'image_lang as il ON i.id_image = il.id_image
                    WHERE p.id_product IN ('.$ids.')
                    AND i.cover = 1
                    AND p.active = 1
                    GROUP BY p.id_product
                    ORDER BY p.price ASC';

        $result = $this->dbConn->fetchRowMany($sql);

        if (is_array($result)) {
            foreach ($result as $key => $value) {
                $result['cat_id'] = $value['id_category_default'];
                $result['orderprice'] = $this->getOrderPrice($value['id_product']);
                $result['category_default'] = $this->getProductCat($value['id_category_default']);
            }

            return $result;
        }
    }

    private function getOrderPrice($product)
    {
        $sql = 'SELECT ps.price from '._DB_PREFIX_.'product_shop as ps WHERE ps.id_product = '.(int) $product;
        $result = $this->dbConn->fetchColumn($sql);

        return $result;
    }

    /**
     * @param string $product
     */
    private function getProductName($product)
    {
        $sql = 'SELECT pl.name from '._DB_PREFIX_.'product_lang as pl WHERE pl.id_product = '.(int) $product;
        $result = $this->dbConn->fetchColumn($sql);

        return $result;
    }

    private function getProductCat($category)
    {
        $sql = 'SELECT cl.name from '._DB_PREFIX_.'category_lang as cl WHERE cl.id_category = '.(int) $category;
        $result = $this->dbConn->fetchColumn($sql);

        return $result;
    }

}
