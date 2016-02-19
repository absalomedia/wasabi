<?php

namespace ABM\Wasabi;

use Analog\Analog;
use Ratchet\ConnectionInterface;
use Ratchet\MessageComponentInterface;

class Cart extends Prestashop 
{

	 /**
     * @param string $data
     */
    private function getCartData($data)
    {
        $cart = substr($data, 0, strpos($data, ','));
        $cust = substr($data, strpos($data, ',') + 1);

        Analog::log("Cart & customer variables: $data");
        $otherCarts = $this->processFindCarts($cart, $cust);

        return $otherCarts;
    }

    /**
     * @param string $cart
     * @param string $cust
     */
    private function processFindCarts($cart, $cust)
    {
        $sql = 'SELECT DISTINCT pc.id_cart as id, DATE_FORMAT(pc.date_upd,"%a %D %b %Y, %l:%i %p") as timer from '._DB_PREFIX_.'cart as pc
                LEFT JOIN  '._DB_PREFIX_.'cart_product as pcp on pcp.id_cart = pc.id_cart
                WHERE pc.id_cart NOT IN (SELECT po.id_cart FROM  '._DB_PREFIX_.'orders as po)
                AND pcp.id_product IS NOT NULL
                AND pc.id_customer = '.(int) $cust.'
                AND pc.id_cart != '.(int) $cart.'
                ORDER BY pc.date_upd DESC
                LIMIT 10';
        if ($results = $this->dbConn->fetchRowMany($sql)) {
            foreach ($results as &$row) {
                $row['token'] = md5(_COOKIE_KEY_.'recover_cart_'.$row['id']);
            }

            return $results;
        }
    }

}