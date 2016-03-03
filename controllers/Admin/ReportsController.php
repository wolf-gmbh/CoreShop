<?php
/**
 * CoreShop
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License version 3 (GPLv3)
 * For the full copyright and license information, please view the LICENSE.md and gpl-3.0.txt
 * files that are distributed with this source code.
 *
 * @copyright  Copyright (c) 2015 Dominik Pfaffenbauer (http://dominik.pfaffenbauer.at)
 * @license    http://www.coreshop.org/license     GNU General Public License version 3 (GPLv3)
 */

use CoreShop\Plugin;
use CoreShop\Tool;
use CoreShop\Model;
use Pimcore\Controller\Action\Admin;
use CoreShop\Helper\ReportQuery;

class CoreShop_Admin_ReportsController extends Admin
{
    public function getProductsReportAction() {
        $filter = ReportQuery::extractFilterDefinition($this->getParam("filters"));

        $listOrders = new \Pimcore\Model\Object\CoreShopOrderItem\Listing();
        $listOrders->setCondition($filter);
        $listOrders = $listOrders->getObjects();

        $productSales = array();

        foreach($listOrders as $orderItem) {
            $product = $orderItem->getProduct();

            if($product instanceof Model\Product) {
                if (!array_key_exists($product->getId(), $productSales)) {
                    $productSales[$product->getId()] = array(
                        "count" => 0,
                        "salesPrice" => 0,
                        "sales" => 0,
                        "name" => $product->getName(),
                        "profit" => 0
                    );
                }

                $productSales[$product->getId()]['count']++;
                $productSales[$product->getId()]['salesPrice'] = ($productSales[$product->getId()]['salesPrice'] + $orderItem->getRetailPrice()) / 2;
                $productSales[$product->getId()]['sales'] += $orderItem->getRetailPrice() * $orderItem->getAmount();
                $productSales[$product->getId()]['profit'] += (($orderItem->getRetailPrice() - $orderItem->getWholesalePrice()) * $orderItem->getAmount());
            }
        }

        foreach($productSales as &$sale) {
            $sale['salesPrice'] = Tool::formatPrice($sale['salesPrice']);
            $sale['sales'] = Tool::formatPrice($sale['sales']);
            $sale['profit'] = Tool::formatPrice($sale['profit']);
        }

        usort($productSales, function ($item1, $item2) {
            if ($item1['count'] == $item2['count']) return 0;
            return $item1['count'] < $item2['count'] ? 1 : -1;
        });

        $this->_helper->json(array("data" => array_values($productSales)));
    }

    public function getCategoriesReportAction() {
        $filter = ReportQuery::extractFilterDefinition($this->getParam("filters"));

        $listOrders = new \Pimcore\Model\Object\CoreShopOrderItem\Listing();
        $listOrders->setCondition($filter);
        $listOrders = $listOrders->getObjects();

        $catSales = array();

        foreach($listOrders as $orderItem) {
            $product = $orderItem->getProduct();

            if($product instanceof Model\Product) {
                $categories = $product->getCategories();

                foreach($categories as $cat) {
                    if($cat instanceof Model\Category) {
                        if (!array_key_exists($cat->getId(), $catSales)) {
                            $catSales[$cat->getId()] = array(
                                "name" => $cat->getName(),
                                "count" => 0,
                                "sales" => 0,
                                "profit" => 0
                            );
                        }

                        $catSales[$cat->getId()]['count']++;
                        $catSales[$cat->getId()]['sales'] += $orderItem->getRetailPrice() * $orderItem->getAmount();
                        $catSales[$cat->getId()]['profit'] += ($orderItem->getRetailPrice() - $orderItem->getWholesalePrice()) * $orderItem->getAmount();
                    }
                }
            }
        }

        foreach($catSales as &$sale) {
            $sale['sales'] = Tool::formatPrice($sale['sales']);
            $sale['profit'] = Tool::formatPrice($sale['profit']);
        }

        usort($catSales, function ($item1, $item2) {
            if ($item1['count'] == $item2['count']) return 0;
            return $item1['count'] < $item2['count'] ? 1 : -1;
        });

        $this->_helper->json(array("data" => array_values($catSales)));
    }

    public function getCustomersReportAction() {
        $filter = ReportQuery::extractFilterDefinition($this->getParam("filters"));

        $listOrders = new \Pimcore\Model\Object\CoreShopOrder\Listing();
        $listOrders->setCondition($filter);
        $listOrders = $listOrders->getObjects();

        $custSales = array();

        foreach($listOrders as $order) {
            $customer = $order->getCustomer();

            if($customer  instanceof Model\User) {
                if (!array_key_exists($customer->getId(), $custSales)) {
                    $custSales[$customer->getId()] = array(
                        "name" => $customer->getFirstname() . " " . $customer->getLastname(),
                        "count" => 0,
                        "sales" => 0
                    );
                }

                $custSales[$customer->getId()]['count']++;
                $custSales[$customer->getId()]['sales'] += $order->getTotal();
            }
        }

        foreach($custSales as &$sale) {
            $sale['sales'] = Tool::formatPrice($sale['sales']);
        }

        usort($custSales, function ($item1, $item2) {
            if ($item1['count'] == $item2['count']) return 0;
            return $item1['count'] < $item2['count'] ? 1 : -1;
        });

        $this->_helper->json(array("data" => array_values($custSales)));
    }

    /**
     * Return Orders/Carts from last 31 Days
     */
    public function getOrdersCartsReportAction()
    {
        $filters = $this->getParam("filters", array("from" => date('01-m-Y'), "to" => date('m-t-Y')));
        $from = new \Pimcore\Date($filters['from']);
        $to = new \Pimcore\Date($filters['to']);

        $diff = $to->sub($from)->toValue();
        $days = ceil($diff / 60 / 60 / 24) +1;

        $startDate = $from->getTimestamp();

        $data = array();

        for ($i=0; $i<$days; $i++) {
            // documents
            $end = $startDate + ($i * 86400);
            $start = $end - 86399;
            $date = new \Zend_Date($start);

            $listOrders = new \Pimcore\Model\Object\CoreShopOrder\Listing();
            $listOrders->setCondition("o_creationDate > ? AND o_creationDate < ?", array($start, $end));

            $listCarts = new \Pimcore\Model\Object\CoreShopCart\Listing();
            $listCarts->setCondition("o_creationDate > ? AND o_creationDate < ?", array($start, $end));


            $data[] = array(
                "timestamp" => $start,
                "datetext" => $date->get(\Zend_Date::DATE_LONG),
                "orders" => count($listOrders->load()),
                "carts" => count($listCarts->load())
            );
        }

        $this->_helper->json(array("data" => $data));
    }

    /**
     * Return Sales from last 31 days
     */
    public function getSalesReportAction()
    {
        $filters = $this->getParam("filters", array("from" => date('01-m-Y'), "to" => date('m-t-Y')));
        $from = new \Pimcore\Date($filters['from']);
        $to = new \Pimcore\Date($filters['to']);

        $diff = $to->sub($from)->toValue();
        $days = ceil($diff / 60 / 60 / 24) +1;

        $startDate = $from->getTimestamp();

        $data = array();

        for ($i=0; $i<$days; $i++) {
            // documents
            $end = $startDate + ($i * 86400);
            $start = $end - 86399;
            $date = new \Zend_Date($start);

            $listOrders = new \Pimcore\Model\Object\CoreShopOrder\Listing();
            $listOrders->setCondition("o_creationDate > ? AND o_creationDate < ?", array($start, $end));
            $total = 0;

            foreach ($listOrders->getObjects() as $order) {
                $total += $order->getTotal();
            }

            $data[] = array(
                "timestamp" => $start,
                "datetext" => $date->get(\Zend_Date::DATE_LONG),
                "sales" => $total,
                "salesFormatted" => Tool::formatPrice($total)
            );
        }

        $this->_helper->json(array("data" => $data));
    }
}